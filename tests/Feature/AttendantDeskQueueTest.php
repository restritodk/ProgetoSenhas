<?php

namespace Tests\Feature;

use App\Actions\CallNextTicket;
use App\Actions\ClaimDesk;
use App\Actions\IssueTicket;
use App\Livewire\AttendantDeskQueue;
use App\Models\Clinic;
use App\Models\Desk;
use App\Models\TicketType;
use App\Models\Unit;
use App\Models\User;
use App\Services\OperationalContext;
use App\TicketStatus;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AttendantDeskQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_waiting_tickets_appear_with_dynamic_type_counts_and_search(): void
    {
        [$attendant, $unit, $desk, $admin] = $this->ready();
        $normal = $this->type($admin->clinic, 'Normal', 'N', 10);
        $preferential = $this->type($admin->clinic, 'Preferencial', 'P', 20);
        $retorno = $this->type($admin->clinic, 'Retorno', 'R', 15);

        $this->actingAs($admin);
        $n1 = app(IssueTicket::class)->handle($admin, $unit->id, $normal->id);
        $p1 = app(IssueTicket::class)->handle($admin, $unit->id, $preferential->id);
        app(IssueTicket::class)->handle($admin, $unit->id, $retorno->id);

        $this->claim($attendant, $unit, $desk);

        $component = Livewire::actingAs($attendant)->test(AttendantDeskQueue::class);

        $component
            ->assertSee($n1->display_code)
            ->assertSee($p1->display_code)
            ->assertSee('Retorno')
            ->assertSee('Preferencial')
            ->assertSee('Normal');

        $counts = $component->instance()->countsByType;
        $this->assertSame(1, (int) ($counts[$normal->id] ?? 0));
        $this->assertSame(1, (int) ($counts[$preferential->id] ?? 0));
        $this->assertSame(1, (int) ($counts[$retorno->id] ?? 0));

        $component
            ->set('search', 'Preferencial')
            ->assertSee($p1->display_code)
            ->assertDontSee($n1->display_code);

        $component
            ->set('search', $p1->display_code)
            ->assertSee($p1->display_code);
    }

    public function test_called_ticket_leaves_queue_and_other_unit_clinic_are_isolated(): void
    {
        [$attendant, $unit, $desk, $admin] = $this->ready();
        $type = $this->type($admin->clinic, 'Normal', 'N', 10);

        $otherUnit = Unit::factory()->for($admin->clinic)->create();
        $otherClinic = Clinic::factory()->create();
        $otherClinicUnit = Unit::factory()->for($otherClinic)->create();
        $otherAdmin = User::factory()->create([
            'clinic_id' => $otherClinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);
        $otherType = $this->type($otherClinic, 'Normal', 'N', 10);

        $this->actingAs($admin);
        $local = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        $otherUnitTicket = app(IssueTicket::class)->handle($admin, $otherUnit->id, $type->id);

        $this->actingAs($otherAdmin);
        $foreign = app(IssueTicket::class)->handle($otherAdmin, $otherClinicUnit->id, $otherType->id);

        $this->claim($attendant, $unit, $desk);

        $ids = Livewire::actingAs($attendant)
            ->test(AttendantDeskQueue::class)
            ->instance()
            ->waitingTickets
            ->pluck('id')
            ->all();

        $this->assertContains($local->id, $ids);
        $this->assertNotContains($otherUnitTicket->id, $ids);
        $this->assertNotContains($foreign->id, $ids);

        app(CallNextTicket::class)->handle($attendant);

        $idsAfter = Livewire::actingAs($attendant)
            ->test(AttendantDeskQueue::class)
            ->call('refreshQueue')
            ->instance()
            ->waitingTickets
            ->pluck('id')
            ->all();

        $this->assertNotContains($local->id, $idsAfter);

        $this->assertSame(TicketStatus::CALLED, $local->fresh()->status);
        $this->assertSame(TicketStatus::WAITING, $otherUnitTicket->fresh()->status);
    }

    public function test_empty_only_when_no_eligible_waiting_tickets(): void
    {
        [$attendant, $unit, $desk] = $this->ready();
        $this->claim($attendant, $unit, $desk);

        $waiting = Livewire::actingAs($attendant)
            ->test(AttendantDeskQueue::class)
            ->instance()
            ->waitingTickets;

        $this->assertCount(0, $waiting);
    }

    /**
     * @return array{0: User, 1: Unit, 2: Desk, 3: User}
     */
    private function ready(): array
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create();
        $desk = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
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

        return [$attendant, $unit, $desk, $admin];
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

    private function claim(User $attendant, Unit $unit, Desk $desk): void
    {
        $this->actingAs($attendant);
        app(OperationalContext::class)->setActiveUnit($attendant, $unit, session());
        app(ClaimDesk::class)->handle($attendant, $desk);
    }
}
