<?php

namespace App\Models;

use App\MediaType;
use App\Support\YouTubeUrl;
use Database\Factories\MediaItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class MediaItem extends Model
{
    /** @use HasFactory<MediaItemFactory> */
    use HasFactory;

    public const DISK = 'public';

    public const IMAGE_MAX_KILOBYTES = 10240;

    public const VIDEO_MAX_KILOBYTES = 102400;

    public const DEFAULT_IMAGE_DURATION_SECONDS = 10;

    protected $fillable = [
        'name',
        'active',
        'duration_seconds',
        'play_with_audio',
    ];

    protected function casts(): array
    {
        return [
            'type' => MediaType::class,
            'active' => 'boolean',
            'play_with_audio' => 'boolean',
            'file_size' => 'integer',
            'duration_seconds' => 'integer',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function displayPanels(): BelongsToMany
    {
        return $this->belongsToMany(DisplayPanel::class, 'display_panel_media')
            ->withPivot(['id', 'clinic_id', 'position', 'duration_seconds', 'active'])
            ->withTimestamps();
    }

    public function publicUrl(): ?string
    {
        if ($this->file_path === null || $this->file_path === '') {
            return null;
        }

        $url = Storage::disk($this->disk ?: self::DISK)->url($this->file_path);

        // Keep media URLs host-agnostic (localhost vs 127.0.0.1) for the public TV.
        if (is_string($url) && (str_starts_with($url, 'http://') || str_starts_with($url, 'https://'))) {
            $path = parse_url($url, PHP_URL_PATH);

            return is_string($path) && $path !== '' ? $path : $url;
        }

        return $url;
    }

    public function isImage(): bool
    {
        return $this->type === MediaType::IMAGE;
    }

    public function isVideo(): bool
    {
        return $this->type === MediaType::VIDEO;
    }

    public function isYouTube(): bool
    {
        return $this->type === MediaType::YOUTUBE;
    }

    public function supportsAudioSetting(): bool
    {
        return $this->isVideo() || $this->isYouTube();
    }

    public function playsWithAudio(): bool
    {
        return $this->supportsAudioSetting() && (bool) $this->play_with_audio;
    }

    public function youtubeVideoId(): ?string
    {
        if (! $this->isYouTube()) {
            return null;
        }

        $id = is_string($this->external_id) ? $this->external_id : '';

        return YouTubeUrl::isValidVideoId($id) ? $id : null;
    }

    public function youtubeThumbnailUrl(): ?string
    {
        $id = $this->youtubeVideoId();

        return $id !== null ? YouTubeUrl::thumbnailUrl($id) : null;
    }

    public function youtubeEmbedUrl(string $origin = ''): ?string
    {
        $id = $this->youtubeVideoId();

        return $id !== null ? YouTubeUrl::embedUrl($id, $origin) : null;
    }

    public function previewUrl(): ?string
    {
        if ($this->isYouTube()) {
            return $this->youtubeThumbnailUrl();
        }

        return $this->publicUrl();
    }

    /**
     * Effective image duration for TV playback.
     * Source of truth on air: display_panel_media.duration_seconds (playlist override).
     * media_items.duration_seconds is only the default seed when attaching new playlist rows.
     */
    public function effectiveDurationSeconds(?int $playlistOverride = null): int
    {
        if (! $this->isImage()) {
            return 0;
        }

        if ($playlistOverride !== null && $playlistOverride > 0) {
            return $playlistOverride;
        }

        if ($this->duration_seconds !== null && $this->duration_seconds > 0) {
            return $this->duration_seconds;
        }

        return self::DEFAULT_IMAGE_DURATION_SECONDS;
    }
}
