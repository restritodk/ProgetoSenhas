<?php

namespace App\Actions;

use App\Models\DisplayPanel;
use App\Models\DisplayPanelMedia;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReorderDisplayPanelMedia
{
    public function handle(User $actor, DisplayPanel $panel, DisplayPanelMedia $entry, string $direction): void
    {
        abort_if($panel->clinic_id !== $actor->clinic_id, 404);
        abort_if($entry->clinic_id !== $actor->clinic_id, 404);
        abort_if((int) $entry->display_panel_id !== (int) $panel->id, 404);
        Gate::forUser($actor)->authorize('update', $panel);

        if (! in_array($direction, ['up', 'down'], true)) {
            throw ValidationException::withMessages([
                'direction' => 'Direção de ordenação inválida.',
            ]);
        }

        DB::transaction(function () use ($panel, $entry, $direction): void {
            $entries = DisplayPanelMedia::query()
                ->where('display_panel_id', $panel->id)
                ->orderBy('position')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->values();

            $index = $entries->search(fn (DisplayPanelMedia $item): bool => $item->id === $entry->id);

            if ($index === false) {
                abort(404);
            }

            $swapWith = $direction === 'up' ? $index - 1 : $index + 1;

            if ($swapWith < 0 || $swapWith >= $entries->count()) {
                return;
            }

            $current = $entries[$index];
            $neighbor = $entries[$swapWith];
            $currentPosition = $current->position;
            $neighborPosition = $neighbor->position;

            $current->forceFill(['position' => $neighborPosition])->save();
            $neighbor->forceFill(['position' => $currentPosition])->save();
        });
    }
}
