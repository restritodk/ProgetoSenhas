<?php

namespace App\Actions;

use App\Models\Desk;
use App\Models\DeskAssignment;
use App\Models\Ticket;
use App\Models\TicketCall;
use App\Models\User;
use App\Services\NextTicketSelector;
use App\Services\OperationalContext;
use App\Services\UnitQueuePolicyResolver;
use App\Support\DeskLease;
use App\TicketCallType;
use App\TicketStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CallNextTicket
{
    public function __construct(
        private OperationalContext $operationalContext,
        private NextTicketSelector $nextTicketSelector,
        private UnitQueuePolicyResolver $policyResolver,
    ) {}

    public function handle(User $actor): ?Ticket
    {
        Gate::forUser($actor)->authorize('call', Ticket::class);

        $unit = $this->operationalContext->activeUnit($actor, session());
        $desk = $this->operationalContext->activeDesk($actor, session());

        if ($unit === null || $desk === null) {
            throw ValidationException::withMessages([
                'desk' => 'Selecione uma unidade e uma mesa antes de chamar senhas.',
            ]);
        }

        $this->assertDeskAssignment($actor, $desk);

        return DB::transaction(function () use ($actor, $unit, $desk): ?Ticket {
            $busy = Ticket::query()
                ->where('clinic_id', $actor->clinic_id)
                ->where('current_desk_id', $desk->id)
                ->whereIn('status', [TicketStatus::CALLED, TicketStatus::IN_SERVICE])
                ->lockForUpdate()
                ->exists();

            if ($busy) {
                throw ValidationException::withMessages([
                    'desk' => 'Finalize ou registre o não comparecimento da senha atual antes de chamar outra.',
                ]);
            }

            $waiting = Ticket::query()
                ->with(['ticketType:id,clinic_id,name,prefix,priority,active'])
                ->where('clinic_id', $unit->clinic_id)
                ->where('unit_id', $unit->id)
                ->where('status', TicketStatus::WAITING)
                ->where(function ($query) use ($desk): void {
                    $query->whereNull('target_desk_id')
                        ->orWhere('target_desk_id', $desk->id);
                })
                ->when($desk->sector_id !== null, function ($query) use ($desk): void {
                    $query->where('sector_id', $desk->sector_id);
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $now = CarbonImmutable::now(config('app.timezone'));
            $policy = $this->policyResolver->lockForUnit($unit);

            if ($policy !== null) {
                $progress = $this->policyResolver->lockProgress($policy);
                $ticket = $this->nextTicketSelector->selectFromLocked($waiting, $policy, $progress, $now);
            } else {
                $ticket = $this->nextTicketSelector->rank($waiting, $now)->first();
                $progress = null;
            }

            if ($ticket === null) {
                return null;
            }

            if ($ticket->status !== TicketStatus::WAITING) {
                throw ValidationException::withMessages([
                    'ticket' => 'A senha selecionada não está mais aguardando.',
                ]);
            }

            $calledAt = now(config('app.timezone'));

            $ticket->forceFill([
                'status' => TicketStatus::CALLED,
                'current_desk_id' => $desk->id,
                'target_desk_id' => null,
                'called_by_user_id' => $actor->id,
                'called_at' => $calledAt,
            ])->save();

            $call = new TicketCall;
            $call->forceFill([
                'clinic_id' => $actor->clinic_id,
                'unit_id' => $unit->id,
                'sector_id' => $ticket->sector_id ?? $desk->sector_id,
                'ticket_id' => $ticket->id,
                'desk_id' => $desk->id,
                'called_by_user_id' => $actor->id,
                'call_type' => TicketCallType::INITIAL,
                'called_at' => $calledAt,
            ])->save();

            if ($policy !== null && $progress !== null) {
                $this->policyResolver->recordCall($policy, $progress, $ticket);
            }

            return $ticket->refresh()->load(['ticketType', 'currentDesk', 'calledBy']);
        });
    }

    private function assertDeskAssignment(User $actor, Desk $desk): void
    {
        $assignment = DeskAssignment::query()
            ->where('desk_id', $desk->id)
            ->where('user_id', $actor->id)
            ->first();

        if ($assignment === null || ! DeskLease::isActive($assignment)) {
            throw ValidationException::withMessages([
                'desk' => 'A mesa não está atribuída a você.',
            ]);
        }
    }
}
