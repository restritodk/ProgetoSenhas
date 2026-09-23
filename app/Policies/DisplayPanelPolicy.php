<?php

namespace App\Policies;

use App\Models\Clinic;
use App\Models\DisplayPanel;
use App\Models\User;

class DisplayPanelPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->activeClinic($user) && $user->hasPermission('display_panels.view');
    }

    public function view(User $user, DisplayPanel $displayPanel): bool
    {
        return $this->sameActiveClinic($user, $displayPanel->clinic_id) && $user->hasPermission('display_panels.view');
    }

    public function create(User $user): bool
    {
        return $this->activeClinic($user) && $user->hasPermission('display_panels.create');
    }

    public function update(User $user, DisplayPanel $displayPanel): bool
    {
        return $this->sameActiveClinic($user, $displayPanel->clinic_id)
            && ($user->hasPermission('display_panels.update') || $user->hasPermission('display_panels.manage_status'));
    }

    public function delete(User $user, DisplayPanel $displayPanel): bool
    {
        return $this->sameActiveClinic($user, $displayPanel->clinic_id) && $user->hasPermission('display_panels.update');
    }

    public function regenerateToken(User $user, DisplayPanel $displayPanel): bool
    {
        return $this->sameActiveClinic($user, $displayPanel->clinic_id)
            && $user->hasPermission('display_panels.regenerate_token');
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
