<?php

namespace Tests\Feature;

use App\Actions\CallNextTicket;
use App\Actions\ClaimDesk;
use App\Actions\CompleteTicketService;
use App\Actions\IssueTicket;
use App\Actions\MarkTicketNoShow;
use App\Actions\RecallTicket;
use App\Actions\StartTicketService;
use App\Livewire\AttendantPanel;
use App\Models\Clinic;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\TicketCall;
use App\Models\TicketType;
use App\Models\Unit;
use App\Models\User;
use App\Services\OperationalContext;
use App\TicketCallType;
use App\TicketStatus;
use App\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AttendantPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendant_accesses_panel_and_user_without_unit_cannot_operate(): void
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create();
        $attendant = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ATTENDANT,
        ]);
        $attendant->units()->detach();
        $attendant->units()->attach($unit, ['clinic_id' => $clinic->id]);

        $this->actingAs($attendant)
            ->get(route('attendant.panel'))
            ->assertOk()
            ->assertSee('Atendimento');

        $orphan = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ATTENDANT,
        ]);
        $orphan->units()->detach();

        $this->actingAs($orphan)
            ->get(route('attendant.panel'))
            ->assertOk()
            ->assertSee('Nenhuma unidade disponível');
    }

    public function test_unlinked_and_cross_tenant_units_are_rejected(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $unitA = Unit::factory()->for($clinicA)->create();
        $unitB = Unit::factory()->for($clinicB)->create();
        $unlinked = Unit::factory()->for($clinicA)->create();
        $attendant = User::factory()->create([
            'clinic_id' => $clinicA->id,
            'role' => UserRole::ATTENDANT,
        ]);
        $attendant->units()->detach();
        $attendant->units()->attach($unitA, ['clinic_id' => $clinicA->id]);

        $context = app(OperationalContext::class);
        $this->assertFalse($context->setActiveUnit($attendant, $unlinked, session()));
        $this->assertFalse($context->setActiveUnit($attendant, $unitB, session()));
        $this->assertTrue($context->setActiveUnit($attendant, $unitA, session()));
    }

    public function test_desk_selection_accepts_only_valid_desks_of_active_unit(): void
    {
        [$attendant, $unitA, $deskA] = $this->readyAttendant();
        $unitB = Unit::factory()->for($attendant->clinic)->create();
        $deskOtherUnit = Desk::factory()->create([
            'clinic_id' => $attendant->clinic_id,
            'unit_id' => $unitB->id,
            'name' => 'Mesa Outra',
            'code' => 'X01',
        ]);
        $inactiveDesk = Desk::factory()->create([
            'clinic_id' => $attendant->clinic_id,
            'unit_id' => $unitA->id,
            'active' => false,
            'name' => 'Mesa Inativa',
            'code' => 'I01',
        ]);
        $foreignClinic = Clinic::factory()->create();
        $foreignUnit = Unit::factory()->for($foreignClinic)->create();
        $foreignDesk = Desk::factory()->create([
            'clinic_id' => $foreignClinic->id,
            'unit_id' => $foreignUnit->id,
        ]);

        $this->actingAs($attendant);
        app(OperationalContext::class)->setActiveUnit($attendant, $unitA, session());

        Livewire::actingAs($attendant)
            ->test(AttendantPanel::class)
            ->assertSee($deskA->name)
            ->assertDontSee($deskOtherUnit->name)
            ->assertDontSee($inactiveDesk->name)
            ->assertDontSee($foreignDesk->name);

        app(ClaimDesk::class)->handle($attendant, $deskA);
        $this->assertSame($deskA->id, app(OperationalContext::class)->activeDesk($attendant, session())?->id);

        $this->expectException(ValidationException::class);
        app(ClaimDesk::class)->handle($attendant, $deskOtherUnit);
    }

    public function test_call_next_ticket_creates_initial_call_and_respects_priority(): void
    {
        [$attendant, $unit, $desk] = $this->readyAttendant();
        $normal = $this->type($attendant->clinic, 'Normal', 'N', 10);
        $preferential = $this->type($attendant->clinic, 'Preferencial', 'P', 20);
        $this->actingAs($attendant);

        $issuer = app(IssueTicket::class);
        $issuer->handle($this->adminFor($attendant->clinic), $unit->id, $normal->id, CarbonImmutable::now(config('app.timezone'))->subMinute());
        $preferentialTicket = $issuer->handle($this->adminFor($attendant->clinic), $unit->id, $preferential->id);

        $this->claimContext($attendant, $unit, $desk);

        $called = app(CallNextTicket::class)->handle($attendant);

        $this->assertNotNull($called);
        $this->assertTrue($called->is($preferentialTicket));
        $this->assertSame(TicketStatus::CALLED, $called->status);
        $this->assertSame($desk->id, $called->current_desk_id);
        $this->assertSame($attendant->id, $called->called_by_user_id);
        $this->assertNotNull($called->called_at);
        $this->assertDatabaseHas('ticket_calls', [
            'ticket_id' => $called->id,
            'desk_id' => $desk->id,
            'called_by_user_id' => $attendant->id,
            'call_type' => TicketCallType::INITIAL->value,
        ]);
    }

    public function test_call_next_respects_aging_over_base_priority(): void
    {
        [$attendant, $unit, $desk] = $this->readyAttendant();
        $normal = $this->type($attendant->clinic, 'Normal', 'N', 10);
        $preferential = $this->type($attendant->clinic, 'Preferencial', 'P', 20);
        $admin = $this->adminFor($attendant->clinic);
        $this->actingAs($attendant);

        $issuer = app(IssueTicket::class);
        $agedNormal = $issuer->handle(
            $admin,
            $unit->id,
            $normal->id,
            CarbonImmutable::now(config('app.timezone'))->subMinutes(3),
        );
        $issuer->handle($admin, $unit->id, $preferential->id);

        $this->claimContext($attendant, $unit, $desk);
        $called = app(CallNextTicket::class)->handle($attendant);

        $this->assertNotNull($called);
        $this->assertTrue($called->is($agedNormal));
    }

    public function test_recall_start_complete_and_no_show_flows(): void
    {
        [$attendant, $unit, $desk] = $this->readyAttendant();
        $type = $this->type($attendant->clinic, 'Normal', 'N', 10);
        $this->actingAs($attendant);
        $admin = $this->adminFor($attendant->clinic);

        $ticket = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        $this->claimContext($attendant, $unit, $desk);
        $called = app(CallNextTicket::class)->handle($attendant);
        $this->assertTrue($called->is($ticket));

        app(RecallTicket::class)->handle($attendant, $called);
        $this->assertSame(1, Ticket::query()->count());
        $this->assertSame(1, $called->fresh()->sequence_number);
        $this->assertSame(2, TicketCall::query()->where('ticket_id', $called->id)->count());
        $this->assertDatabaseHas('ticket_calls', [
            'ticket_id' => $called->id,
            'call_type' => TicketCallType::RECALL->value,
        ]);

        $inService = app(StartTicketService::class)->handle($attendant, $called);
        $this->assertSame(TicketStatus::IN_SERVICE, $inService->status);
        $this->assertNotNull($inService->service_started_at);

        $completed = app(CompleteTicketService::class)->handle($attendant, $inService);
        $this->assertSame(TicketStatus::COMPLETED, $completed->status);
        $this->assertNotNull($completed->completed_at);

        $second = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        $calledAgain = app(CallNextTicket::class)->handle($attendant);
        $this->assertTrue($calledAgain->is($second));

        $noShow = app(MarkTicketNoShow::class)->handle($attendant, $calledAgain);
        $this->assertSame(TicketStatus::NO_SHOW, $noShow->status);
        $this->assertNull(
            Ticket::query()
                ->where('current_desk_id', $desk->id)
                ->whereIn('status', [TicketStatus::CALLED, TicketStatus::IN_SERVICE])
                ->first(),
        );
    }

    public function test_invalid_transitions_and_foreign_desk_ticket_are_blocked(): void
    {
        [$attendant, $unit, $desk] = $this->readyAttendant();
        $type = $this->type($attendant->clinic, 'Normal', 'N', 10);
        $admin = $this->adminFor($attendant->clinic);
        $this->actingAs($attendant);

        $waiting = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        $this->claimContext($attendant, $unit, $desk);

        try {
            app(StartTicketService::class)->handle($attendant, $waiting);
            $this->fail('WAITING cannot start service.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        try {
            app(CompleteTicketService::class)->handle($attendant, $waiting);
            $this->fail('WAITING cannot complete.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $called = app(CallNextTicket::class)->handle($attendant);
        app(StartTicketService::class)->handle($attendant, $called);
        $completed = app(CompleteTicketService::class)->handle($attendant, $called);

        try {
            app(RecallTicket::class)->handle($attendant, $completed);
            $this->fail('COMPLETED cannot recall.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $otherDesk = Desk::factory()->create([
            'clinic_id' => $attendant->clinic_id,
            'unit_id' => $unit->id,
            'name' => 'Mesa 02',
            'code' => 'M02',
        ]);
        $otherTicket = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        app(ClaimDesk::class)->handle($attendant, $otherDesk);
        $foreignCalled = app(CallNextTicket::class)->handle($attendant);
        $this->assertTrue($foreignCalled->is($otherTicket));

        app(ClaimDesk::class)->handle($attendant, $desk);

        try {
            app(StartTicketService::class)->handle($attendant, $foreignCalled);
            $this->fail('Cannot operate ticket of another desk.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    public function test_desk_exclusivity_blocks_second_attendant(): void
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create();
        $desk = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
        ]);
        $first = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ATTENDANT]);
        $second = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ATTENDANT]);
        foreach ([$first, $second] as $user) {
            $user->units()->detach();
            $user->units()->attach($unit, ['clinic_id' => $clinic->id]);
        }

        $this->actingAs($first);
        app(OperationalContext::class)->setActiveUnit($first, $unit, session());
        app(ClaimDesk::class)->handle($first, $desk);

        $this->actingAs($second);
        app(OperationalContext::class)->setActiveUnit($second, $unit, session());

        $this->expectException(ValidationException::class);
        app(ClaimDesk::class)->handle($second, $desk);
    }

    /**
     * @return array{0: User, 1: Unit, 2: Desk}
     */
    private function readyAttendant(): array
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create();
        $desk = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'name' => 'Mesa 01',
            'code' => 'M01',
        ]);
        $attendant = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ATTENDANT,
        ]);
        $attendant->units()->detach();
        $attendant->units()->attach($unit, ['clinic_id' => $clinic->id]);

        return [$attendant, $unit, $desk];
    }

    private function adminFor(Clinic $clinic): User
    {
        return User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);
    }

    private function claimContext(User $user, Unit $unit, Desk $desk): void
    {
        app(OperationalContext::class)->setActiveUnit($user, $unit, session());
        app(ClaimDesk::class)->handle($user, $desk);
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
