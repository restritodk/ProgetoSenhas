<?php

namespace App\Policies;

use App\Models\Clinic;
use App\Models\Desk;
use App\Models\User;

class DeskPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->activeClinic($user) && $user->hasPermission('desks.view');
    }

    public function manageAny(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, Desk $desk): bool
    {
        return $this->sameActiveClinic($user, $desk->clinic_id) && $user->hasPermission('desks.view');
    }

    public function create(User $user): bool
    {
        return $this->activeClinic($user) && $user->hasPermission('desks.create');
    }

    public function update(User $user, Desk $desk): bool
    {
        return $this->sameActiveClinic($user, $desk->clinic_id)
            && ($user->hasPermission('desks.update') || $user->hasPermission('desks.manage_status'));
    }

    public function delete(User $user, Desk $desk): bool
    {
        return $this->sameActiveClinic($user, $desk->clinic_id) && $user->hasPermission('desks.update');
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
