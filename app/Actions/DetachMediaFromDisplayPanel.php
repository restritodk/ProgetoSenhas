<?php

namespace App\Actions;

use App\Models\DisplayPanel;
use App\Models\DisplayPanelMedia;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DetachMediaFromDisplayPanel
{
    public function handle(User $actor, DisplayPanel $panel, DisplayPanelMedia $entry): void
    {
        abort_if($panel->clinic_id !== $actor->clinic_id, 404);
        abort_if($entry->clinic_id !== $actor->clinic_id, 404);
        abort_if((int) $entry->display_panel_id !== (int) $panel->id, 404);
        Gate::forUser($actor)->authorize('update', $panel);

        DB::transaction(function () use ($panel, $entry): void {
            $entry->delete();
            $this->normalizePositions($panel);
        });
    }

    private function normalizePositions(DisplayPanel $panel): void
    {
        $entries = DisplayPanelMedia::query()
            ->where('display_panel_id', $panel->id)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $position = 1;

        foreach ($entries as $item) {
            $item->forceFill(['position' => $position])->save();
            $position++;
        }
    }
}
