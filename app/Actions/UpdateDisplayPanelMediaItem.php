<?php

namespace App\Actions;

use App\Models\DisplayPanel;
use App\Models\DisplayPanelMedia;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateDisplayPanelMediaItem
{
    /**
     * @param  array{active?: bool, duration_seconds?: int|null}  $attributes
     */
    public function handle(User $actor, DisplayPanel $panel, DisplayPanelMedia $entry, array $attributes): DisplayPanelMedia
    {
        abort_if($panel->clinic_id !== $actor->clinic_id, 404);
        abort_if($entry->clinic_id !== $actor->clinic_id, 404);
        abort_if((int) $entry->display_panel_id !== (int) $panel->id, 404);
        Gate::forUser($actor)->authorize('update', $panel);

        $entry->loadMissing('mediaItem');

        if (array_key_exists('duration_seconds', $attributes) && $entry->mediaItem?->isImage()) {
            $duration = (int) $attributes['duration_seconds'];

            if ($duration < 3 || $duration > 300) {
                throw ValidationException::withMessages([
                    'durationSeconds' => 'A duração da imagem deve estar entre 3 e 300 segundos.',
                ]);
            }

            $entry->duration_seconds = $duration;
        }

        if (array_key_exists('active', $attributes)) {
            $entry->active = (bool) $attributes['active'];
        }

        $entry->save();

        return $entry->refresh();
    }
}
