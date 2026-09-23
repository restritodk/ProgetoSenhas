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

class CreateMediaItem
{
    /**
     * @param  array{
     *     name: string,
     *     type: string,
     *     active: bool,
     *     duration_seconds?: int|null,
     *     play_with_audio?: bool,
     *     youtube_url?: string|null
     * }  $attributes
     */
    public function handle(User $actor, array $attributes, ?UploadedFile $file = null): MediaItem
    {
        Gate::forUser($actor)->authorize('create', MediaItem::class);
        abort_if($actor->clinic_id === null, 404);

        $type = MediaType::tryFrom($attributes['type']);

        if ($type === null) {
            throw ValidationException::withMessages([
                'type' => 'Tipo de mídia inválido.',
            ]);
        }

        if ($type === MediaType::YOUTUBE) {
            return $this->createYouTube($actor, $attributes);
        }

        if ($file === null) {
            throw ValidationException::withMessages([
                'file' => 'Selecione um arquivo.',
            ]);
        }

        if (! $type->isUploadable()) {
            throw ValidationException::withMessages([
                'type' => 'Tipo de mídia inválido para upload.',
            ]);
        }

        return $this->createUpload($actor, $attributes, $type, $file);
    }

    /**
     * @param  array{name: string, active: bool, play_with_audio?: bool, youtube_url?: string|null}  $attributes
     */
    private function createYouTube(User $actor, array $attributes): MediaItem
    {
        $parsed = YouTubeUrl::parse((string) ($attributes['youtube_url'] ?? ''));

        return DB::transaction(function () use ($actor, $attributes, $parsed): MediaItem {
            $item = new MediaItem;
            $item->forceFill([
                'clinic_id' => $actor->clinic_id,
                'name' => $attributes['name'],
                'type' => MediaType::YOUTUBE,
                'disk' => MediaItem::DISK,
                'file_path' => null,
                'external_url' => $parsed['normalized_url'],
                'external_id' => $parsed['video_id'],
                'mime_type' => null,
                'file_size' => null,
                'duration_seconds' => null,
                'play_with_audio' => (bool) ($attributes['play_with_audio'] ?? false),
                'active' => $attributes['active'],
                'created_by_user_id' => $actor->id,
            ])->save();

            return $item->refresh();
        });
    }

    /**
     * @param  array{name: string, active: bool, duration_seconds?: int|null, play_with_audio?: bool}  $attributes
     */
    private function createUpload(User $actor, array $attributes, MediaType $type, UploadedFile $file): MediaItem
    {
        $this->assertSafeUpload($file, $type);

        $directory = 'tv-media/'.$actor->clinic_id;
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $filename = Str::lower(Str::random(40)).'.'.$extension;
        $path = null;

        try {
            $path = $file->storeAs($directory, $filename, MediaItem::DISK);

            if ($path === false || $path === '') {
                throw ValidationException::withMessages([
                    'file' => 'Não foi possível armazenar o arquivo.',
                ]);
            }

            return DB::transaction(function () use ($actor, $attributes, $type, $file, $path): MediaItem {
                $item = new MediaItem;
                $item->forceFill([
                    'clinic_id' => $actor->clinic_id,
                    'name' => $attributes['name'],
                    'type' => $type,
                    'disk' => MediaItem::DISK,
                    'file_path' => $path,
                    'external_url' => null,
                    'external_id' => null,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'duration_seconds' => $type === MediaType::IMAGE
                        ? (int) ($attributes['duration_seconds'] ?? MediaItem::DEFAULT_IMAGE_DURATION_SECONDS)
                        : null,
                    'play_with_audio' => $type === MediaType::VIDEO
                        ? (bool) ($attributes['play_with_audio'] ?? false)
                        : false,
                    'active' => $attributes['active'],
                    'created_by_user_id' => $actor->id,
                ])->save();

                return $item->refresh();
            });
        } catch (\Throwable $exception) {
            if (is_string($path) && $path !== '') {
                Storage::disk(MediaItem::DISK)->delete($path);
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
