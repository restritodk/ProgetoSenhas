<?php

namespace App\Actions;

use App\Models\Kiosk;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class RegenerateKioskToken
{
    /**
     * Rotates the public short code and the long internal token.
     * Previous short and legacy URLs stop resolving.
     */
    public function handle(User $actor, Kiosk $kiosk): Kiosk
    {
        abort_if($kiosk->clinic_id !== $actor->clinic_id, 404);
        Gate::forUser($actor)->authorize('regenerateToken', $kiosk);

        $kiosk->forceFill([
            'public_token' => Kiosk::generatePublicToken(),
            'public_code' => Kiosk::generatePublicCode(),
        ])->save();

        return $kiosk->refresh();
    }
}
