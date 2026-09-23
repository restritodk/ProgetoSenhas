<?php

namespace App\Policies;

use App\Models\UnitQueuePolicy;
use App\Models\User;

class UnitQueuePolicyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->active && $user->hasPermission('queue_policy.view');
    }

    public function view(User $user, UnitQueuePolicy $unitQueuePolicy): bool
    {
        return $user->active
            && $user->clinic_id === $unitQueuePolicy->clinic_id
            && $user->hasPermission('queue_policy.view');
    }

    public function update(User $user, UnitQueuePolicy $unitQueuePolicy): bool
    {
        return $user->active
            && $user->clinic_id === $unitQueuePolicy->clinic_id
            && $user->hasPermission('queue_policy.update');
    }
}
