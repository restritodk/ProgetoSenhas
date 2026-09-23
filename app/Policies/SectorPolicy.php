<?php

namespace App\Policies;

use App\Models\Clinic;
use App\Models\Sector;
use App\Models\User;

class SectorPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->activeClinic($user) && $user->hasPermission('sectors.view');
    }

    public function view(User $user, Sector $sector): bool
    {
        return $this->sameActiveClinic($user, $sector->clinic_id) && $user->hasPermission('sectors.view');
    }

    public function create(User $user): bool
    {
        return $this->activeClinic($user) && $user->hasPermission('sectors.create');
    }

    public function update(User $user, Sector $sector): bool
    {
        return $this->sameActiveClinic($user, $sector->clinic_id)
            && ($user->hasPermission('sectors.update') || $user->hasPermission('sectors.manage_status'));
    }

    public function delete(User $user, Sector $sector): bool
    {
        return $this->sameActiveClinic($user, $sector->clinic_id) && $user->hasPermission('sectors.update');
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
