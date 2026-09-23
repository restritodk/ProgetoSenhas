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

        if ($user->hasPermission('clinic.view') || $user->hasPermission('units.manage')) {
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
        return $user->active
            && $this->hasActiveClinic($user)
            && $user->hasPermission('units.manage');
    }

    public function create(User $user): bool
    {
        return $this->manageAny($user);
    }

    public function update(User $user, Unit $unit): bool
    {
        return $this->belongsToActiveClinic($user, $unit) && $user->hasPermission('units.manage');
    }

    public function manageTicketTypes(User $user, Unit $unit): bool
    {
        return $this->belongsToActiveClinic($user, $unit) && $user->hasPermission('unit_ticket_types.manage');
    }

    public function manageQueuePolicy(User $user, Unit $unit): bool
    {
        return $this->belongsToActiveClinic($user, $unit) && $user->hasPermission('queue_policy.update');
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
