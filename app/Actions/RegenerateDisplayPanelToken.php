<?php

namespace App\Actions;

use App\Models\DisplayPanel;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class RegenerateDisplayPanelToken
{
    public function handle(User $actor, DisplayPanel $panel): DisplayPanel
    {
        abort_if($panel->clinic_id !== $actor->clinic_id, 404);
        Gate::forUser($actor)->authorize('regenerateToken', $panel);

        $panel->forceFill([
            'public_token' => DisplayPanel::generatePublicToken(),
        ])->save();

        return $panel->refresh();
    }
}
