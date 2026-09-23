<?php

namespace App\Actions;

use App\Models\DisplayPanel;
use App\Models\DisplayPanelMedia;
use App\Models\MediaItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AttachMediaToDisplayPanel
{
    public function handle(User $actor, DisplayPanel $panel, MediaItem $mediaItem, ?int $durationSeconds = null): DisplayPanelMedia
    {
        abort_if($panel->clinic_id !== $actor->clinic_id, 404);
        Gate::forUser($actor)->authorize('update', $panel);

        if ($mediaItem->clinic_id !== $actor->clinic_id || $panel->clinic_id !== $mediaItem->clinic_id) {
            throw ValidationException::withMessages([
                'mediaItemId' => 'A mídia selecionada não pertence à sua clínica.',
            ]);
        }

        Gate::forUser($actor)->authorize('view', $mediaItem);

        if (! $mediaItem->active) {
            throw ValidationException::withMessages([
                'mediaItemId' => 'Selecione uma mídia ativa.',
            ]);
        }

        return DB::transaction(function () use ($actor, $panel, $mediaItem, $durationSeconds): DisplayPanelMedia {
            $exists = DisplayPanelMedia::query()
                ->where('display_panel_id', $panel->id)
                ->where('media_item_id', $mediaItem->id)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'mediaItemId' => 'Esta mídia já está na playlist deste painel.',
                ]);
            }

            $position = (int) DisplayPanelMedia::query()
                ->where('display_panel_id', $panel->id)
                ->max('position') + 1;

            $entry = new DisplayPanelMedia;
            $entry->forceFill([
                'clinic_id' => $actor->clinic_id,
                'display_panel_id' => $panel->id,
                'media_item_id' => $mediaItem->id,
                'position' => max(1, $position),
                'duration_seconds' => $mediaItem->isImage()
                    ? ($durationSeconds ?? $mediaItem->effectiveDurationSeconds())
                    : null,
                'active' => true,
            ])->save();

            return $entry->refresh();
        });
    }
}
