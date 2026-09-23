<?php

namespace Tests\Feature;

use App\Actions\EnsureClinicRolePermissions;
use App\Actions\SaveClinicRolePermissions;
use App\Models\Clinic;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RolePermissionTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_alter_foreign_clinic_role_permissions(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        app(EnsureClinicRolePermissions::class)->handle($clinicA);
        app(EnsureClinicRolePermissions::class)->handle($clinicB);

        $adminA = User::factory()->create(['clinic_id' => $clinicA->id, 'role' => UserRole::ADMINISTRATOR]);

        $this->expectException(HttpException::class);

        app(SaveClinicRolePermissions::class)->handle($adminA, $clinicB, UserRole::ATTENDANT, [
            'dashboard.view',
            'attendant.access',
            'tickets.call',
        ]);
    }

    public function test_admin_cannot_open_roles_page_of_other_tenant_data_via_own_session(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        app(EnsureClinicRolePermissions::class)->handle($clinicA);
        app(EnsureClinicRolePermissions::class)->handle($clinicB);

        $adminA = User::factory()->create(['clinic_id' => $clinicA->id, 'role' => UserRole::ADMINISTRATOR]);
        $adminB = User::factory()->create(['clinic_id' => $clinicB->id, 'role' => UserRole::ADMINISTRATOR]);

        app(SaveClinicRolePermissions::class)->handle($adminB, $clinicB, UserRole::SUPERVISOR, [
            'dashboard.view',
            'users.view',
        ]);

        $this->assertFalse(
            User::factory()->create(['clinic_id' => $clinicA->id, 'role' => UserRole::SUPERVISOR])->hasPermission('users.view'),
        );
        $this->assertTrue($adminA->hasPermission('roles.update'));
    }
}
