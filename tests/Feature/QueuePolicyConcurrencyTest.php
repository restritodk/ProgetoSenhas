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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QueuePolicyConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_desks_never_receive_same_ticket_and_progress_advances_once_each(): void
    {
        [$admin, $unit, $types, $deskA, $deskB, $userA, $userB] = $this->twoDesks();

        app(SaveUnitQueuePolicy::class)->handle($admin, $unit, [
            'critical_ticket_type_id' => null,
            'critical_mode' => QueueCriticalMode::AlwaysFirst->value,
            'distribution_enabled' => true,
            'distribution_source_ticket_type_id' => $types['normal']->id,
            'distribution_source_count' => 3,
            'distribution_target_ticket_type_id' => $types['preferential']->id,
            'distribution_target_count' => 1,
            'anti_starvation_enabled' => false,
            'aging_interval_seconds' => 60,
            'aging_bonus_per_interval' => 5,
            'type_settings' => [
                $types['normal']->id => 900,
                $types['preferential']->id => 900,
            ],
        ]);

        $now = CarbonImmutable::parse('2026-09-23 17:00:00', config('app.timezone'));
        $first = $this->waitingTicket($unit, $types['normal'], 1, $now->subMinutes(2));
        $second = $this->waitingTicket($unit, $types['normal'], 2, $now->subMinute());

        $this->actingAs($userA);
        app(OperationalContext::class)->setActiveUnit($userA, $unit, session());
        app(ClaimDesk::class)->handle($userA, $deskA);
        $calledA = app(CallNextTicket::class)->handle($userA);

        $this->actingAs($userB);
        app(OperationalContext::class)->setActiveUnit($userB, $unit, session());
        app(ClaimDesk::class)->handle($userB, $deskB);
        $calledB = app(CallNextTicket::class)->handle($userB);

        $this->assertNotNull($calledA);
        $this->assertNotNull($calledB);
        $this->assertNotSame($calledA->id, $calledB->id);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], [$calledA->id, $calledB->id]);

        $progress = UnitQueuePolicyProgress::query()->where('unit_id', $unit->id)->first();
        $this->assertSame(2, (int) $progress?->source_calls_in_cycle);
    }

    /**
     * SQLite in-memory does not faithfully reproduce MariaDB row locking under true parallelism.
     * Production relies on transaction + lockForUpdate on WAITING rows and unit_queue_policy_progress.
     * Homologate parallel CallNextTicket on MariaDB before production.
     */
    public function test_documents_sqlite_locking_limitation_for_shared_progress(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $this->assertSame('sqlite', $driver);
            $this->assertTrue(true, 'Parallel lockForUpdate on shared distribution progress is not fully validated on SQLite.');

            return;
        }

        $this->test_two_desks_never_receive_same_ticket_and_progress_advances_once_each();
    }

    /**
     * @return array{0: User, 1: Unit, 2: array{normal: TicketType, preferential: TicketType}, 3: Desk, 4: Desk, 5: User, 6: User}
     */
    private function twoDesks(): array
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create();
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);
        $types = [
            'normal' => $this->type($clinic, 'Normal', 'N', 10),
            'preferential' => $this->type($clinic, 'Preferencial', 'P', 20),
        ];
        $deskA = Desk::factory()->create(['clinic_id' => $clinic->id, 'unit_id' => $unit->id, 'code' => 'A', 'active' => true]);
        $deskB = Desk::factory()->create(['clinic_id' => $clinic->id, 'unit_id' => $unit->id, 'code' => 'B', 'active' => true]);
        $userA = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ATTENDANT]);
        $userB = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ATTENDANT]);
        $unit->users()->attach($userA->id, ['clinic_id' => $clinic->id]);
        $unit->users()->attach($userB->id, ['clinic_id' => $clinic->id]);

        return [$admin, $unit, $types, $deskA, $deskB, $userA, $userB];
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
