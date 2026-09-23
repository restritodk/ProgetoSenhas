<?php

namespace Tests\Feature;

use App\Actions\EnsureClinicRolePermissions;
use App\Actions\SaveClinicRolePermissions;
use App\Models\Clinic;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_receives_administrative_defaults(): void
    {
        [$clinic, $admin] = $this->seedClinicWithRoles();

        foreach (PermissionCatalog::defaultsFor(UserRole::ADMINISTRATOR) as $key) {
            $this->assertTrue($admin->hasPermission($key), $key);
        }

        $this->assertTrue($admin->hasPermission('roles.update'));
        $this->assertTrue($admin->hasPermission('users.view'));
        $this->assertTrue($admin->hasPermission('queue_policy.update'));
    }

    public function test_supervisor_and_attendant_receive_operational_defaults(): void
    {
        [$clinic, , $supervisor, $attendant] = $this->seedClinicWithRoles();

        foreach ([UserRole::SUPERVISOR, UserRole::ATTENDANT] as $role) {
            $user = $role === UserRole::SUPERVISOR ? $supervisor : $attendant;
            foreach (PermissionCatalog::defaultsFor($role) as $key) {
                $this->assertTrue($user->hasPermission($key), $role->value.' '.$key);
            }
            $this->assertFalse($user->hasPermission('users.view'));
            $this->assertFalse($user->hasPermission('settings.update'));
            $this->assertTrue($user->hasPermission('tickets.call'));
            $this->assertTrue($user->canAccessAttendantPanel());
        }
    }

    public function test_ensure_is_idempotent_and_preserves_existing_grants(): void
    {
        [$clinic, $admin] = $this->seedClinicWithRoles();

        app(SaveClinicRolePermissions::class)->handle(
            $admin,
            $clinic,
            UserRole::ATTENDANT,
            ['dashboard.view', 'attendant.access', 'tickets.call'],
        );

        app(EnsureClinicRolePermissions::class)->handle($clinic);

        $attendant = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ATTENDANT,
        ]);

        $this->assertTrue($attendant->hasPermission('tickets.call'));
        $this->assertFalse($attendant->hasPermission('tickets.transfer'));
    }

    /**
     * @return array{0: Clinic, 1: User, 2: User, 3: User}
     */
    private function seedClinicWithRoles(): array
    {
        $clinic = Clinic::factory()->create();
        app(EnsureClinicRolePermissions::class)->handle($clinic);

        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);
        $supervisor = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::SUPERVISOR]);
        $attendant = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ATTENDANT]);

        return [$clinic, $admin, $supervisor, $attendant];
    }
}
