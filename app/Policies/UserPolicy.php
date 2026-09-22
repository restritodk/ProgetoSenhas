<?php

namespace App\Policies;

use App\Models\Clinic;
use App\Models\User;

class UserPolicy
{
    public function view(User $user, User $model): bool
    {
        return $this->sameClinic($user, $model) && ($user->isAdministrator() || $user->id === $model->id);
    }

    public function viewAny(User $user): bool
    {
        return $this->canManageUsers($user);
    }

    public function create(User $user): bool
    {
        return $this->canManageUsers($user);
    }

    public function update(User $user, User $model): bool
    {
        return $this->sameClinic($user, $model) && $user->isAdministrator();
    }

    public function delete(User $user, User $model): bool
    {
        return $this->update($user, $model);
    }

    public function assignRole(User $user, User $model): bool
    {
        return $this->update($user, $model);
    }

    private function canManageUsers(User $user): bool
    {
        return $user->active && $user->isAdministrator() && $this->hasActiveClinic($user);
    }

    private function sameClinic(User $user, User $model): bool
    {
        return $user->active
            && $user->clinic_id !== null
            && $user->clinic_id === $model->clinic_id
            && $this->hasActiveClinic($user);
    }

    private function hasActiveClinic(User $user): bool
    {
        return $user->clinic_id !== null
            && Clinic::query()->whereKey($user->clinic_id)->where('active', true)->exists();
    }
}
