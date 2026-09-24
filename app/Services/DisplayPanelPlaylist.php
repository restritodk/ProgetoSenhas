<?php

namespace App\Services;

use App\MediaType;
use App\Models\DisplayPanel;
use App\Models\DisplayPanelMedia;
use App\Models\MediaItem;
use App\Support\YouTubeUrl;

class DisplayPanelPlaylist
{
    /**
     * Lean playlist payload for the TV media player. Separate from operational call feed.
     *
     * @return list<array{
     *     id: int,
     *     media_item_id: int,
     *     type: string,
     *     name: string,
     *     url: string|null,
     *     video_id: string|null,
     *     duration_seconds: int,
     *     play_with_audio: bool,
     *     mime_type: string|null
     * }>
     */
    public function forPanel(DisplayPanel $panel): array
    {
        if (! $panel->isOperationallyAvailable()) {
            return [];
        }

        $origin = '';

        return DisplayPanelMedia::query()
            ->with(['mediaItem:id,clinic_id,name,type,disk,file_path,external_url,external_id,mime_type,duration_seconds,play_with_audio,active'])
            ->where('clinic_id', $panel->clinic_id)
            ->where('display_panel_id', $panel->id)
            ->where('active', true)
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->filter(function (DisplayPanelMedia $entry): bool {
                $media = $entry->mediaItem;

                if ($media === null || ! $media->active || $media->clinic_id !== $entry->clinic_id) {
                    return false;
                }

                if ($media->isYouTube()) {
                    return $media->youtubeVideoId() !== null;
                }

                return $media->type->isUploadable()
                    && is_string($media->file_path)
                    && $media->file_path !== '';
            })
            ->map(function (DisplayPanelMedia $entry) use ($origin): array {
                /** @var MediaItem $media */
                $media = $entry->mediaItem;

                if ($media->isYouTube()) {
                    $videoId = $media->youtubeVideoId();

                    return [
                        'id' => $entry->id,
                        'media_item_id' => $media->id,
                        'type' => MediaType::YOUTUBE->value,
                        'name' => $media->name,
                        // Embed URL is informational; the player mounts via validated video_id only.
                        'url' => $videoId !== null ? YouTubeUrl::embedUrl($videoId, $origin) : null,
                        'video_id' => $videoId,
                        'duration_seconds' => 0,
                        'play_with_audio' => (bool) $media->playsWithAudio(),
                        'mime_type' => null,
                    ];
                }

                return [
                    'id' => $entry->id,
                    'media_item_id' => $media->id,
                    'type' => $media->type->value,
                    'name' => $media->name,
                    'url' => $media->publicUrl(),
                    'video_id' => null,
                    'duration_seconds' => $media->isImage()
                        ? $media->effectiveDurationSeconds(
                            $entry->duration_seconds !== null && $entry->duration_seconds > 0
                                ? (int) $entry->duration_seconds
                                : null
                        )
                        : 0,
                    'play_with_audio' => (bool) $media->playsWithAudio(),
                    'mime_type' => $media->mime_type,
                ];
            })
            ->values()
            ->all();
    }
}
