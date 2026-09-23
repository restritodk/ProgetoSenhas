<?php

namespace App\Policies;

use App\Models\Clinic;
use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->activeClinic($user)
            && ($user->hasPermission('tickets.issue') || $user->hasPermission('attendant.access'));
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $this->sameActiveClinic($user, $ticket->clinic_id)
            && ($user->hasPermission('tickets.issue') || $user->hasPermission('attendant.access'));
    }

    public function create(User $user): bool
    {
        return $this->activeClinic($user) && $user->hasPermission('tickets.issue');
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $this->sameActiveClinic($user, $ticket->clinic_id) && $user->hasPermission('tickets.issue');
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return false;
    }

    public function call(User $user): bool
    {
        return $this->activeClinic($user) && $user->hasPermission('tickets.call');
    }

    public function recall(User $user, Ticket $ticket): bool
    {
        return $this->canOperateTicket($user, $ticket, 'tickets.recall');
    }

    public function startService(User $user, Ticket $ticket): bool
    {
        return $this->canOperateTicket($user, $ticket, 'tickets.start');
    }

    public function complete(User $user, Ticket $ticket): bool
    {
        return $this->canOperateTicket($user, $ticket, 'tickets.complete');
    }

    public function markNoShow(User $user, Ticket $ticket): bool
    {
        return $this->canOperateTicket($user, $ticket, 'tickets.no_show');
    }

    public function transfer(User $user, Ticket $ticket): bool
    {
        return $this->canOperateTicket($user, $ticket, 'tickets.transfer');
    }

    private function canOperateTicket(User $user, Ticket $ticket, string $permission): bool
    {
        return $this->sameActiveClinic($user, $ticket->clinic_id) && $user->hasPermission($permission);
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
