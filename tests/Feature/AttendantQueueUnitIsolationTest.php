<?php

namespace Tests\Feature;

use App\Actions\CallNextTicket;
use App\Actions\ClaimDesk;
use App\Actions\IssueTicket;
use App\Livewire\AttendantPanel;
use App\Models\Clinic;
use App\Models\ClinicRolePermission;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\TicketCall;
use App\Models\TicketType;
use App\Models\Unit;
use App\Models\User;
use App\Services\ClinicPermissionResolver;
use App\Services\NextTicketSelector;
use App\Services\OperationalContext;
use App\TicketCallType;
use App\TicketStatus;
use App\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AttendantQueueUnitIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_attendant_calls_waiting_ticket_on_same_unit(): void
    {
        [$attendant, $unit, $desk, $admin, $type] = $this->readyAttendantOnUnit();

        $this->actingAs($admin);
        $ticket = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        $this->assertSame(TicketStatus::WAITING, $ticket->status);

        $this->actingAs($attendant);
        app(OperationalContext::class)->setActiveUnit($attendant, $unit, session());
        app(ClaimDesk::class)->handle($attendant, $desk);

        $panel = Livewire::actingAs($attendant)->test(AttendantPanel::class);
        $this->assertTrue(
            $panel->instance()->upcomingQueue->contains(fn (Ticket $row): bool => $row->id === $ticket->id)
        );
        $this->assertSame(1, $panel->instance()->unitWaitingCount);
        $this->assertSame(1, $panel->instance()->deskAvailableCount);
        $panel->assertSee('Na fila 1');

        $called = app(CallNextTicket::class)->handle($attendant);

        $this->assertNotNull($called);
        $this->assertTrue($called->is($ticket));
        $this->assertSame(TicketStatus::CALLED, $called->status);
        $this->assertSame($desk->id, $called->current_desk_id);
        $this->assertSame($attendant->id, $called->called_by_user_id);
        $this->assertDatabaseHas('ticket_calls', [
            'ticket_id' => $ticket->id,
            'desk_id' => $desk->id,
            'called_by_user_id' => $attendant->id,
            'call_type' => TicketCallType::INITIAL->value,
            'unit_id' => $unit->id,
        ]);
    }

    public function test_attendant_on_unit_b_sees_only_unit_b_tickets_and_calls_unit_b(): void
    {
        $clinic = Clinic::factory()->create();
        $unitA = Unit::factory()->for($clinic)->create(['name' => 'Unidade A']);
        $unitB = Unit::factory()->for($clinic)->create(['name' => 'Unidade B']);
        $deskA = Desk::factory()->create(['clinic_id' => $clinic->id, 'unit_id' => $unitA->id, 'name' => 'Mesa A']);
        $deskB = Desk::factory()->create(['clinic_id' => $clinic->id, 'unit_id' => $unitB->id, 'name' => 'Mesa B']);
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);
        $attendant = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ATTENDANT]);
        $attendant->units()->attach([
            $unitA->id => ['clinic_id' => $clinic->id],
            $unitB->id => ['clinic_id' => $clinic->id],
        ]);
        $type = $this->type($clinic, 'Normal', 'N', 10);

        $this->actingAs($admin);
        $ticketA = app(IssueTicket::class)->handle($admin, $unitA->id, $type->id);
        $ticketB = app(IssueTicket::class)->handle($admin, $unitB->id, $type->id);

        $this->actingAs($attendant);
        app(OperationalContext::class)->setActiveUnit($attendant, $unitB, session());
        app(ClaimDesk::class)->handle($attendant, $deskB);

        $upcoming = Livewire::actingAs($attendant)
            ->test(AttendantPanel::class)
            ->instance()
            ->upcomingQueue;

        $this->assertTrue($upcoming->contains(fn (Ticket $row): bool => $row->id === $ticketB->id));
        $this->assertFalse($upcoming->contains(fn (Ticket $row): bool => $row->id === $ticketA->id));
        $this->assertSame(1, Livewire::actingAs($attendant)->test(AttendantPanel::class)->instance()->unitWaitingCount);

        $hint = Livewire::actingAs($attendant)->test(AttendantPanel::class)->instance()->crossUnitWaitingHint;
        $this->assertNull($hint);

        // Empty unit B after calling, then hint should mention unit A.
        $called = app(CallNextTicket::class)->handle($attendant);
        $this->assertTrue($called->is($ticketB));

        $this->assertNull(
            app(NextTicketSelector::class)->rankedWaitingQueue($unitB, CarbonImmutable::now(config('app.timezone')), $deskB)->first()
        );

        Livewire::actingAs($attendant)
            ->test(AttendantPanel::class)
            ->call('refreshPanel')
            ->assertSee('Unidade A')
            ->assertSee('somente Unidade B');
    }

    public function test_admin_and_attendant_share_same_selector_rules_on_same_unit(): void
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create();
        $desk = Desk::factory()->create(['clinic_id' => $clinic->id, 'unit_id' => $unit->id]);
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);
        $attendant = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ATTENDANT]);
        $attendant->units()->attach($unit->id, ['clinic_id' => $clinic->id]);
        $type = $this->type($clinic, 'Preferencial', 'P', 20);

        $this->actingAs($admin);
        $ticket = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);

        $selector = app(NextTicketSelector::class);
        $now = CarbonImmutable::now(config('app.timezone'));

        $forAdmin = $selector->rankedWaitingQueue($unit, $now, $desk)->pluck('id')->all();
        $forAttendant = $selector->rankedWaitingQueue($unit, $now, $desk)->pluck('id')->all();
        $this->assertSame($forAdmin, $forAttendant);
        $this->assertSame([$ticket->id], $forAdmin);

        $this->actingAs($attendant);
        app(OperationalContext::class)->setActiveUnit($attendant, $unit, session());
        app(ClaimDesk::class)->handle($attendant, $desk);
        $calledByAttendant = app(CallNextTicket::class)->handle($attendant);
        $this->assertTrue($calledByAttendant->is($ticket));
    }

    public function test_clinic_isolation_blocks_foreign_ticket_even_with_forged_context(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $unitA = Unit::factory()->for($clinicA)->create();
        $unitB = Unit::factory()->for($clinicB)->create();
        $deskA = Desk::factory()->create(['clinic_id' => $clinicA->id, 'unit_id' => $unitA->id]);
        $adminA = User::factory()->create(['clinic_id' => $clinicA->id, 'role' => UserRole::ADMINISTRATOR]);
        $adminB = User::factory()->create(['clinic_id' => $clinicB->id, 'role' => UserRole::ADMINISTRATOR]);
        $attendantA = User::factory()->create(['clinic_id' => $clinicA->id, 'role' => UserRole::ATTENDANT]);
        $attendantA->units()->attach($unitA->id, ['clinic_id' => $clinicA->id]);
        $typeA = $this->type($clinicA, 'Normal', 'N', 10);
        $typeB = $this->type($clinicB, 'Normal', 'N', 10);

        $this->actingAs($adminB);
        $foreign = app(IssueTicket::class)->handle($adminB, $unitB->id, $typeB->id);

        $this->actingAs($adminA);
        $local = app(IssueTicket::class)->handle($adminA, $unitA->id, $typeA->id);

        $this->actingAs($attendantA);
        app(OperationalContext::class)->setActiveUnit($attendantA, $unitA, session());
        app(ClaimDesk::class)->handle($attendantA, $deskA);

        $upcoming = Livewire::actingAs($attendantA)->test(AttendantPanel::class)->instance()->upcomingQueue;
        $this->assertTrue($upcoming->contains(fn (Ticket $row): bool => $row->id === $local->id));
        $this->assertFalse($upcoming->contains(fn (Ticket $row): bool => $row->id === $foreign->id));

        $called = app(CallNextTicket::class)->handle($attendantA);
        $this->assertTrue($called->is($local));
        $this->assertSame(0, TicketCall::query()->where('ticket_id', $foreign->id)->count());
        $this->assertSame(TicketStatus::WAITING, $foreign->fresh()->status);
    }

    public function test_missing_call_permission_is_distinct_from_empty_queue(): void
    {
        [$attendant, $unit, $desk, $admin, $type] = $this->readyAttendantOnUnit();

        $this->actingAs($admin);
        app(IssueTicket::class)->handle($admin, $unit->id, $type->id);

        // Ensure defaults exist, then revoke only tickets.call.
        $this->assertTrue($attendant->hasPermission('tickets.call'));

        ClinicRolePermission::query()
            ->where('clinic_id', $attendant->clinic_id)
            ->where('role', UserRole::ATTENDANT->value)
            ->whereHas('permission', fn ($q) => $q->where('key', 'tickets.call'))
            ->delete();

        app(ClinicPermissionResolver::class)->forget($attendant->clinic_id, UserRole::ATTENDANT);

        $this->assertFalse($attendant->fresh()->hasPermission('tickets.call'));

        $this->actingAs($attendant);
        app(OperationalContext::class)->setActiveUnit($attendant, $unit, session());
        app(ClaimDesk::class)->handle($attendant, $desk);

        Livewire::actingAs($attendant)
            ->test(AttendantPanel::class)
            ->assertSee('Na fila 1')
            ->assertSee('Seu perfil não tem permissão para chamar senhas.')
            ->assertDontSee('Chamar próxima senha');
    }

    /**
     * @return array{0: User, 1: Unit, 2: Desk, 3: User, 4: TicketType}
     */
    private function readyAttendantOnUnit(): array
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create(['name' => 'Hospital Toledo']);
        $desk = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'name' => 'Guichê 02',
            'code' => 'G02',
        ]);
        $admin = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);
        $attendant = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ATTENDANT,
        ]);
        $attendant->units()->attach($unit->id, ['clinic_id' => $clinic->id]);

        return [$attendant, $unit, $desk, $admin, $this->type($clinic, 'Normal', 'N', 10)];
    }

    private function type(Clinic $clinic, string $name, string $prefix, int $priority): TicketType
    {
        $type = new TicketType;
        $type->forceFill([
            'clinic_id' => $clinic->id,
            'name' => $name,
            'prefix' => $prefix,
            'priority' => $priority,
            'active' => true,
        ])->save();

        return $type->refresh();
    }
}
