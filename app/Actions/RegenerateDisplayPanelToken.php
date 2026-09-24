<?php

namespace App\Actions;

use App\Models\DisplayPanel;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class RegenerateDisplayPanelToken
{
    /**
     * Rotates the public short code and the long internal token.
     * Previous short and legacy URLs stop resolving.
     */
    public function handle(User $actor, DisplayPanel $panel): DisplayPanel
    {
        abort_if($panel->clinic_id !== $actor->clinic_id, 404);
        Gate::forUser($actor)->authorize('regenerateToken', $panel);

        $panel->forceFill([
            'public_token' => DisplayPanel::generatePublicToken(),
            'public_code' => DisplayPanel::generatePublicCode(),
        ])->save();

        return $panel->refresh();
    }
}
