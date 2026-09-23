<?php

namespace Tests\Feature;

use App\Actions\EnsureClinicRolePermissions;
use App\Actions\SaveClinicRolePermissions;
use App\Actions\SaveUnitQueuePolicy;
use App\Livewire\QueuePoliciesManager;
use App\Models\Clinic;
use App\Models\Unit;
use App\Models\User;
use App\QueueCriticalMode;
use App\UserRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RolePermissionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendant_without_users_view_is_denied_on_users_page(): void
    {
        [$clinic, , , $attendant] = $this->seedClinic();

        $this->actingAs($attendant)
            ->get(route('users.index'))
            ->assertRedirect(route('attendant.panel'));
    }

    public function test_attendant_without_queue_policy_update_cannot_save_policy(): void
    {
        [$clinic, $admin, , $attendant] = $this->seedClinic();
        $unit = Unit::factory()->for($clinic)->create();

        $this->expectException(AuthorizationException::class);

        app(SaveUnitQueuePolicy::class)->handle($attendant, $unit, [
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

    public function test_supervisor_with_queue_policy_view_can_open_page_but_not_save_without_update(): void
    {
        [$clinic, $admin, $supervisor] = $this->seedClinic();
        $unit = Unit::factory()->for($clinic)->create();

        app(SaveClinicRolePermissions::class)->handle($admin, $clinic, UserRole::SUPERVISOR, [
            'dashboard.view',
            'attendant.access',
            'tickets.call',
            'tickets.recall',
            'tickets.start',
            'tickets.complete',
            'tickets.no_show',
            'tickets.transfer',
            'queue_policy.view',
        ]);

        $this->actingAs($supervisor->fresh())
            ->get(route('queue-policies.index'))
            ->assertOk();

        Livewire::actingAs($supervisor->fresh())
            ->test(QueuePoliciesManager::class)
            ->set('selectedUnitId', $unit->id)
            ->call('save')
            ->assertForbidden();
    }

    public function test_granting_permission_allows_operation(): void
    {
        [$clinic, $admin, $supervisor] = $this->seedClinic();

        $this->actingAs($supervisor)->get(route('users.index'))->assertForbidden();

        app(SaveClinicRolePermissions::class)->handle($admin, $clinic, UserRole::SUPERVISOR, [
            'dashboard.view',
            'attendant.access',
            'tickets.call',
            'tickets.recall',
            'tickets.start',
            'tickets.complete',
            'tickets.no_show',
            'tickets.transfer',
            'users.view',
        ]);

        $this->actingAs($supervisor->fresh())->get(route('users.index'))->assertOk();
    }

    /**
     * @return array{0: Clinic, 1: User, 2: User, 3: User}
     */
    private function seedClinic(): array
    {
        $clinic = Clinic::factory()->create();
        app(EnsureClinicRolePermissions::class)->handle($clinic);
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);
        $supervisor = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::SUPERVISOR]);
        $attendant = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ATTENDANT]);

        return [$clinic, $admin, $supervisor, $attendant];
    }
}
