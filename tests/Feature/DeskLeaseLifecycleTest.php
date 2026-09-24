<?php

namespace Tests\Feature;

use App\Actions\CallNextTicket;
use App\Actions\ClaimDesk;
use App\Actions\EnsureClinicRolePermissions;
use App\Actions\IssueTicket;
use App\Actions\ReleaseDesk;
use App\Livewire\AttendantPanel;
use App\Models\Clinic;
use App\Models\Desk;
use App\Models\DeskAssignment;
use App\Models\TicketCall;
use App\Models\TicketType;
use App\Models\Unit;
use App\Models\User;
use App\Services\OperationalContext;
use App\Support\DeskLease;
use App\TicketStatus;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class DeskLeaseLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_second_user_cannot_claim_active_desk(): void
    {
        [$unit, $desk, $juliane, $renan] = $this->twoAttendantsOneDesk();

        $this->actingAs($juliane);
        app(OperationalContext::class)->setActiveUnit($juliane, $unit, session());
        app(ClaimDesk::class)->handle($juliane, $desk);

        $this->actingAs($renan);
        session()->flush();
        app(OperationalContext::class)->setActiveUnit($renan, $unit, session());

        $this->expectException(ValidationException::class);
        app(ClaimDesk::class)->handle($renan, $desk);
    }

    public function test_logout_releases_desk_immediately(): void
    {
        [$unit, $desk, $juliane, $renan] = $this->twoAttendantsOneDesk();

        $this->actingAs($juliane);
        app(OperationalContext::class)->setActiveUnit($juliane, $unit, session());
        app(ClaimDesk::class)->handle($juliane, $desk);
        $this->assertDatabaseHas('desk_assignments', [
            'desk_id' => $desk->id,
            'user_id' => $juliane->id,
        ]);

        $this->assertLoggedOutFeedback($this->post(route('logout')));
        $this->assertGuest();
        $this->assertDatabaseMissing('desk_assignments', [
            'desk_id' => $desk->id,
            'user_id' => $juliane->id,
        ]);

        $this->actingAs($renan);
        app(OperationalContext::class)->setActiveUnit($renan, $unit, session());
        app(ClaimDesk::class)->handle($renan, $desk);

        $this->assertDatabaseHas('desk_assignments', [
            'desk_id' => $desk->id,
            'user_id' => $renan->id,
        ]);
    }

    public function test_expired_lease_can_be_reclaimed_without_deleting_ticket_history(): void
    {
        [$unit, $desk, $juliane, $renan, $type, $admin] = $this->twoAttendantsOneDesk(withType: true);

        $this->actingAs($juliane);
        app(OperationalContext::class)->setActiveUnit($juliane, $unit, session());
        app(ClaimDesk::class)->handle($juliane, $desk);

        $this->actingAs($admin);
        $ticket = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);

        $this->actingAs($juliane);
        $called = app(CallNextTicket::class)->handle($juliane);
        $this->assertTrue($called->is($ticket));

        $callCount = TicketCall::query()->where('ticket_id', $ticket->id)->count();
        $this->assertGreaterThan(0, $callCount);

        DeskAssignment::query()->where('desk_id', $desk->id)->update([
            'last_seen_at' => now()->subSeconds(DeskLease::ttlSeconds() + 10),
        ]);

        $this->actingAs($renan);
        session()->flush();
        app(OperationalContext::class)->setActiveUnit($renan, $unit, session());
        app(ClaimDesk::class)->handle($renan, $desk);

        $this->assertDatabaseHas('desk_assignments', [
            'desk_id' => $desk->id,
            'user_id' => $renan->id,
        ]);
        $this->assertDatabaseMissing('desk_assignments', [
            'desk_id' => $desk->id,
            'user_id' => $juliane->id,
        ]);

        $this->assertSame($callCount, TicketCall::query()->where('ticket_id', $ticket->id)->count());
        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'status' => TicketStatus::CALLED->value,
        ]);
    }

    public function test_active_heartbeat_keeps_lease_and_blocks_others(): void
    {
        [$unit, $desk, $juliane, $renan] = $this->twoAttendantsOneDesk();

        $this->actingAs($juliane);
        app(OperationalContext::class)->setActiveUnit($juliane, $unit, session());
        app(ClaimDesk::class)->handle($juliane, $desk);

        $this->travel(40)->seconds();
        $still = app(OperationalContext::class)->activeDesk($juliane, session());
        $this->assertNotNull($still);
        $this->assertSame($desk->id, $still->id);

        $this->actingAs($renan);
        session()->flush();
        app(OperationalContext::class)->setActiveUnit($renan, $unit, session());

        $this->expectException(ValidationException::class);
        app(ClaimDesk::class)->handle($renan, $desk);
    }

    public function test_concurrent_claim_only_one_wins(): void
    {
        [$unit, $desk, $juliane, $renan] = $this->twoAttendantsOneDesk();

        $this->actingAs($juliane);
        app(OperationalContext::class)->setActiveUnit($juliane, $unit, session());
        app(ClaimDesk::class)->handle($juliane, $desk);

        $this->actingAs($renan);
        session()->flush();
        app(OperationalContext::class)->setActiveUnit($renan, $unit, session());

        try {
            app(ClaimDesk::class)->handle($renan, $desk);
            $this->fail('Second claim should have failed');
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame(1, DeskAssignment::query()->where('desk_id', $desk->id)->count());
        $this->assertSame($juliane->id, (int) DeskAssignment::query()->where('desk_id', $desk->id)->value('user_id'));
    }

    public function test_user_cannot_release_another_users_desk_via_release_action(): void
    {
        [$unit, $desk, $juliane, $renan] = $this->twoAttendantsOneDesk();

        $this->actingAs($juliane);
        app(OperationalContext::class)->setActiveUnit($juliane, $unit, session());
        app(ClaimDesk::class)->handle($juliane, $desk);

        $this->actingAs($renan);
        app(ReleaseDesk::class)->handle($renan, $desk);

        $this->assertDatabaseHas('desk_assignments', [
            'desk_id' => $desk->id,
            'user_id' => $juliane->id,
        ]);
    }

    public function test_cannot_claim_desk_outside_authorized_unit(): void
    {
        $clinic = Clinic::factory()->create();
        app(EnsureClinicRolePermissions::class)->handle($clinic);
        $unitA = Unit::factory()->for($clinic)->create();
        $unitB = Unit::factory()->for($clinic)->create();
        $deskB = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unitB->id,
        ]);
        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ATTENDANT,
        ]);
        $user->units()->detach();
        $user->units()->attach($unitA->id, ['clinic_id' => $clinic->id]);

        $this->actingAs($user);
        app(OperationalContext::class)->setActiveUnit($user, $unitA, session());

        $this->expectException(ValidationException::class);
        app(ClaimDesk::class)->handle($user, $deskB);
    }

    public function test_switching_desk_releases_previous_claim(): void
    {
        [$unit, $deskA, $juliane] = $this->oneAttendantTwoDesks();
        $deskB = Desk::factory()->create([
            'clinic_id' => $juliane->clinic_id,
            'unit_id' => $unit->id,
            'name' => 'Guichê 02',
            'code' => 'G02',
        ]);

        $this->actingAs($juliane);
        app(OperationalContext::class)->setActiveUnit($juliane, $unit, session());
        app(ClaimDesk::class)->handle($juliane, $deskA);
        app(ClaimDesk::class)->handle($juliane, $deskB);

        $this->assertDatabaseMissing('desk_assignments', ['desk_id' => $deskA->id]);
        $this->assertDatabaseHas('desk_assignments', [
            'desk_id' => $deskB->id,
            'user_id' => $juliane->id,
        ]);
    }

    public function test_switching_unit_releases_previous_desk(): void
    {
        $clinic = Clinic::factory()->create();
        app(EnsureClinicRolePermissions::class)->handle($clinic);
        $unitA = Unit::factory()->for($clinic)->create();
        $unitB = Unit::factory()->for($clinic)->create();
        $deskA = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unitA->id,
        ]);
        Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unitB->id,
        ]);
        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ATTENDANT,
        ]);
        $user->units()->detach();
        $user->units()->attach([
            $unitA->id => ['clinic_id' => $clinic->id],
            $unitB->id => ['clinic_id' => $clinic->id],
        ]);

        Livewire::actingAs($user)
            ->test(AttendantPanel::class)
            ->call('selectUnit', $unitA->id)
            ->call('selectDesk', $deskA->id)
            ->call('selectUnit', $unitB->id);

        $this->assertDatabaseMissing('desk_assignments', [
            'desk_id' => $deskA->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_selection_ui_shows_expired_claim_as_available(): void
    {
        [$unit, $desk, $juliane, $renan] = $this->twoAttendantsOneDesk();

        $this->actingAs($juliane);
        app(OperationalContext::class)->setActiveUnit($juliane, $unit, session());
        app(ClaimDesk::class)->handle($juliane, $desk);

        DeskAssignment::query()->where('desk_id', $desk->id)->update([
            'last_seen_at' => now()->subSeconds(DeskLease::ttlSeconds() + 5),
        ]);

        session()->flush();
        $this->actingAs($renan);
        app(OperationalContext::class)->setActiveUnit($renan, $unit, session());

        Livewire::actingAs($renan)
            ->test(AttendantPanel::class)
            ->assertSee('Disponível')
            ->assertDontSee('Em uso');
    }

    public function test_logout_without_active_desk_still_works(): void
    {
        $clinic = Clinic::factory()->create();
        app(EnsureClinicRolePermissions::class)->handle($clinic);
        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ATTENDANT,
        ]);

        $this->actingAs($user);
        $this->assertLoggedOutFeedback($this->post(route('logout')));
        $this->assertGuest();
    }

    /**
     * @return array{0: Unit, 1: Desk, 2: User, 3: User}|array{0: Unit, 1: Desk, 2: User, 3: User, 4: TicketType, 5: User}
     */
    private function twoAttendantsOneDesk(bool $withType = false): array
    {
        $clinic = Clinic::factory()->create();
        app(EnsureClinicRolePermissions::class)->handle($clinic);
        $unit = Unit::factory()->for($clinic)->create();
        $desk = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'name' => 'Guichê 01',
            'code' => 'G01',
        ]);

        $juliane = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ATTENDANT,
            'email' => 'juliane.test@example.com',
        ]);
        $renan = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ATTENDANT,
            'email' => 'renan.test@example.com',
        ]);
        foreach ([$juliane, $renan] as $user) {
            $user->units()->detach();
            $user->units()->attach($unit->id, ['clinic_id' => $clinic->id]);
        }

        if (! $withType) {
            return [$unit, $desk, $juliane, $renan];
        }

        $admin = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);
        $admin->units()->attach($unit->id, ['clinic_id' => $clinic->id]);

        $type = TicketType::factory()->create([
            'clinic_id' => $clinic->id,
            'prefix' => 'N',
            'priority' => 10,
        ]);

        return [$unit, $desk, $juliane, $renan, $type, $admin];
    }

    /**
     * @return array{0: Unit, 1: Desk, 2: User}
     */
    private function oneAttendantTwoDesks(): array
    {
        $clinic = Clinic::factory()->create();
        app(EnsureClinicRolePermissions::class)->handle($clinic);
        $unit = Unit::factory()->for($clinic)->create();
        $desk = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'name' => 'Guichê 01',
            'code' => 'G01',
        ]);
        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ATTENDANT,
        ]);
        $user->units()->detach();
        $user->units()->attach($unit->id, ['clinic_id' => $clinic->id]);

        return [$unit, $desk, $user];
    }
}
