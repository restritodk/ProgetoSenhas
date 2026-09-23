<?php

namespace App\Policies;

use App\Models\Clinic;
use App\Models\DisplayPanel;
use App\Models\User;

class DisplayPanelPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManagePanels($user);
    }

    public function view(User $user, DisplayPanel $displayPanel): bool
    {
        return $this->sameActiveClinic($user, $displayPanel->clinic_id) && $user->isAdministrator();
    }

    public function create(User $user): bool
    {
        return $this->canManagePanels($user);
    }

    public function update(User $user, DisplayPanel $displayPanel): bool
    {
        return $this->sameActiveClinic($user, $displayPanel->clinic_id) && $user->isAdministrator();
    }

    public function delete(User $user, DisplayPanel $displayPanel): bool
    {
        return $this->update($user, $displayPanel);
    }

    public function regenerateToken(User $user, DisplayPanel $displayPanel): bool
    {
        return $this->update($user, $displayPanel);
    }

    private function canManagePanels(User $user): bool
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
