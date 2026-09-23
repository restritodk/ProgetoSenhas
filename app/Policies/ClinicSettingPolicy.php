<?php

namespace App\Policies;

use App\Models\User;

class ClinicSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->active
            && $user->clinic !== null
            && $user->clinic->active
            && $user->hasPermission('settings.view');
    }

    public function update(User $user): bool
    {
        return $user->active
            && $user->clinic !== null
            && $user->clinic->active
            && $user->hasPermission('settings.update');
    }

    public function manage(User $user): bool
    {
        return $this->update($user);
    }
}
