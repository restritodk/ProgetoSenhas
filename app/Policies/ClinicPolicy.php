<?php

namespace App\Policies;

use App\Models\Clinic;
use App\Models\User;

class ClinicPolicy
{
    public function view(User $user, Clinic $clinic): bool
    {
        return $this->sameActiveClinic($user, $clinic) && $user->hasPermission('clinic.view');
    }

    public function update(User $user, Clinic $clinic): bool
    {
        return $this->sameActiveClinic($user, $clinic) && $user->hasPermission('clinic.update');
    }

    public function manage(User $user, Clinic $clinic): bool
    {
        return $this->sameActiveClinic($user, $clinic)
            && ($user->hasPermission('clinic.update') || $user->hasPermission('units.manage'));
    }

    private function sameActiveClinic(User $user, Clinic $clinic): bool
    {
        return $user->active && $clinic->active && $user->clinic_id === $clinic->id;
    }
}
