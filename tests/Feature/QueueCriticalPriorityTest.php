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
use App\Models\User;
use App\QueueCriticalMode;
use App\Services\NextTicketSelector;
use App\Services\OperationalContext;
use App\TicketStatus;
use App\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class QueueCriticalPriorityTest extends TestCase
{
    use RefreshDatabase;

    public function test_critical_always_first_selects_critical_type(): void
    {
        [$admin, $unit, $types] = $this->ready();
        $now = CarbonImmutable::parse('2026-09-23 10:00:00', config('app.timezone'));

        app(SaveUnitQueuePolicy::class)->handle($admin, $unit, $this->payload($types, [
            'critical_ticket_type_id' => $types['emergency']->id,
            'critical_mode' => QueueCriticalMode::AlwaysFirst->value,
            'anti_starvation_enabled' => false,
        ]));

        $this->waitingTicket($unit, $types['normal'], 1, $now->subMinutes(10));
        $this->waitingTicket($unit, $types['preferential'], 1, $now->subMinutes(8));
        $emergency = $this->waitingTicket($unit, $types['emergency'], 1, $now->subSeconds(5));

        $selected = app(NextTicketSelector::class)->select($unit, $now);
        $this->assertTrue($selected?->is($emergency));
    }

    public function test_custom_type_can_be_critical(): void
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create();
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);
        $lab = $this->type($clinic, 'Laboratório', 'L', 5);
        $normal = $this->type($clinic, 'Normal', 'N', 10);
        $now = CarbonImmutable::parse('2026-09-23 10:00:00', config('app.timezone'));

        app(SaveUnitQueuePolicy::class)->handle($admin, $unit, [
            'critical_ticket_type_id' => $lab->id,
            'critical_mode' => QueueCriticalMode::AlwaysFirst->value,
            'distribution_enabled' => false,
            'distribution_source_ticket_type_id' => null,
            'distribution_source_count' => 3,
            'distribution_target_ticket_type_id' => null,
            'distribution_target_count' => 1,
            'anti_starvation_enabled' => false,
            'aging_interval_seconds' => 60,
            'aging_bonus_per_interval' => 5,
            'type_settings' => [
                $lab->id => 900,
                $normal->id => 900,
            ],
        ]);

        $this->waitingTicket($unit, $normal, 1, $now->subMinutes(20));
        $labTicket = $this->waitingTicket($unit, $lab, 1, $now->subSeconds(2));

        $this->assertTrue(app(NextTicketSelector::class)->select($unit, $now)?->is($labTicket));
    }

    public function test_in_service_ticket_is_not_interrupted(): void
    {
        [$admin, $unit, $types] = $this->ready();
        $desk = Desk::factory()->create(['clinic_id' => $admin->clinic_id, 'unit_id' => $unit->id, 'active' => true]);
        $attendant = User::factory()->create(['clinic_id' => $admin->clinic_id, 'role' => UserRole::ATTENDANT]);
        $unit->users()->attach($attendant->id, ['clinic_id' => $admin->clinic_id]);

        app(SaveUnitQueuePolicy::class)->handle($admin, $unit, $this->payload($types, [
            'anti_starvation_enabled' => false,
        ]));

        $now = CarbonImmutable::parse('2026-09-23 10:00:00', config('app.timezone'));
        $inService = $this->waitingTicket($unit, $types['normal'], 1, $now->subMinutes(5));
        $inService->forceFill([
            'status' => TicketStatus::IN_SERVICE,
            'current_desk_id' => $desk->id,
            'called_by_user_id' => $attendant->id,
            'called_at' => $now->subMinutes(2),
        ])->save();

        $emergency = $this->waitingTicket($unit, $types['emergency'], 1, $now->subMinute());

        $this->actingAs($attendant);
        app(OperationalContext::class)->setActiveUnit($attendant, $unit, session());
        app(ClaimDesk::class)->handle($attendant, $desk);

        try {
            app(CallNextTicket::class)->handle($attendant);
            $this->fail('Expected ValidationException when desk is busy with IN_SERVICE ticket.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('desk', $exception->errors());
        }

        $this->assertSame(TicketStatus::IN_SERVICE, $inService->fresh()->status);
        $this->assertSame(TicketStatus::WAITING, $emergency->fresh()->status);
    }

    public function test_after_critical_policy_continues_normally(): void
    {
        [$admin, $unit, $types] = $this->ready();
        $desk = Desk::factory()->create(['clinic_id' => $admin->clinic_id, 'unit_id' => $unit->id, 'active' => true]);
        $attendant = User::factory()->create(['clinic_id' => $admin->clinic_id, 'role' => UserRole::ATTENDANT]);
        $unit->users()->attach($attendant->id, ['clinic_id' => $admin->clinic_id]);

        app(SaveUnitQueuePolicy::class)->handle($admin, $unit, $this->payload($types, [
            'anti_starvation_enabled' => false,
        ]));

        $now = CarbonImmutable::parse('2026-09-23 10:00:00', config('app.timezone'));
        $this->waitingTicket($unit, $types['normal'], 1, $now->subMinutes(3));
        $this->waitingTicket($unit, $types['preferential'], 1, $now->subMinutes(2));
        $emergency = $this->waitingTicket($unit, $types['emergency'], 1, $now->subMinute());

        $this->actingAs($attendant);
        app(OperationalContext::class)->setActiveUnit($attendant, $unit, session());
        app(ClaimDesk::class)->handle($attendant, $desk);

        $first = app(CallNextTicket::class)->handle($attendant);
        $this->assertTrue($first?->is($emergency));

        $first->forceFill([
            'status' => TicketStatus::COMPLETED,
            'current_desk_id' => null,
        ])->save();

        $second = app(CallNextTicket::class)->handle($attendant);
        $this->assertSame($types['normal']->id, $second?->ticket_type_id);
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
