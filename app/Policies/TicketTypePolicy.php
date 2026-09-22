<?php

namespace App\Policies;

use App\Models\Clinic;
use App\Models\TicketType;
use App\Models\User;

class TicketTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManageTicketTypes($user);
    }

    public function manageAny(User $user): bool
    {
        return $this->canManageTicketTypes($user);
    }

    public function view(User $user, TicketType $ticketType): bool
    {
        return $this->sameActiveClinic($user, $ticketType->clinic_id) && $user->isAdministrator();
    }

    public function create(User $user): bool
    {
        return $this->canManageTicketTypes($user);
    }

    public function update(User $user, TicketType $ticketType): bool
    {
        return $this->sameActiveClinic($user, $ticketType->clinic_id) && $user->isAdministrator();
    }

    public function delete(User $user, TicketType $ticketType): bool
    {
        return $this->update($user, $ticketType);
    }

    private function canManageTicketTypes(User $user): bool
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
