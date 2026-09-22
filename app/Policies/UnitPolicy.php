<?php

namespace App\Policies;

use App\Models\Clinic;
use App\Models\Unit;
use App\Models\User;

class UnitPolicy
{
    public function view(User $user, Unit $unit): bool
    {
        if (! $this->belongsToActiveClinic($user, $unit)) {
            return false;
        }

        if ($user->isAdministrator()) {
            return true;
        }

        return $unit->active && $user->hasAccessToUnit($unit);
    }

    public function viewAny(User $user): bool
    {
        return $user->active && $this->hasActiveClinic($user);
    }

    public function manageAny(User $user): bool
    {
        return $user->active && $this->hasActiveClinic($user) && $user->isAdministrator();
    }

    public function create(User $user): bool
    {
        return $this->manageAny($user);
    }

    public function update(User $user, Unit $unit): bool
    {
        return $this->belongsToActiveClinic($user, $unit) && $user->isAdministrator();
    }

    public function delete(User $user, Unit $unit): bool
    {
        return $this->update($user, $unit);
    }

    private function belongsToActiveClinic(User $user, Unit $unit): bool
    {
        return $user->active && $user->clinic_id === $unit->clinic_id && $this->hasActiveClinic($user);
    }

    private function hasActiveClinic(User $user): bool
    {
        return $user->clinic_id !== null
            && Clinic::query()->whereKey($user->clinic_id)->where('active', true)->exists();
    }
}
