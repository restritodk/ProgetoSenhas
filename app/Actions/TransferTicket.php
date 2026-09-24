<?php

namespace App\Actions;

use App\Models\Desk;
use App\Models\DeskAssignment;
use App\Models\Ticket;
use App\Models\TicketTransfer;
use App\Models\User;
use App\Services\OperationalContext;
use App\Support\DeskLease;
use App\TicketStatus;
use App\TicketTransferType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TransferTicket
{
    public function __construct(private OperationalContext $operationalContext) {}

    public function handle(
        User $actor,
        Ticket $ticket,
        TicketTransferType $transferType,
        ?int $toDeskId = null,
        ?string $reason = null,
    ): Ticket {
        Gate::forUser($actor)->authorize('transfer', $ticket);

        $unit = $this->operationalContext->activeUnit($actor, session());
        $desk = $this->operationalContext->activeDesk($actor, session());

        if ($unit === null || $desk === null) {
            throw ValidationException::withMessages([
                'desk' => 'Selecione uma unidade e uma mesa antes de transferir senhas.',
            ]);
        }

        if (! $unit->active || ! $actor->clinic?->active) {
            throw ValidationException::withMessages([
                'unit' => 'A clínica ou unidade operacional está inativa.',
            ]);
        }

        $this->assertDeskAssignment($actor, $desk);

        $normalizedReason = $this->normalizeReason($reason);

        return DB::transaction(function () use ($actor, $ticket, $transferType, $toDeskId, $normalizedReason, $unit, $desk): Ticket {
            $locked = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            if (
                $locked->clinic_id !== $actor->clinic_id
                || (int) $locked->unit_id !== (int) $unit->id
                || (int) $locked->current_desk_id !== (int) $desk->id
                || ! in_array($locked->status, [TicketStatus::CALLED, TicketStatus::IN_SERVICE], true)
                || ! $locked->status->canTransitionTo(TicketStatus::WAITING)
            ) {
                throw ValidationException::withMessages([
                    'ticket' => 'Só é possível transferir a senha atual desta mesa (chamada ou em atendimento).',
                ]);
            }

            $toDesk = null;

            if ($transferType === TicketTransferType::DESK) {
                $toDesk = $this->resolveDestinationDesk($actor, $unit->id, $desk->id, $toDeskId);
            } elseif ($toDeskId !== null) {
                throw ValidationException::withMessages([
                    'toDeskId' => 'Transferência para fila geral não aceita mesa de destino.',
                ]);
            }

            $transferredAt = CarbonImmutable::now(config('app.timezone'));

            $transfer = new TicketTransfer;
            $transfer->forceFill([
                'clinic_id' => $actor->clinic_id,
                'unit_id' => $unit->id,
                'ticket_id' => $locked->id,
                'from_desk_id' => $desk->id,
                'to_desk_id' => $toDesk?->id,
                'transferred_by_user_id' => $actor->id,
                'transfer_type' => $transferType,
                'reason' => $normalizedReason,
                'transferred_at' => $transferredAt,
            ])->save();

            $locked->forceFill([
                'status' => TicketStatus::WAITING,
                'current_desk_id' => null,
                'called_by_user_id' => null,
                'started_by_user_id' => null,
                'called_at' => null,
                'service_started_at' => null,
                'queued_at' => $transferredAt,
                'target_desk_id' => $toDesk?->id,
                // Desk transfer may move the ticket to another sector of the same unit.
                'sector_id' => $toDesk?->sector_id ?? $locked->sector_id,
            ])->save();

            return $locked->refresh()->load(['ticketType', 'targetDesk', 'currentDesk']);
        });
    }

    private function resolveDestinationDesk(User $actor, int $unitId, int $fromDeskId, ?int $toDeskId): Desk
    {
        if ($toDeskId === null) {
            throw ValidationException::withMessages([
                'toDeskId' => 'Selecione a mesa de destino.',
            ]);
        }

        if ((int) $toDeskId === (int) $fromDeskId) {
            throw ValidationException::withMessages([
                'toDeskId' => 'A mesa de destino deve ser diferente da mesa atual.',
            ]);
        }

        $desk = Desk::query()
            ->where('clinic_id', $actor->clinic_id)
            ->where('unit_id', $unitId)
            ->whereKey($toDeskId)
            ->first();

        if ($desk === null || ! $desk->active) {
            throw ValidationException::withMessages([
                'toDeskId' => 'A mesa de destino é inválida ou está inativa.',
            ]);
        }

        return $desk;
    }

    private function normalizeReason(?string $reason): ?string
    {
        if ($reason === null) {
            return null;
        }

        $trimmed = trim($reason);

        if ($trimmed === '') {
            return null;
        }

        if (mb_strlen($trimmed) > 255) {
            throw ValidationException::withMessages([
                'reason' => 'O motivo deve ter no máximo 255 caracteres.',
            ]);
        }

        return $trimmed;
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
