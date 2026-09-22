<?php

namespace App\Policies;

use App\Models\Clinic;
use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManageTickets($user);
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $this->sameActiveClinic($user, $ticket->clinic_id)
            && ($user->isAdministrator() || $user->canAccessAttendantPanel());
    }

    public function create(User $user): bool
    {
        return $this->canManageTickets($user);
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $this->sameActiveClinic($user, $ticket->clinic_id) && $user->isAdministrator();
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return false;
    }

    public function call(User $user): bool
    {
        return $this->canOperateTickets($user);
    }

    public function recall(User $user, Ticket $ticket): bool
    {
        return $this->canOperateTicket($user, $ticket);
    }

    public function startService(User $user, Ticket $ticket): bool
    {
        return $this->canOperateTicket($user, $ticket);
    }

    public function complete(User $user, Ticket $ticket): bool
    {
        return $this->canOperateTicket($user, $ticket);
    }

    public function markNoShow(User $user, Ticket $ticket): bool
    {
        return $this->canOperateTicket($user, $ticket);
    }

    private function canManageTickets(User $user): bool
    {
        return $user->active && $user->isAdministrator() && $this->hasActiveClinic($user);
    }

    private function canOperateTickets(User $user): bool
    {
        return $user->canAccessAttendantPanel() && $this->hasActiveClinic($user);
    }

    private function canOperateTicket(User $user, Ticket $ticket): bool
    {
        return $this->canOperateTickets($user) && $this->sameActiveClinic($user, $ticket->clinic_id);
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
