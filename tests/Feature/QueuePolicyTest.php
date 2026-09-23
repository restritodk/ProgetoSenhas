<?php

namespace Tests\Feature;

use App\Actions\CallNextTicket;
use App\Actions\ClaimDesk;
use App\Actions\EnsureDefaultSectorForUnit;
use App\Actions\RestoreUnitQueuePolicyDefaults;
use App\Actions\SaveUnitQueuePolicy;
use App\Livewire\QueuePoliciesManager;
use App\Models\Clinic;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\Unit;
use App\Models\UnitQueuePolicy;
use App\Models\User;
use App\QueueCriticalMode;
use App\Services\NextTicketSelector;
use App\Services\OperationalContext;
use App\TicketStatus;
use App\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class QueuePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_policy_belongs_to_the_correct_unit(): void
    {
        [$admin, $unitA, $unitB, $types] = $this->readyClinicWithTwoUnits();

        $policyA = app(SaveUnitQueuePolicy::class)->handle($admin, $unitA, $this->policyPayload($types, [
            'distribution_source_count' => 3,
        ]));
        $policyB = app(SaveUnitQueuePolicy::class)->handle($admin, $unitB, $this->policyPayload($types, [
            'distribution_source_count' => 2,
        ]));

        $this->assertSame($unitA->id, $policyA->unit_id);
        $this->assertSame($unitB->id, $policyB->unit_id);
        $this->assertSame(3, $policyA->distribution_source_count);
        $this->assertSame(2, $policyB->distribution_source_count);
        $this->assertSame(2, UnitQueuePolicy::query()->count());
    }

    public function test_tenant_isolation_blocks_foreign_clinic_unit(): void
    {
        [$admin, $unit] = $this->readyClinicWithTwoUnits();
        $foreign = Clinic::factory()->create();
        $foreignUnit = Unit::factory()->for($foreign)->create();

        $this->expectException(HttpException::class);

        app(SaveUnitQueuePolicy::class)->handle($admin, $foreignUnit, [
            'critical_ticket_type_id' => null,
            'critical_mode' => QueueCriticalMode::AlwaysFirst->value,
            'distribution_enabled' => false,
            'distribution_source_ticket_type_id' => null,
            'distribution_source_count' => 3,
            'distribution_target_ticket_type_id' => null,
            'distribution_target_count' => 1,
            'anti_starvation_enabled' => true,
            'aging_interval_seconds' => 60,
            'aging_bonus_per_interval' => 5,
            'type_settings' => [],
        ]);
    }

    public function test_unauthorized_user_cannot_update_policy(): void
    {
        [$admin, $unit, , $types] = $this->readyClinicWithTwoUnits();
        $attendant = User::factory()->create([
            'clinic_id' => $admin->clinic_id,
            'role' => UserRole::ATTENDANT,
        ]);
        $unit->users()->attach($attendant->id, ['clinic_id' => $admin->clinic_id]);

        $this->expectException(AuthorizationException::class);

        app(SaveUnitQueuePolicy::class)->handle($attendant, $unit, $this->policyPayload($types));
    }

    public function test_invalid_critical_ticket_type_is_rejected(): void
    {
        [$admin, $unit, , $types] = $this->readyClinicWithTwoUnits();
        $foreign = Clinic::factory()->create();
        $foreignType = $this->type($foreign, 'Alien', 'X', 99);

        $this->expectException(ValidationException::class);

        app(SaveUnitQueuePolicy::class)->handle($admin, $unit, $this->policyPayload($types, [
            'critical_ticket_type_id' => $foreignType->id,
        ]));
    }

    public function test_source_equals_target_is_rejected(): void
    {
        [$admin, $unit, , $types] = $this->readyClinicWithTwoUnits();

        $this->expectException(ValidationException::class);

        app(SaveUnitQueuePolicy::class)->handle($admin, $unit, $this->policyPayload($types, [
            'distribution_source_ticket_type_id' => $types['normal']->id,
            'distribution_target_ticket_type_id' => $types['normal']->id,
        ]));
    }

    public function test_saving_policy_changes_next_selection(): void
    {
        [$admin, $unit, , $types] = $this->readyClinicWithTwoUnits();
        $now = CarbonImmutable::parse('2026-09-23 10:00:00', config('app.timezone'));

        $this->waitingTicket($unit, $types['normal'], 1, $now->subMinute());
        $preferential = $this->waitingTicket($unit, $types['preferential'], 1, $now->subMinute());

        // Without policy: preferential wins by base priority.
        $this->assertTrue(app(NextTicketSelector::class)->select($unit, $now)?->is($preferential));

        app(SaveUnitQueuePolicy::class)->handle($admin, $unit, $this->policyPayload($types, [
            'critical_ticket_type_id' => null,
            'distribution_enabled' => true,
            'distribution_source_count' => 3,
            'anti_starvation_enabled' => false,
        ]));

        // With 3:1 and both waiting: prefer source (Normal).
        $selected = app(NextTicketSelector::class)->select($unit, $now);
        $this->assertSame($types['normal']->id, $selected?->ticket_type_id);
    }

    public function test_restore_defaults_works(): void
    {
        [$admin, $unit, , $types] = $this->readyClinicWithTwoUnits();

        app(SaveUnitQueuePolicy::class)->handle($admin, $unit, $this->policyPayload($types, [
            'distribution_source_count' => 7,
            'distribution_enabled' => false,
        ]));

        $restored = app(RestoreUnitQueuePolicyDefaults::class)->handle($admin, $unit);

        $this->assertTrue($restored->distribution_enabled);
        $this->assertSame(3, $restored->distribution_source_count);
        $this->assertSame(QueueCriticalMode::AlwaysFirst, $restored->critical_mode);
    }

    public function test_two_units_can_have_different_policies(): void
    {
        [$admin, $unitA, $unitB, $types] = $this->readyClinicWithTwoUnits();
        $now = CarbonImmutable::parse('2026-09-23 11:00:00', config('app.timezone'));

        app(SaveUnitQueuePolicy::class)->handle($admin, $unitA, $this->policyPayload($types, [
            'critical_ticket_type_id' => null,
            'distribution_source_count' => 3,
            'anti_starvation_enabled' => false,
        ]));
        app(SaveUnitQueuePolicy::class)->handle($admin, $unitB, $this->policyPayload($types, [
            'critical_ticket_type_id' => null,
            'distribution_source_count' => 1,
            'anti_starvation_enabled' => false,
        ]));

        $this->waitingTicket($unitA, $types['normal'], 1, $now->subMinute());
        $this->waitingTicket($unitA, $types['preferential'], 1, $now->subMinute());
        $this->waitingTicket($unitB, $types['normal'], 1, $now->subMinute());
        $this->waitingTicket($unitB, $types['preferential'], 1, $now->subMinute());

        // Unit A: 3:1 → still prefer Normal (source_calls=0)
        $this->assertSame($types['normal']->id, app(NextTicketSelector::class)->select($unitA, $now)?->ticket_type_id);

        // Advance unit B progress: source_count=1 means after 0 still prefer source; after 1 call due for target
        $desk = Desk::factory()->create(['clinic_id' => $admin->clinic_id, 'unit_id' => $unitB->id, 'active' => true]);
        $attendant = User::factory()->create(['clinic_id' => $admin->clinic_id, 'role' => UserRole::ATTENDANT]);
        $unitB->users()->attach($attendant->id, ['clinic_id' => $admin->clinic_id]);
        $this->actingAs($attendant);
        app(OperationalContext::class)->setActiveUnit($attendant, $unitB, session());
        app(ClaimDesk::class)->handle($attendant, $desk);
        app(CallNextTicket::class)->handle($attendant);

        $selectedB = app(NextTicketSelector::class)->select($unitB, $now);
        $this->assertSame($types['preferential']->id, $selectedB?->ticket_type_id);
    }

    public function test_livewire_saves_and_restores_with_toast_messages(): void
    {
        [$admin, $unit, , $types] = $this->readyClinicWithTwoUnits();

        Livewire::actingAs($admin)
            ->test(QueuePoliciesManager::class)
            ->assertSee('Filas e Prioridades')
            ->set('selectedUnitId', $unit->id)
            ->set('distributionSourceCount', '4')
            ->set('distributionSourceTicketTypeId', $types['normal']->id)
            ->set('distributionTargetTicketTypeId', $types['preferential']->id)
            ->call('save')
            ->assertSet('statusMessage', 'Configurações de fila salvas com sucesso.')
            ->call('confirmRestore')
            ->call('restoreDefaults')
            ->assertSet('statusMessage', 'Configurações padrão restauradas.');

        $this->assertSame(3, UnitQueuePolicy::query()->where('unit_id', $unit->id)->value('distribution_source_count'));
    }

    public function test_page_requires_administrator(): void
    {
        $clinic = Clinic::factory()->create();
        $attendant = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ATTENDANT,
        ]);

        $this->actingAs($attendant)
            ->get(route('queue-policies.index'))
            ->assertRedirect(route('attendant.panel'));
    }

    /**
     * @return array{0: User, 1: Unit, 2: Unit, 3: array{normal: TicketType, preferential: TicketType, emergency: TicketType}}
     */
    private function readyClinicWithTwoUnits(): array
    {
        $clinic = Clinic::factory()->create();
        $unitA = Unit::factory()->for($clinic)->create(['name' => 'Centro']);
        $unitB = Unit::factory()->for($clinic)->create(['name' => 'Bairro']);
        $admin = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);

        $types = [
            'normal' => $this->type($clinic, 'Normal', 'N', 10),
            'preferential' => $this->type($clinic, 'Preferencial', 'P', 20),
            'emergency' => $this->type($clinic, 'Emergencial', 'E', 30),
        ];

        return [$admin, $unitA, $unitB, $types];
    }

    /**
     * @param  array{normal: TicketType, preferential: TicketType, emergency: TicketType}  $types
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function policyPayload(array $types, array $overrides = []): array
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
