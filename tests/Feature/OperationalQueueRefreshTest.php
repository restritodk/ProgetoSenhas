<?php

namespace Tests\Feature;

use App\Actions\CallNextTicket;
use App\Actions\ClaimDesk;
use App\Actions\IssueTicket;
use App\Livewire\AttendantDeskQueue;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OperationalQueueRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_issued_waiting_ticket_appears_in_attendant_panel_and_desk_queue(): void
    {
        [$attendant, $unit, $desk, $admin, $type] = $this->ready();

        $this->actingAs($admin);
        $ticket = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);

        $this->assertSame(TicketStatus::WAITING, $ticket->status);

        $this->claim($attendant, $unit, $desk);

        Livewire::actingAs($attendant)
            ->test(AttendantPanel::class)
            ->call('refreshPanel')
            ->assertSee($ticket->display_code);

        $this->assertTrue(
            Livewire::actingAs($attendant)
                ->test(AttendantPanel::class)
                ->instance()
                ->upcomingQueue
                ->contains(fn (Ticket $row): bool => $row->id === $ticket->id)
        );

        Livewire::actingAs($attendant)
            ->test(AttendantDeskQueue::class)
            ->call('refreshQueue')
            ->assertSee($ticket->display_code);

        $this->assertTrue(
            Livewire::actingAs($attendant)
                ->test(AttendantDeskQueue::class)
                ->instance()
                ->waitingTickets
                ->contains(fn (Ticket $row): bool => $row->id === $ticket->id)
        );
    }

    public function test_upcoming_queue_and_recent_calls_are_capped_at_five(): void
    {
        [$attendant, $unit, $desk, $admin, $type] = $this->ready();
        $this->actingAs($admin);

        $issued = [];
        for ($i = 0; $i < 8; $i++) {
            $issued[] = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        }

        $this->claim($attendant, $unit, $desk);

        $upcoming = Livewire::actingAs($attendant)
            ->test(AttendantPanel::class)
            ->instance()
            ->upcomingQueue;

        $this->assertCount(5, $upcoming);
        $this->assertTrue($upcoming->every(fn (Ticket $ticket): bool => $ticket->status === TicketStatus::WAITING));

        for ($i = 0; $i < 7; $i++) {
            $called = app(CallNextTicket::class)->handle($attendant);
            $this->assertNotNull($called);
            $called->forceFill([
                'status' => TicketStatus::COMPLETED,
                'completed_at' => now(),
                'current_desk_id' => null,
            ])->save();
        }

        $this->assertGreaterThanOrEqual(7, TicketCall::query()->where('unit_id', $unit->id)->count());

        $recent = Livewire::actingAs($attendant)
            ->test(AttendantPanel::class)
            ->instance()
            ->recentHistory;

        $this->assertCount(5, $recent);
        $this->assertTrue($recent->every(fn (array $row): bool => $row['kind'] === 'call'));
        $this->assertSame(TicketCallType::INITIAL->label(), $recent->first()['event_label']);
    }

    /**
     * @return array{0: User, 1: Unit, 2: Desk, 3: User, 4: TicketType}
     */
    private function ready(): array
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create();
        $desk = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'name' => 'Mesa 01',
            'code' => 'M01',
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
            'name' => 'Normal',
            'prefix' => 'N',
            'priority' => 10,
            'active' => true,
        ])->save();

        return [$attendant, $unit, $desk, $admin, $type->refresh()];
    }

    private function claim(User $attendant, Unit $unit, Desk $desk): void
    {
        $this->actingAs($attendant);
        app(OperationalContext::class)->setActiveUnit($attendant, $unit, session());
        app(ClaimDesk::class)->handle($attendant, $desk);
    }
}
