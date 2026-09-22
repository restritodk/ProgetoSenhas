<?php

namespace App\Actions;

use App\Models\Ticket;
use App\Models\TicketSequence;
use App\Models\TicketType;
use App\Models\Unit;
use App\Models\User;
use App\TicketStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class IssueTicket
{
    public function handle(User $actor, int $unitId, int $ticketTypeId, ?CarbonImmutable $issuedAt = null): Ticket
    {
        Gate::forUser($actor)->authorize('create', Ticket::class);
        abort_if($actor->clinic_id === null, 404);

        $unit = $this->activeUnitForClinic($actor->clinic_id, $unitId);
        $ticketType = $this->activeTicketTypeForClinic($actor->clinic_id, $ticketTypeId);

        $issuedAt ??= CarbonImmutable::now(config('app.timezone'));
        $sequenceDate = $issuedAt->toDateString();

        return DB::transaction(function () use ($actor, $unit, $ticketType, $issuedAt, $sequenceDate): Ticket {
            $sequenceNumber = $this->allocateNextNumber(
                clinicId: $actor->clinic_id,
                unitId: $unit->id,
                ticketTypeId: $ticketType->id,
                sequenceDate: $sequenceDate,
            );

            $ticket = new Ticket;
            $ticket->forceFill([
                'clinic_id' => $actor->clinic_id,
                'unit_id' => $unit->id,
                'ticket_type_id' => $ticketType->id,
                'sequence_number' => $sequenceNumber,
                'sequence_date' => $sequenceDate,
                'status' => TicketStatus::WAITING,
                'issued_at' => $issuedAt,
            ])->save();

            return $ticket->refresh()->load('ticketType');
        });
    }

    private function allocateNextNumber(int $clinicId, int $unitId, int $ticketTypeId, string $sequenceDate): int
    {
        $sequence = TicketSequence::query()
            ->where('clinic_id', $clinicId)
            ->where('unit_id', $unitId)
            ->where('ticket_type_id', $ticketTypeId)
            ->whereDate('sequence_date', $sequenceDate)
            ->lockForUpdate()
            ->first();

        if ($sequence === null) {
            try {
                $sequence = new TicketSequence;
                $sequence->forceFill([
                    'clinic_id' => $clinicId,
                    'unit_id' => $unitId,
                    'ticket_type_id' => $ticketTypeId,
                    'sequence_date' => $sequenceDate,
                    'last_number' => 0,
                ])->save();
            } catch (UniqueConstraintViolationException|QueryException) {
                $sequence = TicketSequence::query()
                    ->where('clinic_id', $clinicId)
                    ->where('unit_id', $unitId)
                    ->where('ticket_type_id', $ticketTypeId)
                    ->whereDate('sequence_date', $sequenceDate)
                    ->lockForUpdate()
                    ->firstOrFail();
            }
        }

        $sequence->forceFill([
            'last_number' => $sequence->last_number + 1,
        ])->save();

        return $sequence->last_number;
    }

    private function activeUnitForClinic(int $clinicId, int $unitId): Unit
    {
        $unit = Unit::query()
            ->where('clinic_id', $clinicId)
            ->whereKey($unitId)
            ->first();

        if ($unit === null) {
            throw ValidationException::withMessages([
                'unitId' => 'A unidade selecionada não pertence à sua clínica.',
            ]);
        }

        if (! $unit->active) {
            throw ValidationException::withMessages([
                'unitId' => 'Não é possível emitir senha para uma unidade desativada.',
            ]);
        }

        return $unit;
    }

    private function activeTicketTypeForClinic(int $clinicId, int $ticketTypeId): TicketType
    {
        $ticketType = TicketType::query()
            ->where('clinic_id', $clinicId)
            ->whereKey($ticketTypeId)
            ->first();

        if ($ticketType === null) {
            throw ValidationException::withMessages([
                'ticketTypeId' => 'O tipo de senha selecionado não pertence à sua clínica.',
            ]);
        }

        if (! $ticketType->active) {
            throw ValidationException::withMessages([
                'ticketTypeId' => 'Não é possível emitir senha com um tipo desativado.',
            ]);
        }

        return $ticketType;
    }
}
