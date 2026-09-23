<?php

namespace Tests\Feature;

use App\Actions\EnsureClinicRolePermissions;
use App\Actions\SaveClinicRolePermissions;
use App\Models\Clinic;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionImmediateEffectTest extends TestCase
{
    use RefreshDatabase;

    public function test_permission_change_applies_on_next_request_without_relogin(): void
    {
        $clinic = Clinic::factory()->create();
        app(EnsureClinicRolePermissions::class)->handle($clinic);
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);
        $supervisor = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::SUPERVISOR]);

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

        $this->actingAs($supervisor)->get(route('users.index'))->assertOk();

        app(SaveClinicRolePermissions::class)->handle($admin, $clinic, UserRole::SUPERVISOR, [
            'dashboard.view',
            'attendant.access',
            'tickets.call',
            'tickets.recall',
            'tickets.start',
            'tickets.complete',
            'tickets.no_show',
            'tickets.transfer',
        ]);

        $this->actingAs($supervisor->fresh())->get(route('users.index'))->assertForbidden();

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
}
