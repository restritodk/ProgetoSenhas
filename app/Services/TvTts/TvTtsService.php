<?php

namespace App\Services\TvTts;

use App\Contracts\TvSpeechSynthesizer;
use App\Models\DisplayPanel;
use App\Models\TicketCall;
use App\Services\TicketCallVoiceFormatter;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class TvTtsService
{
    public function __construct(
        private TvSpeechSynthesizer $synthesizer,
        private TicketCallVoiceFormatter $voiceFormatter,
    ) {}

    public function isAvailable(): bool
    {
        return $this->synthesizer->isAvailable();
    }

    /**
     * Public URL for a panel-scoped call announcement.
     * The file is generated on first request and reused by hash. Polling does not synthesize.
     */
    public function contentType(): string
    {
        return $this->synthesizer->contentType();
    }

    public function isServableCachePath(string $path): bool
    {
        if ($path === '' || ! is_file($path)) {
            return false;
        }

        $real = realpath($path);
        $root = realpath($this->disk()->path($this->cacheDirectory()));
        if ($real === false || $root === false) {
            return false;
        }

        $real = str_replace('\\', '/', $real);
        $root = rtrim(str_replace('\\', '/', $root), '/').'/';

        return str_starts_with($real, $root);
    }

    public function audioUrlForCall(DisplayPanel $panel, TicketCall $call): ?string
    {
        if (! config('tv_tts.enabled', true)) {
            return null;
        }

        if (! $this->callBelongsToPanel($panel, $call)) {
            return null;
        }

        $token = $panel->public_code ?: $panel->public_token;
        if (! is_string($token) || $token === '') {
            return null;
        }

        // Relative: an absolute URL breaks when the TV reaches the host by IP or behind an HTTPS proxy (mixed content).
        return URL::route('tv.tts', [
            'publicToken' => $token,
            'ticketCall' => $call->id,
        ], absolute: false);
    }

    public function callBelongsToPanel(DisplayPanel $panel, TicketCall $call): bool
    {
        if ((int) $call->clinic_id !== (int) $panel->clinic_id
            || (int) $call->unit_id !== (int) $panel->unit_id) {
            return false;
        }

        $sectorIds = $panel->sectorIds();
        if ($sectorIds === []) {
            return true;
        }

        return $call->sector_id !== null && in_array((int) $call->sector_id, $sectorIds, true);
    }

    /**
     * Ensure a cached WAV exists for the announcement text. Returns absolute filesystem path or null.
     */
    public function ensureCachedWav(string $text): ?string
    {
        $text = trim($text);
        if ($text === '' || ! $this->isAvailable()) {
            return null;
        }

        $hash = $this->cacheKey($text);
        $relative = $this->relativePath($hash);
        $disk = $this->disk();

        if ($this->cachedFileIsUsable($disk, $relative)) {
            return $disk->path($relative);
        }

        $lock = Cache::lock('tv-tts:'.$hash, 15);

        try {
            $lock->block(10);

            if ($this->cachedFileIsUsable($disk, $relative)) {
                return $disk->path($relative);
            }

            $audio = $this->synthesizer->synthesizeWav($text);
            if ($audio === '' || strlen($audio) < 44) {
                Log::warning('tv.tts.synthesis_empty', [
                    'hash' => $hash,
                ]);

                return null;
            }

            $disk->makeDirectory($this->cacheDirectory());
            $disk->put($relative, $audio);
            $this->pruneCache($disk);

            return $disk->path($relative);
        } catch (\Throwable $exception) {
            Log::warning('tv.tts.cache_failed', [
                'exception' => $exception::class,
                'hash' => $hash,
            ]);

            return null;
        } finally {
            try {
                $lock->release();
            } catch (\Throwable) {
                // Lock may already have expired.
            }
        }
    }

    public function announcementForCall(TicketCall $call, bool $speakType, bool $speakDesk): string
    {
        return $this->voiceFormatter->announce($call, $speakType, $speakDesk);
    }

    public function cacheKey(string $text): string
    {
        return hash('sha256', implode('|', [
            'v2',
            $this->safeExtension(),
            (string) config('services.google_tts.language_code', 'pt-BR'),
            (string) config('services.google_tts.voice_name', ''),
            (string) config('services.google_tts.speaking_rate', '1.0'),
            (string) config('tv_tts.voice', 'pt'),
            (string) config('tv_tts.rate', 145),
            $text,
        ]));
    }

    private function relativePath(string $hash): string
    {
        if (! preg_match('/^[a-f0-9]{64}$/', $hash)) {
            $hash = hash('sha256', $hash);
        }

        return $this->cacheDirectory().'/'.$hash.'.'.$this->safeExtension();
    }

    private function safeExtension(): string
    {
        $extension = $this->synthesizer->fileExtension();

        return in_array($extension, ['wav', 'mp3'], true) ? $extension : 'wav';
    }

    private function cacheDirectory(): string
    {
        $dir = trim((string) config('tv_tts.cache_directory', 'tv-tts'), '/\\');
        $dir = str_replace('\\', '/', $dir);

        if ($dir === '' || str_contains($dir, '..') || str_contains($dir, ':')) {
            return 'tv-tts';
        }

        return $dir;
    }

    private function cachedFileIsUsable(Filesystem $disk, string $relative): bool
    {
        return $disk->exists($relative) && $disk->size($relative) > 44;
    }

    private function disk(): Filesystem
    {
        return Storage::disk((string) config('tv_tts.cache_disk', 'local'));
    }

    private function pruneCache(Filesystem $disk): void
    {
        $dir = $this->cacheDirectory();
        $maxFiles = max(50, (int) config('tv_tts.cache_max_files', 400));
        $maxAgeDays = max(1, (int) config('tv_tts.cache_max_age_days', 30));
        $cutoff = now()->subDays($maxAgeDays)->getTimestamp();

        try {
            $files = collect($disk->files($dir))
                ->filter(fn (string $path): bool => $this->isCachedAudioPath($path))
                ->map(function (string $path) use ($disk): array {
                    return [
                        'path' => $path,
                        'mtime' => $disk->lastModified($path) ?: 0,
                    ];
                })
                ->sortBy('mtime')
                ->values();
        } catch (\Throwable) {
            return;
        }

        foreach ($files as $file) {
            if ($file['mtime'] < $cutoff) {
                $disk->delete($file['path']);
            }
        }

        $remaining = collect($disk->files($dir))
            ->filter(fn (string $path): bool => $this->isCachedAudioPath($path))
            ->map(fn (string $path): array => [
                'path' => $path,
                'mtime' => $disk->lastModified($path) ?: 0,
            ])
            ->sortBy('mtime')
            ->values();

        $overflow = $remaining->count() - $maxFiles;
        if ($overflow <= 0) {
            return;
        }

        foreach ($remaining->take($overflow) as $file) {
            $disk->delete($file['path']);
        }
    }

    private function isCachedAudioPath(string $path): bool
    {
        return str_ends_with($path, '.wav') || str_ends_with($path, '.mp3');
    }
}
