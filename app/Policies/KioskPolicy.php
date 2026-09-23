<?php

namespace App\Policies;

use App\Models\Clinic;
use App\Models\Kiosk;
use App\Models\User;

class KioskPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManageKiosks($user);
    }

    public function view(User $user, Kiosk $kiosk): bool
    {
        return $this->sameActiveClinic($user, $kiosk->clinic_id) && $user->isAdministrator();
    }

    public function create(User $user): bool
    {
        return $this->canManageKiosks($user);
    }

    public function update(User $user, Kiosk $kiosk): bool
    {
        return $this->sameActiveClinic($user, $kiosk->clinic_id) && $user->isAdministrator();
    }

    public function delete(User $user, Kiosk $kiosk): bool
    {
        return $this->update($user, $kiosk);
    }

    public function regenerateToken(User $user, Kiosk $kiosk): bool
    {
        return $this->update($user, $kiosk);
    }

    private function canManageKiosks(User $user): bool
    {
        return $user->active && $user->isAdministrator() && $this->hasActiveClinic($user);
    }

    private function sameActiveClinic(User $user, ?int $clinicId): bool
    {
        return $user->active
            && $user->clinic_id !== null
            && $user->clinic_id === $clinicId
            && $this->hasActiveClinic($user);
    }

    private function hasActiveClinic(User $user): bool
    {
        return $user->clinic_id !== null
            && Clinic::query()->whereKey($user->clinic_id)->where('active', true)->exists();
    }
}
