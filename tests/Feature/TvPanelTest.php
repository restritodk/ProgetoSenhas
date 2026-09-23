<?php

namespace Tests\Feature;

use App\Actions\CallNextTicket;
use App\Actions\ClaimDesk;
use App\Actions\IssueTicket;
use App\Actions\RecallTicket;
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
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TvPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_token_opens_panel_and_invalid_token_is_not_found(): void
    {
        $panel = $this->panel();

        $this->get(route('tv.panel', ['publicToken' => $panel->public_token]))
            ->assertOk()
            ->assertSee($panel->clinic->name)
            ->assertSee('Senha atual')
            ->assertSee('Últimas chamadas')
            ->assertSee('Aguardando chamada')
            ->assertDontSee('Informações')
            ->assertDontSee($panel->clinic->users()->first()?->email ?? 'never');

        $this->get(route('tv.panel', ['publicToken' => str_repeat('a', 64)]))->assertNotFound();
        $this->get('/painel/1')->assertNotFound();
    }

    public function test_inactive_panel_does_not_deliver_operational_feed(): void
    {
        $panel = $this->panel(['active' => false]);
        $feed = app(DisplayPanelFeed::class)->build($panel);

        $this->assertFalse($feed['available']);
        $this->assertNull($feed['current_call']);
        $this->assertSame([], $feed['recent_calls']);

        Livewire::test(TvDisplay::class, ['publicToken' => $panel->public_token])
            ->assertSet('available', false)
            ->assertSee('Painel indisponível');
    }

    public function test_tv_shows_only_calls_from_its_unit_and_never_other_clinic(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $unitA = Unit::factory()->for($clinicA)->create();
        $unitB = Unit::factory()->for($clinicA)->create();
        $unitOtherClinic = Unit::factory()->for($clinicB)->create();

        $panel = DisplayPanel::factory()->create([
            'clinic_id' => $clinicA->id,
            'unit_id' => $unitA->id,
            'name' => 'TV A',
            'code' => 'TVA',
        ]);

        $adminA = User::factory()->create(['clinic_id' => $clinicA->id, 'role' => UserRole::ADMINISTRATOR]);
        $adminB = User::factory()->create(['clinic_id' => $clinicB->id, 'role' => UserRole::ADMINISTRATOR]);
        $typeA = $this->type($clinicA, 'Preferencial', 'P', 20);
        $typeB = $this->type($clinicB, 'Preferencial', 'P', 20);
        $deskA = Desk::factory()->create(['clinic_id' => $clinicA->id, 'unit_id' => $unitA->id, 'name' => 'Mesa 04', 'code' => 'M04']);
        $deskOtherUnit = Desk::factory()->create(['clinic_id' => $clinicA->id, 'unit_id' => $unitB->id, 'name' => 'Mesa 09', 'code' => 'M09']);
        $deskOtherClinic = Desk::factory()->create(['clinic_id' => $clinicB->id, 'unit_id' => $unitOtherClinic->id, 'name' => 'Mesa X', 'code' => 'MX']);

        $attendantA = $this->attendant($clinicA, $unitA);
        $attendantOtherUnit = $this->attendant($clinicA, $unitB);
        $attendantB = $this->attendant($clinicB, $unitOtherClinic);

        $this->actingAs($adminA);
        $ticketA = app(IssueTicket::class)->handle($adminA, $unitA->id, $typeA->id);
        $ticketOtherUnit = app(IssueTicket::class)->handle($adminA, $unitB->id, $typeA->id);

        $this->actingAs($adminB);
        $ticketB = app(IssueTicket::class)->handle($adminB, $unitOtherClinic->id, $typeB->id);

        $this->callOnDesk($attendantA, $unitA, $deskA);
        $this->callOnDesk($attendantOtherUnit, $unitB, $deskOtherUnit);
        $this->callOnDesk($attendantB, $unitOtherClinic, $deskOtherClinic);

        $feed = app(DisplayPanelFeed::class)->build($panel->fresh(['clinic', 'unit']));

        $this->assertTrue($feed['available']);
        $this->assertSame('Mesa 04', $feed['current_call']['desk_name']);
        $this->assertSame($ticketA->id, TicketCall::query()->whereKey($feed['current_call']['id'])->value('ticket_id'));
        $recentDeskNames = collect($feed['recent_calls'])->pluck('desk_name')->all();
        $this->assertContains('Mesa 04', $recentDeskNames);
        $this->assertNotContains('Mesa 09', $recentDeskNames);
        $this->assertNotContains('Mesa X', $recentDeskNames);

        $serialized = json_encode($feed);
        $this->assertIsString($serialized);
        $this->assertStringNotContainsString('@', $serialized);
        $this->assertStringNotContainsString('password', $serialized);
        $this->assertStringNotContainsString('"email"', $serialized);
        $this->assertStringNotContainsString('"called_by_user_id"', $serialized);
        $this->assertStringNotContainsString('"clinic_id"', $serialized);
        $this->assertStringNotContainsString('"unit_id"', $serialized);
    }

    public function test_initial_and_recall_are_distinct_events_for_tv_detection(): void
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create();
        $panel = DisplayPanel::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
        ]);
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);
        $type = $this->type($clinic, 'Preferencial', 'P', 20);
        $desk = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'name' => 'Mesa 04',
            'code' => 'M04',
        ]);
        $attendant = $this->attendant($clinic, $unit);

        $this->actingAs($admin);
        $ticket = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);

        $this->actingAs($attendant);
        app(OperationalContext::class)->setActiveUnit($attendant, $unit, session());
        app(ClaimDesk::class)->handle($attendant, $desk);
        $called = app(CallNextTicket::class)->handle($attendant);
        $this->assertTrue($called->is($ticket));

        $feed = app(DisplayPanelFeed::class);
        $first = $feed->build($panel->fresh(['clinic', 'unit']));
        $initialId = $first['current_call']['id'];
        $this->assertSame(TicketCallType::INITIAL->value, $first['current_call']['call_type']);

        app(RecallTicket::class)->handle($attendant, $called);

        $second = $feed->build($panel->fresh(['clinic', 'unit']));
        $this->assertNotSame($initialId, $second['current_call']['id']);
        $this->assertSame(TicketCallType::RECALL->value, $second['current_call']['call_type']);
        $this->assertSame($ticket->display_code, $second['current_call']['display_code']);
        $this->assertSame(2, TicketCall::query()->where('ticket_id', $ticket->id)->count());
        $this->assertCount(2, $second['recent_calls']);

        Livewire::test(TvDisplay::class, ['publicToken' => $panel->public_token])
            ->assertSee($ticket->display_code)
            ->assertSee('Mesa 04')
            ->tap(function () use ($attendant, $called): void {
                $this->actingAs($attendant);
                app(RecallTicket::class)->handle($attendant, $called);
            })
            ->call('refreshFeed')
            ->assertDispatched('tv-new-call');
    }

    private function panel(array $overrides = []): DisplayPanel
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create();

        return DisplayPanel::factory()->create(array_merge([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'name' => 'TV Recepção',
            'code' => 'TVREC',
        ], $overrides));
    }

    private function attendant(Clinic $clinic, Unit $unit): User
    {
        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ATTENDANT,
        ]);
        $user->units()->detach();
        $user->units()->attach($unit->id, ['clinic_id' => $clinic->id]);

        return $user;
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

    private function callOnDesk(User $attendant, Unit $unit, Desk $desk): void
    {
        $this->actingAs($attendant);
        app(OperationalContext::class)->setActiveUnit($attendant, $unit, session());
        app(ClaimDesk::class)->handle($attendant, $desk);
        app(CallNextTicket::class)->handle($attendant);
    }
}
