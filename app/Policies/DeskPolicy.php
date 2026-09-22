<?php

namespace App\Policies;

use App\Models\Clinic;
use App\Models\Desk;
use App\Models\User;

class DeskPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManageDesks($user);
    }

    public function manageAny(User $user): bool
    {
        return $this->canManageDesks($user);
    }

    public function view(User $user, Desk $desk): bool
    {
        return $this->sameActiveClinic($user, $desk->clinic_id) && $user->isAdministrator();
    }

    public function create(User $user): bool
    {
        return $this->canManageDesks($user);
    }

    public function update(User $user, Desk $desk): bool
    {
        return $this->sameActiveClinic($user, $desk->clinic_id) && $user->isAdministrator();
    }

    public function delete(User $user, Desk $desk): bool
    {
        return $this->update($user, $desk);
    }

    private function canManageDesks(User $user): bool
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
