<?php

namespace Tests\Feature;

use App\Actions\CallNextTicket;
use App\Actions\ClaimDesk;
use App\Actions\EnsureDefaultSectorForUnit;
use App\Actions\SaveUnitQueuePolicy;
use App\Models\Clinic;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\Unit;
use App\Models\UnitQueuePolicyProgress;
use App\Models\User;
use App\QueueCriticalMode;
use App\Services\OperationalContext;
use App\TicketStatus;
use App\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueueDistributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_three_to_one_sequence_with_sufficient_demand(): void
    {
        [$admin, $unit, $types, $desk, $attendant] = $this->ready();
        $now = CarbonImmutable::parse('2026-09-23 12:00:00', config('app.timezone'));

        app(SaveUnitQueuePolicy::class)->handle($admin, $unit, $this->payload($types, [
            'critical_ticket_type_id' => null,
            'anti_starvation_enabled' => false,
        ]));

        foreach ([1, 2, 3, 4] as $n) {
            $this->waitingTicket($unit, $types['normal'], $n, $now->subMinutes(10 - $n));
        }
        foreach ([1, 2] as $p) {
            $this->waitingTicket($unit, $types['preferential'], $p, $now->subMinutes(5 - $p));
        }

        $order = [];
        for ($i = 0; $i < 4; $i++) {
            $ticket = $this->callNext($attendant, $unit, $desk);
            $order[] = $ticket->ticket_type_id;
            $ticket->forceFill([
                'status' => TicketStatus::COMPLETED,
                'current_desk_id' => null,
            ])->save();
        }

        $this->assertSame([
            $types['normal']->id,
            $types['normal']->id,
            $types['normal']->id,
            $types['preferential']->id,
        ], $order);
    }

    public function test_progress_is_shared_across_desks(): void
    {
        [$admin, $unit, $types] = $this->readyBase();
        $deskA = Desk::factory()->create(['clinic_id' => $admin->clinic_id, 'unit_id' => $unit->id, 'active' => true, 'code' => 'A']);
        $deskB = Desk::factory()->create(['clinic_id' => $admin->clinic_id, 'unit_id' => $unit->id, 'active' => true, 'code' => 'B']);
        $userA = $this->attendant($admin->clinic, $unit);
        $userB = $this->attendant($admin->clinic, $unit);

        app(SaveUnitQueuePolicy::class)->handle($admin, $unit, $this->payload($types, [
            'critical_ticket_type_id' => null,
            'anti_starvation_enabled' => false,
        ]));

        $now = CarbonImmutable::parse('2026-09-23 13:00:00', config('app.timezone'));
        foreach ([1, 2, 3] as $n) {
            $this->waitingTicket($unit, $types['normal'], $n, $now->subMinutes(5));
        }
        $preferential = $this->waitingTicket($unit, $types['preferential'], 1, $now->subMinutes(4));

        $this->callNext($userA, $unit, $deskA)->forceFill(['status' => TicketStatus::COMPLETED, 'current_desk_id' => null])->save();
        $this->callNext($userB, $unit, $deskB)->forceFill(['status' => TicketStatus::COMPLETED, 'current_desk_id' => null])->save();
        $this->callNext($userA, $unit, $deskA)->forceFill(['status' => TicketStatus::COMPLETED, 'current_desk_id' => null])->save();

        $next = $this->callNext($userB, $unit, $deskB);
        $this->assertTrue($next->is($preferential));

        $progress = UnitQueuePolicyProgress::query()->where('unit_id', $unit->id)->first();
        $this->assertNotNull($progress);
        $this->assertSame(0, (int) $progress->source_calls_in_cycle);
    }

    public function test_falls_back_when_target_missing(): void
    {
        [$admin, $unit, $types, $desk, $attendant] = $this->ready();
        $now = CarbonImmutable::parse('2026-09-23 14:00:00', config('app.timezone'));

        app(SaveUnitQueuePolicy::class)->handle($admin, $unit, $this->payload($types, [
            'critical_ticket_type_id' => null,
            'anti_starvation_enabled' => false,
        ]));

        foreach ([1, 2, 3, 4] as $n) {
            $this->waitingTicket($unit, $types['normal'], $n, $now->subMinutes(5));
        }

        for ($i = 0; $i < 3; $i++) {
            $this->callNext($attendant, $unit, $desk)->forceFill([
                'status' => TicketStatus::COMPLETED,
                'current_desk_id' => null,
            ])->save();
        }

        $fourth = $this->callNext($attendant, $unit, $desk);
        $this->assertSame($types['normal']->id, $fourth->ticket_type_id);
    }

    public function test_only_preferential_waiting_is_served(): void
    {
        [$admin, $unit, $types, $desk, $attendant] = $this->ready();
        $now = CarbonImmutable::parse('2026-09-23 15:00:00', config('app.timezone'));

        app(SaveUnitQueuePolicy::class)->handle($admin, $unit, $this->payload($types, [
            'critical_ticket_type_id' => null,
            'anti_starvation_enabled' => false,
        ]));

        $preferential = $this->waitingTicket($unit, $types['preferential'], 1, $now->subMinute());
        $called = $this->callNext($attendant, $unit, $desk);

        $this->assertTrue($called->is($preferential));
    }

    public function test_custom_types_work_without_hardcoded_prefixes(): void
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create();
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);
        $exames = $this->type($clinic, 'Exames', 'X', 10);
        $retorno = $this->type($clinic, 'Retorno', 'R', 20);
        $desk = Desk::factory()->create(['clinic_id' => $clinic->id, 'unit_id' => $unit->id, 'active' => true]);
        $attendant = $this->attendant($clinic, $unit);

        app(SaveUnitQueuePolicy::class)->handle($admin, $unit, [
            'critical_ticket_type_id' => null,
            'critical_mode' => QueueCriticalMode::AlwaysFirst->value,
            'distribution_enabled' => true,
            'distribution_source_ticket_type_id' => $exames->id,
            'distribution_source_count' => 2,
            'distribution_target_ticket_type_id' => $retorno->id,
            'distribution_target_count' => 1,
            'anti_starvation_enabled' => false,
            'aging_interval_seconds' => 60,
            'aging_bonus_per_interval' => 5,
            'type_settings' => [
                $exames->id => 900,
                $retorno->id => 900,
            ],
        ]);

        $now = CarbonImmutable::parse('2026-09-23 16:00:00', config('app.timezone'));
        $this->waitingTicket($unit, $exames, 1, $now->subMinutes(3));
        $this->waitingTicket($unit, $exames, 2, $now->subMinutes(2));
        $retornoTicket = $this->waitingTicket($unit, $retorno, 1, $now->subMinute());

        $this->callNext($attendant, $unit, $desk)->forceFill([
            'status' => TicketStatus::COMPLETED,
            'current_desk_id' => null,
        ])->save();
        $this->callNext($attendant, $unit, $desk)->forceFill([
            'status' => TicketStatus::COMPLETED,
            'current_desk_id' => null,
        ])->save();

        $this->assertTrue($this->callNext($attendant, $unit, $desk)->is($retornoTicket));
    }

    /**
     * @return array{0: User, 1: Unit, 2: array{normal: TicketType, preferential: TicketType, emergency: TicketType}, 3: Desk, 4: User}
     */
    private function ready(): array
    {
        [$admin, $unit, $types] = $this->readyBase();
        $desk = Desk::factory()->create(['clinic_id' => $admin->clinic_id, 'unit_id' => $unit->id, 'active' => true]);
        $attendant = $this->attendant($admin->clinic, $unit);

        return [$admin, $unit, $types, $desk, $attendant];
    }

    /**
     * @return array{0: User, 1: Unit, 2: array{normal: TicketType, preferential: TicketType, emergency: TicketType}}
     */
    private function readyBase(): array
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

    private function attendant(Clinic $clinic, Unit $unit): User
    {
        $attendant = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ATTENDANT,
        ]);
        $unit->users()->attach($attendant->id, ['clinic_id' => $clinic->id]);

        return $attendant;
    }

    private function callNext(User $attendant, Unit $unit, Desk $desk): Ticket
    {
        $this->actingAs($attendant);
        app(OperationalContext::class)->setActiveUnit($attendant, $unit, session());
        app(ClaimDesk::class)->handle($attendant, $desk);

        $ticket = app(CallNextTicket::class)->handle($attendant);
        $this->assertNotNull($ticket);

        return $ticket;
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
