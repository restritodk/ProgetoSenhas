<?php

namespace App\Actions;

use App\Models\Kiosk;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class RegenerateKioskToken
{
    public function handle(User $actor, Kiosk $kiosk): Kiosk
    {
        abort_if($kiosk->clinic_id !== $actor->clinic_id, 404);
        Gate::forUser($actor)->authorize('regenerateToken', $kiosk);

        $kiosk->forceFill([
            'public_token' => Kiosk::generatePublicToken(),
        ])->save();

        return $kiosk->refresh();
    }
}
