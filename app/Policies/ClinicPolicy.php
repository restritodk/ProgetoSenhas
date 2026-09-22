<?php

namespace App\Policies;

use App\Models\Clinic;
use App\Models\User;

class ClinicPolicy
{
    public function view(User $user, Clinic $clinic): bool
    {
        return $this->sameActiveClinic($user, $clinic);
    }

    public function update(User $user, Clinic $clinic): bool
    {
        return $this->sameActiveClinic($user, $clinic) && $user->isAdministrator();
    }

    public function manage(User $user, Clinic $clinic): bool
    {
        return $this->update($user, $clinic);
    }

    private function sameActiveClinic(User $user, Clinic $clinic): bool
    {
        return $user->active && $clinic->active && $user->clinic_id === $clinic->id;
    }
}
