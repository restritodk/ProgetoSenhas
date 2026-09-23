<?php

namespace App\Actions;

use App\MediaType;
use App\Models\MediaItem;
use App\Models\User;
use App\Support\YouTubeUrl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UpdateMediaItem
{
    /**
     * Type changes during edit are blocked to avoid orphaned local files.
     * Register a new media item to switch origin.
     *
     * @param  array{
     *     name: string,
     *     active: bool,
     *     duration_seconds?: int|null,
     *     play_with_audio?: bool,
     *     youtube_url?: string|null
     * }  $attributes
     */
    public function handle(User $actor, MediaItem $mediaItem, array $attributes, ?UploadedFile $file = null): MediaItem
    {
        abort_if($mediaItem->clinic_id !== $actor->clinic_id, 404);
        Gate::forUser($actor)->authorize('update', $mediaItem);

        if ($mediaItem->isYouTube()) {
            return $this->updateYouTube($mediaItem, $attributes);
        }

        if ($file !== null) {
            return $this->replaceUpload($mediaItem, $attributes, $file);
        }

        $duration = $mediaItem->type === MediaType::IMAGE
            ? (int) ($attributes['duration_seconds'] ?? $mediaItem->duration_seconds ?? MediaItem::DEFAULT_IMAGE_DURATION_SECONDS)
            : null;

        $mediaItem->forceFill([
            'name' => $attributes['name'],
            'active' => $attributes['active'],
            'duration_seconds' => $duration,
            'play_with_audio' => $this->resolvePlayWithAudio($mediaItem, $attributes),
        ])->save();

        return $mediaItem->refresh();
    }

    /**
     * @param  array{name: string, active: bool, play_with_audio?: bool, youtube_url?: string|null}  $attributes
     */
    private function updateYouTube(MediaItem $mediaItem, array $attributes): MediaItem
    {
        $urlInput = trim((string) ($attributes['youtube_url'] ?? ''));

        if ($urlInput === '') {
            $urlInput = (string) ($mediaItem->external_url ?? '');
        }

        $parsed = YouTubeUrl::parse($urlInput);

        $mediaItem->forceFill([
            'name' => $attributes['name'],
            'active' => $attributes['active'],
            'duration_seconds' => null,
            'play_with_audio' => $this->resolvePlayWithAudio($mediaItem, $attributes),
            'external_url' => $parsed['normalized_url'],
            'external_id' => $parsed['video_id'],
            'file_path' => null,
            'mime_type' => null,
            'file_size' => null,
        ])->save();

        return $mediaItem->refresh();
    }

    /**
     * @param  array{play_with_audio?: bool}  $attributes
     */
    private function resolvePlayWithAudio(MediaItem $mediaItem, array $attributes): bool
    {
        if (! $mediaItem->supportsAudioSetting()) {
            return false;
        }

        if (array_key_exists('play_with_audio', $attributes)) {
            return (bool) $attributes['play_with_audio'];
        }

        return (bool) $mediaItem->play_with_audio;
    }

    /**
     * @param  array{name: string, active: bool, duration_seconds?: int|null, play_with_audio?: bool}  $attributes
     */
    private function replaceUpload(MediaItem $mediaItem, array $attributes, UploadedFile $file): MediaItem
    {
        $type = $mediaItem->type;

        if (! $type->isUploadable()) {
            throw ValidationException::withMessages([
                'file' => 'Esta mídia não aceita arquivo local.',
            ]);
        }

        $this->assertSafeUpload($file, $type);

        $directory = 'tv-media/'.$mediaItem->clinic_id;
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $filename = Str::lower(Str::random(40)).'.'.$extension;
        $newPath = null;
        $oldPath = $mediaItem->file_path;
        $oldDisk = $mediaItem->disk ?: MediaItem::DISK;

        try {
            $newPath = $file->storeAs($directory, $filename, MediaItem::DISK);

            if ($newPath === false || $newPath === '') {
                throw ValidationException::withMessages([
                    'file' => 'Não foi possível armazenar o arquivo.',
                ]);
            }

            $item = DB::transaction(function () use ($mediaItem, $attributes, $type, $file, $newPath): MediaItem {
                $mediaItem->forceFill([
                    'name' => $attributes['name'],
                    'active' => $attributes['active'],
                    'disk' => MediaItem::DISK,
                    'file_path' => $newPath,
                    'external_url' => null,
                    'external_id' => null,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'duration_seconds' => $type === MediaType::IMAGE
                        ? (int) ($attributes['duration_seconds'] ?? $mediaItem->duration_seconds ?? MediaItem::DEFAULT_IMAGE_DURATION_SECONDS)
                        : null,
                    'play_with_audio' => $this->resolvePlayWithAudio($mediaItem, $attributes),
                ])->save();

                return $mediaItem->refresh();
            });

            if (is_string($oldPath) && $oldPath !== '' && $oldPath !== $newPath && Storage::disk($oldDisk)->exists($oldPath)) {
                Storage::disk($oldDisk)->delete($oldPath);
            }

            return $item;
        } catch (\Throwable $exception) {
            if (is_string($newPath) && $newPath !== '') {
                Storage::disk(MediaItem::DISK)->delete($newPath);
            }

            throw $exception;
        }
    }

    private function assertSafeUpload(UploadedFile $file, MediaType $type): void
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'file' => 'O upload falhou. Verifique o tamanho do arquivo e tente novamente.',
            ]);
        }

        $mime = (string) $file->getMimeType();
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension()));

        if ($type === MediaType::IMAGE) {
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            $maxKb = MediaItem::IMAGE_MAX_KILOBYTES;
        } else {
            $allowedMimes = ['video/mp4', 'video/quicktime'];
            $allowedExtensions = ['mp4'];
            $maxKb = MediaItem::VIDEO_MAX_KILOBYTES;
        }

        if (! in_array($mime, $allowedMimes, true) || ! in_array($extension, $allowedExtensions, true)) {
            throw ValidationException::withMessages([
                'file' => $type === MediaType::IMAGE
                    ? 'Envie uma imagem JPEG, PNG ou WebP válida.'
                    : 'Envie um vídeo MP4 válido.',
            ]);
        }

        if (in_array($extension, ['svg', 'php', 'html', 'htm', 'js', 'exe', 'sh', 'bat'], true)) {
            throw ValidationException::withMessages([
                'file' => 'Este tipo de arquivo não é permitido.',
            ]);
        }

        if (($file->getSize() / 1024) > $maxKb) {
            throw ValidationException::withMessages([
                'file' => $type === MediaType::IMAGE
                    ? 'A imagem deve ter no máximo 10 MB.'
                    : 'O vídeo deve ter no máximo 100 MB.',
            ]);
        }
    }
}
