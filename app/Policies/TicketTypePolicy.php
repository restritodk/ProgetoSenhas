<?php

namespace App\Policies;

use App\Models\Clinic;
use App\Models\TicketType;
use App\Models\User;

class TicketTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->activeClinic($user) && $user->hasPermission('ticket_types.view');
    }

    public function manageAny(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, TicketType $ticketType): bool
    {
        return $this->sameActiveClinic($user, $ticketType->clinic_id) && $user->hasPermission('ticket_types.view');
    }

    public function create(User $user): bool
    {
        return $this->activeClinic($user) && $user->hasPermission('ticket_types.create');
    }

    public function update(User $user, TicketType $ticketType): bool
    {
        return $this->sameActiveClinic($user, $ticketType->clinic_id)
            && ($user->hasPermission('ticket_types.update') || $user->hasPermission('ticket_types.manage_status'));
    }

    public function delete(User $user, TicketType $ticketType): bool
    {
        return $this->sameActiveClinic($user, $ticketType->clinic_id) && $user->hasPermission('ticket_types.delete');
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
