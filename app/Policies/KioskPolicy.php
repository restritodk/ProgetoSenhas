<?php

namespace App\Policies;

use App\Models\Clinic;
use App\Models\Kiosk;
use App\Models\User;

class KioskPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->activeClinic($user) && $user->hasPermission('kiosks.view');
    }

    public function view(User $user, Kiosk $kiosk): bool
    {
        return $this->sameActiveClinic($user, $kiosk->clinic_id) && $user->hasPermission('kiosks.view');
    }

    public function create(User $user): bool
    {
        return $this->activeClinic($user) && $user->hasPermission('kiosks.create');
    }

    public function update(User $user, Kiosk $kiosk): bool
    {
        return $this->sameActiveClinic($user, $kiosk->clinic_id)
            && ($user->hasPermission('kiosks.update') || $user->hasPermission('kiosks.manage_status'));
    }

    public function delete(User $user, Kiosk $kiosk): bool
    {
        return $this->sameActiveClinic($user, $kiosk->clinic_id) && $user->hasPermission('kiosks.update');
    }

    public function regenerateToken(User $user, Kiosk $kiosk): bool
    {
        return $this->sameActiveClinic($user, $kiosk->clinic_id) && $user->hasPermission('kiosks.regenerate_token');
    }

    private function sameActiveClinic(User $user, ?int $clinicId): bool
    {
        return $this->activeClinic($user) && $user->clinic_id === $clinicId;
    }

    private function activeClinic(User $user): bool
    {
        return $user->active
            && $user->clinic_id !== null
            && Clinic::query()->whereKey($user->clinic_id)->where('active', true)->exists();
    }
}
