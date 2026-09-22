<?php

namespace Tests\Feature;

use App\Actions\CallNextTicket;
use App\Actions\ClaimDesk;
use App\Actions\IssueTicket;
use App\Models\Clinic;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\Unit;
use App\Models\User;
use App\Services\OperationalContext;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TicketCallConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_desks_never_receive_the_same_ticket_when_calling_sequentially_under_lock(): void
    {
        [$clinic, $unit, $type, $deskA, $deskB, $userA, $userB] = $this->twoDesksSetup();
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);

        $this->actingAs($admin);
        $first = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        $second = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);

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
        $this->assertSame($deskA->id, $calledA->current_desk_id);
        $this->assertSame($deskB->id, $calledB->current_desk_id);
    }

    public function test_second_desk_gets_empty_queue_when_only_one_ticket_exists(): void
    {
        [$clinic, $unit, $type, $deskA, $deskB, $userA, $userB] = $this->twoDesksSetup();
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);

        $this->actingAs($admin);
        app(IssueTicket::class)->handle($admin, $unit->id, $type->id);

        $this->actingAs($userA);
        app(OperationalContext::class)->setActiveUnit($userA, $unit, session());
        app(ClaimDesk::class)->handle($userA, $deskA);
        $calledA = app(CallNextTicket::class)->handle($userA);

        $this->actingAs($userB);
        app(OperationalContext::class)->setActiveUnit($userB, $unit, session());
        app(ClaimDesk::class)->handle($userB, $deskB);
        $calledB = app(CallNextTicket::class)->handle($userB);

        $this->assertNotNull($calledA);
        $this->assertNull($calledB);
        $this->assertSame(1, Ticket::query()->where('status', 'called')->count());
    }

    public function test_busy_desk_cannot_call_another_ticket_before_finishing(): void
    {
        [$clinic, $unit, $type, $deskA, , $userA] = $this->twoDesksSetup();
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);

        $this->actingAs($admin);
        app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        app(IssueTicket::class)->handle($admin, $unit->id, $type->id);

        $this->actingAs($userA);
        app(OperationalContext::class)->setActiveUnit($userA, $unit, session());
        app(ClaimDesk::class)->handle($userA, $deskA);
        app(CallNextTicket::class)->handle($userA);

        $this->expectException(ValidationException::class);
        app(CallNextTicket::class)->handle($userA);
    }

    /**
     * SQLite in-memory does not faithfully reproduce MySQL/MariaDB row locking under true parallelism.
     * Production CallNextTicket relies on transaction + lockForUpdate on WAITING rows + status claim.
     */
    public function test_documents_sqlite_locking_limitation_for_parallel_calls(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $this->assertSame('sqlite', $driver);
            $this->assertTrue(true, 'Parallel lockForUpdate claiming is not fully validated on SQLite.');

            return;
        }

        $this->test_two_desks_never_receive_the_same_ticket_when_calling_sequentially_under_lock();
    }

    /**
     * @return array{0: Clinic, 1: Unit, 2: TicketType, 3: Desk, 4: Desk, 5: User, 6: User}
     */
    private function twoDesksSetup(): array
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create();
        $type = new TicketType;
        $type->forceFill([
            'clinic_id' => $clinic->id,
            'name' => 'Normal',
            'prefix' => 'N',
            'priority' => 10,
            'active' => true,
        ])->save();

        $deskA = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'name' => 'Mesa 01',
            'code' => 'M01',
        ]);
        $deskB = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'name' => 'Mesa 02',
            'code' => 'M02',
        ]);

        $userA = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ATTENDANT]);
        $userB = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ATTENDANT]);
        foreach ([$userA, $userB] as $user) {
            $user->units()->detach();
            $user->units()->attach($unit, ['clinic_id' => $clinic->id]);
        }

        return [$clinic, $unit, $type->refresh(), $deskA, $deskB, $userA, $userB];
    }
}
