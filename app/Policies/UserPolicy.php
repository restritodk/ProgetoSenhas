<?php

namespace App\Policies;

use App\Models\Clinic;
use App\Models\User;

class UserPolicy
{
    public function view(User $user, User $model): bool
    {
        return $this->sameClinic($user, $model)
            && ($user->hasPermission('users.view') || $user->id === $model->id);
    }

    public function viewAny(User $user): bool
    {
        return $this->activeClinic($user) && $user->hasPermission('users.view');
    }

    public function create(User $user): bool
    {
        return $this->activeClinic($user) && $user->hasPermission('users.create');
    }

    public function update(User $user, User $model): bool
    {
        return $this->sameClinic($user, $model) && $user->hasPermission('users.update');
    }

    public function delete(User $user, User $model): bool
    {
        return $this->update($user, $model);
    }

    public function assignRole(User $user, User $model): bool
    {
        return $this->sameClinic($user, $model) && $user->hasPermission('users.assign_role');
    }

    public function manageStatus(User $user, User $model): bool
    {
        return $this->sameClinic($user, $model) && $user->hasPermission('users.manage_status');
    }

    private function sameClinic(User $user, User $model): bool
    {
        return $this->activeClinic($user)
            && $user->clinic_id === $model->clinic_id;
    }

    private function activeClinic(User $user): bool
    {
        return $user->active
            && $user->clinic_id !== null
            && Clinic::query()->whereKey($user->clinic_id)->where('active', true)->exists();
    }
}
