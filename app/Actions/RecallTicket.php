<?php

namespace App\Actions;

use App\Models\Ticket;
use App\Models\TicketCall;
use App\Models\User;
use App\Services\OperationalContext;
use App\TicketCallType;
use App\TicketStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RecallTicket
{
    public function __construct(private OperationalContext $operationalContext) {}

    public function handle(User $actor, Ticket $ticket): Ticket
    {
        Gate::forUser($actor)->authorize('recall', $ticket);

        $desk = $this->operationalContext->activeDesk($actor, session());
        $unit = $this->operationalContext->activeUnit($actor, session());

        if ($desk === null || $unit === null) {
            throw ValidationException::withMessages([
                'desk' => 'Contexto operacional inválido para rechamada.',
            ]);
        }

        return DB::transaction(function () use ($actor, $ticket, $desk, $unit): Ticket {
            $locked = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            if (
                $locked->clinic_id !== $actor->clinic_id
                || $locked->unit_id !== $unit->id
                || (int) $locked->current_desk_id !== (int) $desk->id
                || $locked->status !== TicketStatus::CALLED
            ) {
                throw ValidationException::withMessages([
                    'ticket' => 'Somente a senha chamada nesta mesa pode ser rechamada.',
                ]);
            }

            $calledAt = now(config('app.timezone'));

            $locked->forceFill([
                'called_at' => $calledAt,
            ])->save();

            $call = new TicketCall;
            $call->forceFill([
                'clinic_id' => $actor->clinic_id,
                'unit_id' => $unit->id,
                'ticket_id' => $locked->id,
                'desk_id' => $desk->id,
                'called_by_user_id' => $actor->id,
                'call_type' => TicketCallType::RECALL,
                'called_at' => $calledAt,
            ])->save();

            return $locked->refresh()->load(['ticketType', 'currentDesk']);
        });
    }
}
