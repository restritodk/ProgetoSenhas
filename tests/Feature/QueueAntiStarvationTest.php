<?php

namespace Tests\Feature;

use App\Actions\EnsureDefaultSectorForUnit;
use App\Actions\SaveUnitQueuePolicy;
use App\Models\Clinic;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\Unit;
use App\Models\User;
use App\QueueCriticalMode;
use App\Services\NextTicketSelector;
use App\Services\UnitQueuePolicyResolver;
use App\TicketStatus;
use App\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueueAntiStarvationTest extends TestCase
{
    use RefreshDatabase;

    public function test_configurable_aging_still_favours_long_waiting_ticket(): void
    {
        [$admin, $unit, $types] = $this->ready();
        $now = CarbonImmutable::parse('2026-09-23 12:00:00', config('app.timezone'));

        app(SaveUnitQueuePolicy::class)->handle($admin, $unit, $this->payload($types, [
            'critical_ticket_type_id' => null,
            'distribution_enabled' => false,
            'anti_starvation_enabled' => true,
            'aging_interval_seconds' => 60,
            'aging_bonus_per_interval' => 5,
            'type_settings' => [
                $types['normal']->id => null,
                $types['preferential']->id => null,
            ],
        ]));

        // Normal waited 3 minutes → 10 + 3*5 = 25 > Preferencial 20
        $agedNormal = $this->waitingTicket($unit, $types['normal'], 1, $now->subMinutes(3));
        $this->waitingTicket($unit, $types['preferential'], 1, $now->subSeconds(10));

        $selector = app(NextTicketSelector::class);
        $policy = app(UnitQueuePolicyResolver::class)->findForUnit($unit);

        $this->assertSame(25, $selector->effectivePriority($agedNormal, $now, $policy));
        $this->assertTrue($selector->select($unit, $now)?->is($agedNormal));
    }

    public function test_rescue_wait_boosts_ticket_beyond_fresh_higher_base(): void
    {
        [$admin, $unit, $types] = $this->ready();
        $now = CarbonImmutable::parse('2026-09-23 12:00:00', config('app.timezone'));

        app(SaveUnitQueuePolicy::class)->handle($admin, $unit, $this->payload($types, [
            'critical_ticket_type_id' => null,
            'distribution_enabled' => false,
            'anti_starvation_enabled' => true,
            'aging_interval_seconds' => 60,
            'aging_bonus_per_interval' => 1,
            'type_settings' => [
                $types['normal']->id => 120,
                $types['preferential']->id => null,
            ],
        ]));

        // Normal waited 3 minutes (>= 120s rescue) → rescue boost
        $rescued = $this->waitingTicket($unit, $types['normal'], 1, $now->subMinutes(3));
        $this->waitingTicket($unit, $types['preferential'], 1, $now->subSeconds(30));

        $this->assertTrue(app(NextTicketSelector::class)->select($unit, $now)?->is($rescued));
    }

    /**
     * @return array{0: User, 1: Unit, 2: array{normal: TicketType, preferential: TicketType, emergency: TicketType}}
     */
    private function ready(): array
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create();
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);
        $types = [
            'normal' => $this->type($clinic, 'Normal', 'N', 10),
            'preferential' => $this->type($clinic, 'Preferencial', 'P', 20),
            'emergency' => $this->type($clinic, 'Emergencial', 'E', 30),
        ];

        return [$admin, $unit, $types];
    }

    /**
     * @param  array{normal: TicketType, preferential: TicketType, emergency: TicketType}  $types
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $types, array $overrides = []): array
    {
        return array_merge([
            'critical_ticket_type_id' => $types['emergency']->id,
            'critical_mode' => QueueCriticalMode::AlwaysFirst->value,
            'distribution_enabled' => true,
            'distribution_source_ticket_type_id' => $types['normal']->id,
            'distribution_source_count' => 3,
            'distribution_target_ticket_type_id' => $types['preferential']->id,
            'distribution_target_count' => 1,
            'anti_starvation_enabled' => true,
            'aging_interval_seconds' => 60,
            'aging_bonus_per_interval' => 5,
            'type_settings' => [
                $types['normal']->id => 900,
                $types['preferential']->id => 1200,
                $types['emergency']->id => 1800,
            ],
        ], $overrides);
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

    private function waitingTicket(Unit $unit, TicketType $type, int $sequenceNumber, CarbonImmutable $issuedAt): Ticket
    {
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
