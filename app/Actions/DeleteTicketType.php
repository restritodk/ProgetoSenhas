<?php

namespace App\Actions;

use App\Models\TicketType;
use App\Models\UnitTicketType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeleteTicketType
{
    /**
     * Permanently delete a ticket type only when it has no operational history.
     * Configuration links (unit_ticket_types) are removed with the type.
     *
     * @throws ValidationException
     */
    public function handle(User $actor, TicketType $ticketType): void
    {
        abort_if($ticketType->clinic_id !== $actor->clinic_id, 404);
        Gate::forUser($actor)->authorize('delete', $ticketType);

        if ($this->hasOperationalHistory($ticketType)) {
            throw ValidationException::withMessages([
                'ticket_type' => 'Não é possível excluir este tipo de senha. Ele já possui registros vinculados e precisa ser preservado para manter o histórico de atendimentos. Você pode desativá-lo para impedir novas emissões.',
            ]);
        }

        DB::transaction(function () use ($ticketType): void {
            UnitTicketType::query()
                ->where('clinic_id', $ticketType->clinic_id)
                ->where('ticket_type_id', $ticketType->id)
                ->delete();

            $ticketType->delete();
        });
    }

    public function hasOperationalHistory(TicketType $ticketType): bool
    {
        if ($ticketType->tickets()->exists()) {
            return true;
        }

        return DB::table('ticket_sequences')
            ->where('clinic_id', $ticketType->clinic_id)
            ->where('ticket_type_id', $ticketType->id)
            ->exists();
    }
}
