<?php

namespace App\Services\TvTts;

use App\Contracts\TvSpeechSynthesizer;
use App\Models\DisplayPanel;
use App\Models\TicketCall;
use App\Services\TicketCallVoiceFormatter;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
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
     * Public URL for a panel-scoped call announcement audio (WAV).
     *
     * Emits the route when server TTS is enabled so the TV can fall back after
     * speechSynthesis fails. Availability of the binary is checked at serve time.
     */
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

        return URL::route('tv.tts', [
            'publicToken' => $token,
            'ticketCall' => $call->id,
        ], absolute: true);
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

        if ($disk->exists($relative) && $disk->size($relative) > 44) {
            return $disk->path($relative);
        }

        $lock = Cache::lock('tv-tts:'.$hash, 15);

        try {
            $lock->block(10);

            if ($disk->exists($relative) && $disk->size($relative) > 44) {
                return $disk->path($relative);
            }

            $wav = $this->synthesizer->synthesizeWav($text);
            if ($wav === '' || strlen($wav) < 44) {
                return null;
            }

            $disk->makeDirectory(trim((string) config('tv_tts.cache_directory'), '/'));
            $disk->put($relative, $wav);
            $this->pruneCache($disk);

            return $disk->path($relative);
        } catch (\Throwable) {
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
        $voice = (string) config('tv_tts.voice', 'pt');
        $rate = (int) config('tv_tts.rate', 145);

        return hash('sha256', 'v1|'.$voice.'|'.$rate.'|'.$text);
    }

    private function relativePath(string $hash): string
    {
        $dir = trim((string) config('tv_tts.cache_directory', 'tv-tts'), '/');

        return $dir.'/'.$hash.'.wav';
    }

    private function disk(): Filesystem
    {
        return Storage::disk((string) config('tv_tts.cache_disk', 'local'));
    }

    private function pruneCache(Filesystem $disk): void
    {
        $dir = trim((string) config('tv_tts.cache_directory', 'tv-tts'), '/');
        $maxFiles = max(50, (int) config('tv_tts.cache_max_files', 400));
        $maxAgeDays = max(1, (int) config('tv_tts.cache_max_age_days', 30));
        $cutoff = now()->subDays($maxAgeDays)->getTimestamp();

        try {
            $files = collect($disk->files($dir))
                ->filter(fn (string $path): bool => str_ends_with($path, '.wav'))
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
            ->filter(fn (string $path): bool => str_ends_with($path, '.wav'))
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
}
