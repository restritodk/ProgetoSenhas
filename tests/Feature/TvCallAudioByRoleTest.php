<?php

namespace Tests\Feature;

use App\Actions\CallNextTicket;
use App\Actions\ClaimDesk;
use App\Actions\CreateSector;
use App\Actions\EnsureDefaultSectorForUnit;
use App\Actions\IssueTicket;
use App\Actions\RecallTicket;
use App\Actions\ReleaseDesk;
use App\Livewire\TvDisplay;
use App\Models\Clinic;
use App\Models\Desk;
use App\Models\DisplayPanel;
use App\Models\TicketCall;
use App\Models\TicketType;
use App\Models\Unit;
use App\Models\User;
use App\Services\DisplayPanelFeed;
use App\Services\OperationalContext;
use App\TicketCallType;
use App\TicketStatus;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TvCallAudioByRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendant_call_and_recall_appear_in_tv_feed_by_ticket_call_id(): void
    {
        [$panel, $unit, $desk, $attendant, $admin, $type] = $this->ready();

        $this->actingAs($admin);
        $ticket = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);

        $this->actingAs($attendant);
        app(OperationalContext::class)->setActiveUnit($attendant, $unit, session());
        app(ClaimDesk::class)->handle($attendant, $desk);

        $called = app(CallNextTicket::class)->handle($attendant);
        $this->assertTrue($called->is($ticket));

        $call = TicketCall::query()->where('ticket_id', $ticket->id)->where('call_type', TicketCallType::INITIAL)->firstOrFail();
        $feed = app(DisplayPanelFeed::class)->build($panel->fresh(['clinic', 'unit', 'sectors']));

        $this->assertSame($call->id, $feed['current_call']['id']);
        $this->assertNotEmpty($feed['current_call']['announcement']);
        $this->assertStringNotContainsString('"called_by_user_id"', json_encode($feed));
        $this->assertStringNotContainsString(UserRole::ATTENDANT->value, json_encode($feed['current_call']));

        Livewire::test(TvDisplay::class, ['publicToken' => $panel->public_token])
            ->assertSee($ticket->display_code)
            ->tap(function () use ($attendant, $called): void {
                $this->actingAs($attendant);
                app(RecallTicket::class)->handle($attendant, $called);
            })
            ->call('refreshFeed')
            ->assertDispatched('tv-new-call');

        $recall = TicketCall::query()
            ->where('ticket_id', $ticket->id)
            ->where('call_type', TicketCallType::RECALL)
            ->orderByDesc('id')
            ->firstOrFail();

        $afterRecall = app(DisplayPanelFeed::class)->build($panel->fresh(['clinic', 'unit', 'sectors']));
        $this->assertSame($recall->id, $afterRecall['current_call']['id']);
        $this->assertNotSame($call->id, $afterRecall['current_call']['id']);
    }

    public function test_admin_and_supervisor_calls_also_feed_tv_without_role_filter(): void
    {
        [$panel, $unit, $desk, , $admin, $type] = $this->ready();
        $supervisor = User::factory()->create([
            'clinic_id' => $admin->clinic_id,
            'role' => UserRole::SUPERVISOR,
        ]);
        $supervisor->units()->attach($unit->id, ['clinic_id' => $admin->clinic_id]);

        $this->actingAs($admin);
        app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        app(IssueTicket::class)->handle($admin, $unit->id, $type->id);

        foreach ([$admin, $supervisor] as $actor) {
            $this->actingAs($actor);
            app(OperationalContext::class)->setActiveUnit($actor, $unit, session());
            app(ClaimDesk::class)->handle($actor, $desk);
            $called = app(CallNextTicket::class)->handle($actor);
            $this->assertNotNull($called);

            $feed = app(DisplayPanelFeed::class)->build($panel->fresh(['clinic', 'unit', 'sectors']));
            $this->assertSame($called->display_code, $feed['current_call']['display_code']);
            $this->assertDatabaseHas('ticket_calls', [
                'ticket_id' => $called->id,
                'called_by_user_id' => $actor->id,
                'call_type' => TicketCallType::INITIAL->value,
            ]);

            $called->forceFill([
                'status' => TicketStatus::COMPLETED,
                'completed_at' => now(),
                'current_desk_id' => null,
            ])->save();
            app(ReleaseDesk::class)->handle($actor);
            app(OperationalContext::class)->clear(session());
        }
    }

    public function test_multiple_eligible_panels_independently_see_same_ticket_call(): void
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create(['name' => 'Hospital Toledo']);
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);
        $sector = app(EnsureDefaultSectorForUnit::class)->handle($unit);

        $panelA = DisplayPanel::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'name' => 'TV A',
            'active' => true,
        ]);
        $panelB = DisplayPanel::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'name' => 'TV B',
            'active' => true,
        ]);
        $panelA->sectors()->sync([$sector->id => ['clinic_id' => $clinic->id]]);
        $panelB->sectors()->sync([$sector->id => ['clinic_id' => $clinic->id]]);

        $desk = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'sector_id' => $sector->id,
        ]);
        $type = TicketType::factory()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Normal',
            'prefix' => 'N',
            'priority' => 10,
        ]);

        $this->actingAs($admin);
        $ticket = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        app(OperationalContext::class)->setActiveUnit($admin, $unit, session());
        app(ClaimDesk::class)->handle($admin, $desk);
        $called = app(CallNextTicket::class)->handle($admin);
        $this->assertTrue($called->is($ticket));

        $call = TicketCall::query()->where('ticket_id', $ticket->id)->firstOrFail();
        $feed = app(DisplayPanelFeed::class);

        $payloadA = $feed->build($panelA->fresh(['clinic', 'unit', 'sectors']));
        $payloadB = $feed->build($panelB->fresh(['clinic', 'unit', 'sectors']));

        $this->assertSame($call->id, $payloadA['current_call']['id']);
        $this->assertSame($call->id, $payloadB['current_call']['id']);
        $this->assertNotEmpty($payloadA['current_call']['announcement']);
        $this->assertNotEmpty($payloadB['current_call']['announcement']);

        // Consulting one panel must not consume the event for the other.
        Livewire::test(TvDisplay::class, ['publicToken' => $panelA->public_token])
            ->call('refreshFeed')
            ->assertSee($ticket->display_code);

        $stillB = $feed->build($panelB->fresh(['clinic', 'unit', 'sectors']));
        $this->assertSame($call->id, $stillB['current_call']['id']);
    }

    public function test_panel_on_other_unit_does_not_receive_call(): void
    {
        $clinic = Clinic::factory()->create();
        $unitA = Unit::factory()->for($clinic)->create(['name' => 'Hospital Toledo']);
        $unitB = Unit::factory()->for($clinic)->create(['name' => 'Recepção']);
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);

        $panelB = DisplayPanel::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unitB->id,
            'active' => true,
        ]);
        $panelB->sectors()->sync([
            app(EnsureDefaultSectorForUnit::class)->handle($unitB)->id => ['clinic_id' => $clinic->id],
        ]);

        $desk = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unitA->id,
        ]);
        $type = TicketType::factory()->create([
            'clinic_id' => $clinic->id,
            'prefix' => 'N',
            'priority' => 10,
        ]);

        $this->actingAs($admin);
        app(IssueTicket::class)->handle($admin, $unitA->id, $type->id);
        app(OperationalContext::class)->setActiveUnit($admin, $unitA, session());
        app(ClaimDesk::class)->handle($admin, $desk);
        app(CallNextTicket::class)->handle($admin);

        $feed = app(DisplayPanelFeed::class)->build($panelB->fresh(['clinic', 'unit', 'sectors']));
        $this->assertNull($feed['current_call']);
        $this->assertSame([], $feed['recent_calls']);
    }

    public function test_panel_on_other_sector_does_not_receive_call(): void
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create();
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);
        $recepcion = app(CreateSector::class)->handle($admin, [
            'name' => 'Recepção',
            'code' => 'REC',
            'unit_id' => $unit->id,
            'active' => true,
        ]);
        $exames = app(CreateSector::class)->handle($admin, [
            'name' => 'Exames',
            'code' => 'EXA',
            'unit_id' => $unit->id,
            'active' => true,
        ]);

        $panelExames = DisplayPanel::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'active' => true,
        ]);
        $panelExames->sectors()->sync([$exames->id => ['clinic_id' => $clinic->id]]);

        $desk = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'sector_id' => $recepcion->id,
        ]);
        $type = TicketType::factory()->create([
            'clinic_id' => $clinic->id,
            'prefix' => 'N',
            'priority' => 10,
        ]);

        $this->actingAs($admin);
        $ticket = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        $ticket->forceFill(['sector_id' => $recepcion->id])->save();

        app(OperationalContext::class)->setActiveUnit($admin, $unit, session());
        app(ClaimDesk::class)->handle($admin, $desk);
        app(CallNextTicket::class)->handle($admin);

        $feed = app(DisplayPanelFeed::class)->build($panelExames->fresh(['clinic', 'unit', 'sectors']));
        $this->assertNull($feed['current_call']);
    }

    public function test_tv_display_dispatches_new_call_for_each_ticket_call_id_including_admin(): void
    {
        [$panel, $unit, $desk, , $admin, $type] = $this->ready();

        $this->actingAs($admin);
        app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        app(OperationalContext::class)->setActiveUnit($admin, $unit, session());
        app(ClaimDesk::class)->handle($admin, $desk);

        $component = Livewire::test(TvDisplay::class, ['publicToken' => $panel->public_token]);

        app(CallNextTicket::class)->handle($admin);

        $component->call('refreshFeed')->assertDispatched('tv-new-call');

        $initialId = (int) $component->get('lastAnnouncedCallId');
        $this->assertGreaterThan(0, $initialId);

        $ticket = TicketCall::query()->whereKey($initialId)->firstOrFail()->ticket;
        app(RecallTicket::class)->handle($admin, $ticket->fresh());

        $component->call('refreshFeed')->assertDispatched('tv-new-call');
        $this->assertNotSame($initialId, (int) $component->get('lastAnnouncedCallId'));
    }

    /**
     * @return array{0: DisplayPanel, 1: Unit, 2: Desk, 3: User, 4: User, 5: TicketType}
     */
    private function ready(): array
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create();
        $sector = app(EnsureDefaultSectorForUnit::class)->handle($unit);
        $panel = DisplayPanel::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'active' => true,
        ]);
        $panel->sectors()->sync([$sector->id => ['clinic_id' => $clinic->id]]);
        $desk = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'sector_id' => $sector->id,
            'name' => 'Guichê 2',
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

        $type = new TicketType;
        $type->forceFill([
            'clinic_id' => $clinic->id,
            'name' => 'Emergencial',
            'prefix' => 'E',
            'priority' => 30,
            'active' => true,
        ])->save();

        return [$panel, $unit, $desk, $attendant, $admin, $type->refresh()];
    }
}
