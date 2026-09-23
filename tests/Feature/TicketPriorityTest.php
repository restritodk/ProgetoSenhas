<?php

namespace Tests\Feature;

use App\Actions\EnsureDefaultSectorForUnit;
use App\Models\Clinic;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\Unit;
use App\Services\NextTicketSelector;
use App\TicketStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketPriorityTest extends TestCase
{
    use RefreshDatabase;

    public function test_emergency_is_selected_before_fresh_normal(): void
    {
        [$unit, $normal, $preferential, $emergency] = $this->setupClinicTypes();
        $now = CarbonImmutable::parse('2026-09-22 12:00:00', config('app.timezone'));

        $this->waitingTicket($unit, $normal, 1, $now->subSeconds(30));
        $emergencyTicket = $this->waitingTicket($unit, $emergency, 1, $now->subSeconds(5));

        $selected = app(NextTicketSelector::class)->select($unit, $now);

        $this->assertNotNull($selected);
        $this->assertTrue($selected->is($emergencyTicket));
    }

    public function test_preferential_beats_normal_under_equivalent_waiting(): void
    {
        [$unit, $normal, $preferential] = $this->setupClinicTypes();
        $now = CarbonImmutable::parse('2026-09-22 12:00:00', config('app.timezone'));

        $this->waitingTicket($unit, $normal, 1, $now->subMinutes(1));
        $preferentialTicket = $this->waitingTicket($unit, $preferential, 1, $now->subMinutes(1));

        $selected = app(NextTicketSelector::class)->select($unit, $now);

        $this->assertTrue($selected?->is($preferentialTicket));
    }

    public function test_fifo_between_equivalent_tickets(): void
    {
        [$unit, $normal] = $this->setupClinicTypes();
        $now = CarbonImmutable::parse('2026-09-22 12:00:00', config('app.timezone'));

        $first = $this->waitingTicket($unit, $normal, 1, $now->subMinutes(3));
        $this->waitingTicket($unit, $normal, 2, $now->subMinutes(2));

        $selected = app(NextTicketSelector::class)->select($unit, $now);

        $this->assertTrue($selected?->is($first));
    }

    public function test_aged_normal_eventually_surpasses_fresh_preferential(): void
    {
        [$unit, $normal, $preferential] = $this->setupClinicTypes();
        $now = CarbonImmutable::parse('2026-09-22 12:00:00', config('app.timezone'));

        // Normal waited 3 minutes → effective 10 + (3 * 5) = 25
        $agedNormal = $this->waitingTicket($unit, $normal, 1, $now->subMinutes(3));
        // Fresh preferential → effective 20
        $this->waitingTicket($unit, $preferential, 1, $now->subSeconds(10));

        $selector = app(NextTicketSelector::class);

        $this->assertSame(25, $selector->effectivePriority($agedNormal, $now));
        $this->assertTrue($selector->select($unit, $now)?->is($agedNormal));
    }

    public function test_non_waiting_tickets_are_ignored(): void
    {
        [$unit, $normal, $preferential] = $this->setupClinicTypes();
        $now = CarbonImmutable::parse('2026-09-22 12:00:00', config('app.timezone'));

        $called = $this->waitingTicket($unit, $preferential, 1, $now->subMinutes(5));
        $called->forceFill(['status' => TicketStatus::CALLED])->save();

        $waiting = $this->waitingTicket($unit, $normal, 1, $now->subMinutes(1));

        $selected = app(NextTicketSelector::class)->select($unit, $now);

        $this->assertTrue($selected?->is($waiting));
        $this->assertFalse($selected?->is($called));
    }

    public function test_selection_does_not_mutate_ticket_status(): void
    {
        [$unit, $normal] = $this->setupClinicTypes();
        $now = CarbonImmutable::parse('2026-09-22 12:00:00', config('app.timezone'));
        $ticket = $this->waitingTicket($unit, $normal, 1, $now->subMinute());

        app(NextTicketSelector::class)->select($unit, $now);

        $this->assertSame(TicketStatus::WAITING, $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->called_at);
    }

    public function test_queues_never_mix_units_or_clinics(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $unitA = Unit::factory()->for($clinicA)->create();
        $unitB = Unit::factory()->for($clinicA)->create();
        $unitForeign = Unit::factory()->for($clinicB)->create();

        $typeA = $this->type($clinicA, 'Normal', 'N', 10);
        $typeB = $this->type($clinicB, 'Emergencial', 'E', 30);

        $now = CarbonImmutable::parse('2026-09-22 12:00:00', config('app.timezone'));

        $ticketA = $this->waitingTicket($unitA, $typeA, 1, $now->subMinutes(10));
        $this->waitingTicket($unitB, $typeA, 1, $now->subMinutes(20));
        $this->waitingTicket($unitForeign, $typeB, 1, $now->subMinutes(30));

        $selected = app(NextTicketSelector::class)->select($unitA, $now);

        $this->assertTrue($selected?->is($ticketA));
        $this->assertSame($unitA->id, $selected?->unit_id);
        $this->assertSame($clinicA->id, $selected?->clinic_id);
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
    ): Ticket {
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
            'queued_at' => $issuedAt,
            'target_desk_id' => null,
        ])->save();

        return $ticket->refresh()->load('ticketType');
    }
}
