<?php

namespace Tests\Feature;

use App\Actions\CallNextTicket;
use App\Actions\ClaimDesk;
use App\Actions\CompleteTicketService;
use App\Actions\IssueTicket;
use App\Actions\MarkTicketNoShow;
use App\Actions\StartTicketService;
use App\Actions\TransferTicket;
use App\Actions\UpdateDesk;
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
use App\TicketTransferType;
use App\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class TicketTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_called_ticket_can_be_transferred_to_general_queue(): void
    {
        [$attendant, $unit, $desk, $type, $admin] = $this->readyContext();
        $this->actingAs($attendant);

        $ticket = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        $issuedAt = $ticket->issued_at->copy();
        $this->claimContext($attendant, $unit, $desk);
        $called = app(CallNextTicket::class)->handle($attendant);

        $before = CarbonImmutable::now(config('app.timezone'))->subSecond();
        $transferred = app(TransferTicket::class)->handle(
            $attendant,
            $called,
            TicketTransferType::QUEUE,
            null,
            'Documentação',
        );
        $after = CarbonImmutable::now(config('app.timezone'))->addSecond();

        $this->assertSame(TicketStatus::WAITING, $transferred->status);
        $this->assertNull($transferred->current_desk_id);
        $this->assertNull($transferred->called_by_user_id);
        $this->assertNull($transferred->called_at);
        $this->assertNull($transferred->service_started_at);
        $this->assertNull($transferred->target_desk_id);
        $this->assertTrue($transferred->issued_at->equalTo($issuedAt));
        $this->assertSame($ticket->sequence_number, $transferred->sequence_number);
        $this->assertSame($ticket->display_code, $transferred->display_code);
        $this->assertNotNull($transferred->queued_at);
        $this->assertTrue(
            $transferred->queued_at->greaterThanOrEqualTo($before)
            && $transferred->queued_at->lessThanOrEqualTo($after),
        );

        $this->assertDatabaseHas('ticket_transfers', [
            'ticket_id' => $ticket->id,
            'from_desk_id' => $desk->id,
            'to_desk_id' => null,
            'transfer_type' => TicketTransferType::QUEUE->value,
            'transferred_by_user_id' => $attendant->id,
            'reason' => 'Documentação',
        ]);

        $this->assertNull(
            Ticket::query()
                ->where('current_desk_id', $desk->id)
                ->whereIn('status', [TicketStatus::CALLED, TicketStatus::IN_SERVICE])
                ->first(),
        );
    }

    public function test_in_service_ticket_can_be_transferred_to_desk(): void
    {
        [$attendant, $unit, $deskA, $type, $admin] = $this->readyContext();
        $deskB = Desk::factory()->create([
            'clinic_id' => $attendant->clinic_id,
            'unit_id' => $unit->id,
            'name' => 'Mesa 07',
            'code' => 'M07',
        ]);
        $this->actingAs($attendant);

        $ticket = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        $this->claimContext($attendant, $unit, $deskA);
        $called = app(CallNextTicket::class)->handle($attendant);
        app(StartTicketService::class)->handle($attendant, $called);

        $transferred = app(TransferTicket::class)->handle(
            $attendant,
            $called,
            TicketTransferType::DESK,
            $deskB->id,
            'Atendimento específico',
        );

        $this->assertSame(TicketStatus::WAITING, $transferred->status);
        $this->assertSame($deskB->id, $transferred->target_desk_id);
        $this->assertNull($transferred->current_desk_id);
        $this->assertDatabaseHas('ticket_transfers', [
            'ticket_id' => $ticket->id,
            'from_desk_id' => $deskA->id,
            'to_desk_id' => $deskB->id,
            'transfer_type' => TicketTransferType::DESK->value,
            'reason' => 'Atendimento específico',
        ]);
        $this->assertSame(1, TicketCall::query()->where('ticket_id', $ticket->id)->count());
    }

    public function test_other_desk_cannot_call_targeted_ticket_but_destination_can(): void
    {
        [$attendantA, $unit, $deskA, $type, $admin] = $this->readyContext();
        $deskB = Desk::factory()->create([
            'clinic_id' => $attendantA->clinic_id,
            'unit_id' => $unit->id,
            'name' => 'Mesa 07',
            'code' => 'M07',
        ]);
        $deskC = Desk::factory()->create([
            'clinic_id' => $attendantA->clinic_id,
            'unit_id' => $unit->id,
            'name' => 'Mesa 03',
            'code' => 'M03',
        ]);
        $attendantC = User::factory()->create([
            'clinic_id' => $attendantA->clinic_id,
            'role' => UserRole::ATTENDANT,
        ]);
        $attendantC->units()->detach();
        $attendantC->units()->attach($unit, ['clinic_id' => $attendantA->clinic_id]);

        $attendantB = User::factory()->create([
            'clinic_id' => $attendantA->clinic_id,
            'role' => UserRole::ATTENDANT,
        ]);
        $attendantB->units()->detach();
        $attendantB->units()->attach($unit, ['clinic_id' => $attendantA->clinic_id]);

        $this->actingAs($attendantA);
        $ticket = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        $this->claimContext($attendantA, $unit, $deskA);
        $called = app(CallNextTicket::class)->handle($attendantA);
        app(TransferTicket::class)->handle($attendantA, $called, TicketTransferType::DESK, $deskB->id);

        $this->actingAs($attendantC);
        $this->claimContext($attendantC, $unit, $deskC);
        $this->assertNull(app(CallNextTicket::class)->handle($attendantC));

        $this->actingAs($attendantB);
        $this->claimContext($attendantB, $unit, $deskB);
        $claimed = app(CallNextTicket::class)->handle($attendantB);

        $this->assertNotNull($claimed);
        $this->assertTrue($claimed->is($ticket));
        $this->assertNull($claimed->target_desk_id);
        $this->assertSame($deskB->id, $claimed->current_desk_id);
        $this->assertSame(2, TicketCall::query()->where('ticket_id', $ticket->id)->where('call_type', TicketCallType::INITIAL->value)->count());
    }

    public function test_waiting_completed_no_show_and_cancelled_cannot_transfer(): void
    {
        [$attendant, $unit, $desk, $type, $admin] = $this->readyContext();
        $this->actingAs($attendant);
        $this->claimContext($attendant, $unit, $desk);

        $waiting = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);

        try {
            app(TransferTicket::class)->handle($attendant, $waiting, TicketTransferType::QUEUE);
            $this->fail('WAITING cannot transfer.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $called = app(CallNextTicket::class)->handle($attendant);
        app(StartTicketService::class)->handle($attendant, $called);
        $completed = app(CompleteTicketService::class)->handle($attendant, $called);

        try {
            app(TransferTicket::class)->handle($attendant, $completed, TicketTransferType::QUEUE);
            $this->fail('COMPLETED cannot transfer.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $second = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        $calledAgain = app(CallNextTicket::class)->handle($attendant);
        $noShow = app(MarkTicketNoShow::class)->handle($attendant, $calledAgain);

        try {
            app(TransferTicket::class)->handle($attendant, $noShow, TicketTransferType::QUEUE);
            $this->fail('NO_SHOW cannot transfer.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $cancelled = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        $cancelled->forceFill(['status' => TicketStatus::CANCELLED])->save();

        try {
            app(TransferTicket::class)->handle($attendant, $cancelled, TicketTransferType::QUEUE);
            $this->fail('CANCELLED cannot transfer.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    public function test_foreign_desk_unit_clinic_and_inactive_targets_are_rejected(): void
    {
        [$attendant, $unit, $desk, $type, $admin] = $this->readyContext();
        $otherUnit = Unit::factory()->for($attendant->clinic)->create();
        $otherUnitDesk = Desk::factory()->create([
            'clinic_id' => $attendant->clinic_id,
            'unit_id' => $otherUnit->id,
            'name' => 'Outra Unidade',
            'code' => 'OU1',
        ]);
        $inactiveDesk = Desk::factory()->create([
            'clinic_id' => $attendant->clinic_id,
            'unit_id' => $unit->id,
            'active' => false,
            'name' => 'Inativa',
            'code' => 'IN1',
        ]);
        $foreignClinic = Clinic::factory()->create();
        $foreignUnit = Unit::factory()->for($foreignClinic)->create();
        $foreignDesk = Desk::factory()->create([
            'clinic_id' => $foreignClinic->id,
            'unit_id' => $foreignUnit->id,
        ]);

        $this->actingAs($attendant);
        $ticket = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        $this->claimContext($attendant, $unit, $desk);
        $called = app(CallNextTicket::class)->handle($attendant);

        foreach ([$otherUnitDesk->id, $inactiveDesk->id, $foreignDesk->id, $desk->id] as $badDeskId) {
            try {
                app(TransferTicket::class)->handle($attendant, $called, TicketTransferType::DESK, $badDeskId);
                $this->fail('Invalid destination desk '.$badDeskId.' should be rejected.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }

        $otherDesk = Desk::factory()->create([
            'clinic_id' => $attendant->clinic_id,
            'unit_id' => $unit->id,
            'name' => 'Mesa 02',
            'code' => 'M02',
        ]);
        $foreignTicket = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        app(ClaimDesk::class)->handle($attendant, $otherDesk);
        $foreignCalled = app(CallNextTicket::class)->handle($attendant);
        app(ClaimDesk::class)->handle($attendant, $desk);

        try {
            app(TransferTicket::class)->handle($attendant, $foreignCalled, TicketTransferType::QUEUE);
            $this->fail('Cannot transfer ticket of another desk.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $crossClinic = Clinic::factory()->create();
        $crossUnit = Unit::factory()->for($crossClinic)->create();
        $crossType = $this->type($crossClinic, 'Normal', 'N', 10);
        $crossAdmin = User::factory()->create(['clinic_id' => $crossClinic->id, 'role' => UserRole::ADMINISTRATOR]);
        $crossTicket = app(IssueTicket::class)->handle($crossAdmin, $crossUnit->id, $crossType->id);

        try {
            app(TransferTicket::class)->handle($attendant, $crossTicket, TicketTransferType::QUEUE);
            $this->fail('Cross-tenant transfer must be rejected.');
        } catch (AuthorizationException|ValidationException) {
            $this->assertTrue(true);
        }
    }

    public function test_inactive_user_unit_or_clinic_cannot_transfer(): void
    {
        [$attendant, $unit, $desk, $type, $admin] = $this->readyContext();
        $this->actingAs($attendant);
        $ticket = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        $this->claimContext($attendant, $unit, $desk);
        $called = app(CallNextTicket::class)->handle($attendant);

        $attendant->forceFill(['active' => false])->save();

        try {
            app(TransferTicket::class)->handle($attendant->fresh(), $called, TicketTransferType::QUEUE);
            $this->fail('Inactive user cannot transfer.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $attendant->forceFill(['active' => true])->save();
        $unit->forceFill(['active' => false])->save();

        try {
            app(TransferTicket::class)->handle($attendant->fresh(), $called, TicketTransferType::QUEUE);
            $this->fail('Inactive unit cannot transfer.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $unit->forceFill(['active' => true])->save();
        $attendant->clinic->forceFill(['active' => false])->save();

        try {
            app(TransferTicket::class)->handle($attendant->fresh()->load('clinic'), $called, TicketTransferType::QUEUE);
            $this->fail('Inactive clinic cannot transfer.');
        } catch (AuthorizationException|ValidationException) {
            $this->assertTrue(true);
        }
    }

    public function test_desk_with_waiting_targeted_tickets_cannot_be_deactivated(): void
    {
        [$attendant, $unit, $deskA, $type, $admin] = $this->readyContext();
        $deskB = Desk::factory()->create([
            'clinic_id' => $attendant->clinic_id,
            'unit_id' => $unit->id,
            'name' => 'Mesa 07',
            'code' => 'M07',
        ]);
        $this->actingAs($attendant);
        $ticket = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        $this->claimContext($attendant, $unit, $deskA);
        $called = app(CallNextTicket::class)->handle($attendant);
        app(TransferTicket::class)->handle($attendant, $called, TicketTransferType::DESK, $deskB->id);

        $adminActor = $admin;

        $this->expectException(ValidationException::class);
        app(UpdateDesk::class)->handle($adminActor, $deskB, [
            'name' => $deskB->name,
            'code' => $deskB->code,
            'unit_id' => $deskB->unit_id,
            'active' => false,
        ]);
    }

    public function test_transfer_and_complete_serialize_on_same_ticket(): void
    {
        /*
         * Conceptual concurrency coverage: both actions lock the ticket row and revalidate.
         * SQLite RefreshDatabase does not prove MariaDB row-level locking under parallel writers.
         */
        [$attendant, $unit, $desk, $type, $admin] = $this->readyContext();
        $this->actingAs($attendant);
        $ticket = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        $this->claimContext($attendant, $unit, $desk);
        $called = app(CallNextTicket::class)->handle($attendant);
        $inService = app(StartTicketService::class)->handle($attendant, $called);

        app(TransferTicket::class)->handle($attendant, $inService, TicketTransferType::QUEUE);

        try {
            app(CompleteTicketService::class)->handle($attendant, $inService);
            $this->fail('Complete after transfer must fail.');
        } catch (ValidationException) {
            $this->assertSame(TicketStatus::WAITING, $ticket->fresh()->status);
        }

        $second = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        $calledAgain = app(CallNextTicket::class)->handle($attendant);
        $this->assertTrue($calledAgain->is($ticket) || $calledAgain->is($second));

        if ($calledAgain->is($ticket)) {
            app(TransferTicket::class)->handle($attendant, $calledAgain, TicketTransferType::QUEUE);
        } else {
            app(MarkTicketNoShow::class)->handle($attendant, $calledAgain);
            $calledTicket = app(CallNextTicket::class)->handle($attendant);
            app(TransferTicket::class)->handle($attendant, $calledTicket, TicketTransferType::QUEUE);

            try {
                app(MarkTicketNoShow::class)->handle($attendant, $calledTicket);
                $this->fail('No-show after transfer must fail.');
            } catch (ValidationException) {
                $this->assertSame(TicketStatus::WAITING, $calledTicket->fresh()->status);
            }
        }
    }

    public function test_attendant_panel_transfer_modal_transfers_to_queue(): void
    {
        [$attendant, $unit, $desk, $type, $admin] = $this->readyContext();
        $this->actingAs($attendant);
        $ticket = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        $this->claimContext($attendant, $unit, $desk);
        app(CallNextTicket::class)->handle($attendant);

        Livewire::actingAs($attendant)
            ->test(AttendantPanel::class)
            ->call('openTransferModal')
            ->assertSet('showTransferModal', true)
            ->set('transferDestination', 'queue')
            ->set('transferReason', 'Encaminhado para triagem')
            ->call('transfer')
            ->assertSet('showTransferModal', false)
            ->assertSee('transferida para a fila');

        $this->assertSame(TicketStatus::WAITING, $ticket->fresh()->status);
        $this->assertDatabaseHas('ticket_transfers', [
            'ticket_id' => $ticket->id,
            'transfer_type' => TicketTransferType::QUEUE->value,
            'reason' => 'Encaminhado para triagem',
        ]);
    }

    /**
     * @return array{0: User, 1: Unit, 2: Desk, 3: TicketType, 4: User}
     */
    private function readyContext(): array
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create();
        $desk = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'name' => 'Mesa 04',
            'code' => 'M04',
        ]);
        $type = $this->type($clinic, 'Preferencial', 'P', 20);
        $attendant = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ATTENDANT,
        ]);
        $attendant->units()->detach();
        $attendant->units()->attach($unit, ['clinic_id' => $clinic->id]);
        $admin = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);

        return [$attendant, $unit, $desk, $type, $admin];
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
