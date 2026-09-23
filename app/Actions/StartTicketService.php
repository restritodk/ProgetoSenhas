<?php

namespace App\Actions;

use App\Models\Ticket;
use App\Models\User;
use App\Services\OperationalContext;
use App\TicketStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class StartTicketService
{
    public function __construct(private OperationalContext $operationalContext) {}

    public function handle(User $actor, Ticket $ticket): Ticket
    {
        Gate::forUser($actor)->authorize('startService', $ticket);

        $desk = $this->operationalContext->activeDesk($actor, session());

        if ($desk === null) {
            throw ValidationException::withMessages([
                'desk' => 'Contexto operacional inválido.',
            ]);
        }

        return DB::transaction(function () use ($actor, $ticket, $desk): Ticket {
            $locked = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            if (
                $locked->clinic_id !== $actor->clinic_id
                || (int) $locked->current_desk_id !== (int) $desk->id
                || $locked->status !== TicketStatus::CALLED
                || ! $locked->status->canTransitionTo(TicketStatus::IN_SERVICE)
            ) {
                throw ValidationException::withMessages([
                    'ticket' => 'Só é possível iniciar atendimento de uma senha chamada nesta mesa.',
                ]);
            }

            $locked->forceFill([
                'status' => TicketStatus::IN_SERVICE,
                'service_started_at' => now(config('app.timezone')),
                'started_by_user_id' => $actor->id,
            ])->save();

            return $locked->refresh()->load(['ticketType', 'currentDesk']);
        });
    }
}
