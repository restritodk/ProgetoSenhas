<?php

namespace App\Actions;

use App\Models\Kiosk;
use App\Models\KioskIssuanceAttempt;
use App\Models\Ticket;
use App\Models\TicketSequence;
use App\Models\TicketType;
use App\Models\Unit;
use App\Models\UnitTicketType;
use App\Models\User;
use App\TicketSource;
use App\TicketStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class IssueTicket
{
    public function handle(User $actor, int $unitId, int $ticketTypeId, ?CarbonImmutable $issuedAt = null): Ticket
    {
        Gate::forUser($actor)->authorize('create', Ticket::class);
        abort_if($actor->clinic_id === null, 404);

        $unit = $this->activeUnitForClinic($actor->clinic_id, $unitId);
        $ticketType = $this->activeTicketTypeForClinic($actor->clinic_id, $ticketTypeId);

        return $this->createTicket(
            clinicId: $actor->clinic_id,
            unit: $unit,
            ticketType: $ticketType,
            source: TicketSource::ADMIN,
            kioskId: null,
            issuedAt: $issuedAt,
        );
    }

    /**
     * Public kiosk issuance. Clinic/Unit are always derived from the Kiosk.
     * requestToken provides short-window idempotency against double taps/retries.
     */
    public function handleFromKiosk(
        Kiosk $kiosk,
        int $ticketTypeId,
        string $requestToken,
        ?string $clientIp = null,
        ?CarbonImmutable $issuedAt = null,
    ): Ticket {
        $requestToken = trim($requestToken);

        if ($requestToken === '' || strlen($requestToken) < 16 || strlen($requestToken) > 64) {
            throw ValidationException::withMessages([
                'requestToken' => 'Tentativa de emissão inválida. Tente novamente.',
            ]);
        }

        $this->assertKioskRateLimit($kiosk, $clientIp);

        $kiosk->loadMissing(['clinic', 'unit']);

        if (! $kiosk->isOperationallyAvailable()) {
            throw ValidationException::withMessages([
                'kiosk' => 'Totem temporariamente indisponível.',
            ]);
        }

        $offer = $this->activeOfferForKiosk($kiosk, $ticketTypeId);
        $ticketType = $offer->ticketType;
        $unit = $kiosk->unit;

        return DB::transaction(function () use ($kiosk, $ticketType, $unit, $requestToken, $issuedAt): Ticket {
            $attempt = KioskIssuanceAttempt::query()
                ->where('kiosk_id', $kiosk->id)
                ->where('request_token', $requestToken)
                ->lockForUpdate()
                ->first();

            if ($attempt?->ticket_id !== null) {
                return Ticket::query()
                    ->whereKey($attempt->ticket_id)
                    ->with('ticketType')
                    ->firstOrFail();
            }

            if ($attempt === null) {
                try {
                    $attempt = new KioskIssuanceAttempt;
                    $attempt->forceFill([
                        'clinic_id' => $kiosk->clinic_id,
                        'kiosk_id' => $kiosk->id,
                        'request_token' => $requestToken,
                        'ticket_type_id' => $ticketType->id,
                        'ticket_id' => null,
                    ])->save();
                } catch (UniqueConstraintViolationException|QueryException) {
                    $attempt = KioskIssuanceAttempt::query()
                        ->where('kiosk_id', $kiosk->id)
                        ->where('request_token', $requestToken)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($attempt->ticket_id !== null) {
                        return Ticket::query()
                            ->whereKey($attempt->ticket_id)
                            ->with('ticketType')
                            ->firstOrFail();
                    }
                }
            }

            if ((int) $attempt->ticket_type_id !== (int) $ticketType->id) {
                throw ValidationException::withMessages([
                    'requestToken' => 'Tentativa de emissão inválida. Tente novamente.',
                ]);
            }

            $ticket = $this->createTicket(
                clinicId: $kiosk->clinic_id,
                unit: $unit,
                ticketType: $ticketType,
                source: TicketSource::KIOSK,
                kioskId: $kiosk->id,
                issuedAt: $issuedAt,
            );

            $attempt->forceFill(['ticket_id' => $ticket->id])->save();

            return $ticket;
        });
    }

    private function createTicket(
        int $clinicId,
        Unit $unit,
        TicketType $ticketType,
        TicketSource $source,
        ?int $kioskId,
        ?CarbonImmutable $issuedAt = null,
    ): Ticket {
        $issuedAt ??= CarbonImmutable::now(config('app.timezone'));
        $sequenceDate = $issuedAt->toDateString();

        return DB::transaction(function () use ($clinicId, $unit, $ticketType, $source, $kioskId, $issuedAt, $sequenceDate): Ticket {
            $sequenceNumber = $this->allocateNextNumber(
                clinicId: $clinicId,
                unitId: $unit->id,
                ticketTypeId: $ticketType->id,
                sequenceDate: $sequenceDate,
            );

            $ticket = new Ticket;
            $ticket->forceFill([
                'clinic_id' => $clinicId,
                'unit_id' => $unit->id,
                'ticket_type_id' => $ticketType->id,
                'sequence_number' => $sequenceNumber,
                'sequence_date' => $sequenceDate,
                'status' => TicketStatus::WAITING,
                'source' => $source,
                'kiosk_id' => $kioskId,
                'issued_at' => $issuedAt,
                'queued_at' => $issuedAt,
                'target_desk_id' => null,
            ])->save();

            return $ticket->refresh()->load('ticketType');
        });
    }

    private function assertKioskRateLimit(Kiosk $kiosk, ?string $clientIp): void
    {
        $key = 'kiosk-issue:'.$kiosk->id.':'.($clientIp ?: 'unknown');

        if (RateLimiter::tooManyAttempts($key, 60)) {
            throw new TooManyRequestsHttpException(60, 'Muitas tentativas. Aguarde um momento e tente novamente.');
        }

        RateLimiter::hit($key, 60);
    }

    private function activeOfferForKiosk(Kiosk $kiosk, int $ticketTypeId): UnitTicketType
    {
        $offer = UnitTicketType::query()
            ->with(['ticketType'])
            ->where('clinic_id', $kiosk->clinic_id)
            ->where('unit_id', $kiosk->unit_id)
            ->where('ticket_type_id', $ticketTypeId)
            ->where('active', true)
            ->first();

        if ($offer === null || $offer->ticketType === null || ! $offer->ticketType->active) {
            throw ValidationException::withMessages([
                'ticketTypeId' => 'O tipo de atendimento selecionado não está disponível.',
            ]);
        }

        if ((int) $offer->ticketType->clinic_id !== (int) $kiosk->clinic_id) {
            throw ValidationException::withMessages([
                'ticketTypeId' => 'O tipo de atendimento selecionado não está disponível.',
            ]);
        }

        return $offer;
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
