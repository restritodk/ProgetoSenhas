<?php

namespace Tests\Feature;

use App\Actions\EnsureDefaultSectorForUnit;
use App\Models\Clinic;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\Unit;
use App\Services\NextTicketSelector;
use App\TicketStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketTransferPriorityTest extends TestCase
{
    use RefreshDatabase;

    public function test_aging_uses_queued_at_not_issued_at(): void
    {
        [$unit, $normal] = $this->setupClinicTypes();
        $now = CarbonImmutable::parse('2026-09-22 12:00:00', config('app.timezone'));

        $ticket = $this->waitingTicket($unit, $normal, 1, $now->subMinutes(30), $now->subSeconds(30));

        $selector = app(NextTicketSelector::class);

        // Base 10 + 0 intervals from queued_at (30s) = 10 — not 10 + 30*5 from issued_at.
        $this->assertSame(10, $selector->effectivePriority($ticket, $now));
    }

    public function test_transfer_resets_aging_via_queued_at(): void
    {
        [$unit, $normal, $preferential] = $this->setupClinicTypes();
        $now = CarbonImmutable::parse('2026-09-22 12:00:00', config('app.timezone'));

        // Old issued_at but freshly queued — should lose to preferential with modest wait.
        $this->waitingTicket($unit, $normal, 1, $now->subHours(2), $now->subSeconds(10));
        $preferentialTicket = $this->waitingTicket($unit, $preferential, 1, $now->subMinutes(1), $now->subMinutes(1));

        $selected = app(NextTicketSelector::class)->select($unit, $now);

        $this->assertTrue($selected?->is($preferentialTicket));
    }

    public function test_fifo_uses_queued_at(): void
    {
        [$unit, $normal] = $this->setupClinicTypes();
        $now = CarbonImmutable::parse('2026-09-22 12:00:00', config('app.timezone'));

        $first = $this->waitingTicket($unit, $normal, 1, $now->subHours(5), $now->subMinutes(3));
        $this->waitingTicket($unit, $normal, 2, $now->subMinutes(1), $now->subMinutes(2));

        $selected = app(NextTicketSelector::class)->select($unit, $now);

        $this->assertTrue($selected?->is($first));
    }

    public function test_targeted_ticket_participates_in_same_priority_policy(): void
    {
        [$unit, $normal, $preferential] = $this->setupClinicTypes();
        $desk = Desk::factory()->create([
            'clinic_id' => $unit->clinic_id,
            'unit_id' => $unit->id,
        ]);
        $now = CarbonImmutable::parse('2026-09-22 12:00:00', config('app.timezone'));

        $targetedNormal = $this->waitingTicket($unit, $normal, 1, $now->subMinutes(3), $now->subMinutes(3), $desk->id);
        $this->waitingTicket($unit, $preferential, 1, $now->subSeconds(10), $now->subSeconds(10));

        $selected = app(NextTicketSelector::class)->select($unit, $now, $desk);

        // Aged normal (25) beats fresh preferential (20) even when targeted.
        $this->assertTrue($selected?->is($targetedNormal));
    }

    public function test_ticket_targeted_to_another_desk_is_excluded(): void
    {
        [$unit, $normal] = $this->setupClinicTypes();
        $deskA = Desk::factory()->create([
            'clinic_id' => $unit->clinic_id,
            'unit_id' => $unit->id,
            'name' => 'Mesa A',
            'code' => 'A01',
        ]);
        $deskB = Desk::factory()->create([
            'clinic_id' => $unit->clinic_id,
            'unit_id' => $unit->id,
            'name' => 'Mesa B',
            'code' => 'B01',
        ]);
        $now = CarbonImmutable::parse('2026-09-22 12:00:00', config('app.timezone'));

        $this->waitingTicket($unit, $normal, 1, $now->subMinutes(5), $now->subMinutes(5), $deskB->id);
        $general = $this->waitingTicket($unit, $normal, 2, $now->subMinutes(1), $now->subMinutes(1));

        $selected = app(NextTicketSelector::class)->select($unit, $now, $deskA);

        $this->assertTrue($selected?->is($general));
        $this->assertNull(
            app(NextTicketSelector::class)
                ->rankedWaitingQueue($unit, $now, $deskA)
                ->first(fn (Ticket $ticket): bool => (int) $ticket->target_desk_id === (int) $deskB->id),
        );
    }

    /**
     * @return array{0: Unit, 1: TicketType, 2?: TicketType, 3?: TicketType}
     */
    private function setupClinicTypes(): array
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create();
        $normal = $this->type($clinic, 'Normal', 'N', 10);
        $preferential = $this->type($clinic, 'Preferencial', 'P', 20);
        $emergency = $this->type($clinic, 'Emergencial', 'E', 30);

        return [$unit, $normal, $preferential, $emergency];
    }

    private function type(Clinic $clinic, string $name, string $prefix, int $priority): TicketType
    {
        $ticketType = new TicketType;
        $ticketType->forceFill([
            'clinic_id' => $clinic->id,
            'name' => $name,
            'prefix' => $prefix,
            'priority' => $priority,
            'active' => true,
        ])->save();

        return $ticketType->refresh();
    }

    private function waitingTicket(
        Unit $unit,
        TicketType $type,
        int $sequenceNumber,
        CarbonImmutable $issuedAt,
        ?CarbonImmutable $queuedAt = null,
        ?int $targetDeskId = null,
    ): Ticket {
        $queuedAt ??= $issuedAt;

        $ticket = new Ticket;
        $ticket->forceFill([
            'clinic_id' => $unit->clinic_id,
            'unit_id' => $unit->id,
            'sector_id' => app(EnsureDefaultSectorForUnit::class)->handle($unit)->id,
            'ticket_type_id' => $type->id,
            'sequence_number' => $sequenceNumber,
            'sequence_date' => $issuedAt->toDateString(),
            'status' => TicketStatus::WAITING,
            'issued_at' => $issuedAt,
            'queued_at' => $queuedAt,
            'target_desk_id' => $targetDeskId,
        ])->save();

        return $ticket->refresh()->load('ticketType');
    }
}
