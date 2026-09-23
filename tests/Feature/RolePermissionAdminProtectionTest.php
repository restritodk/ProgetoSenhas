<?php

namespace Tests\Feature;

use App\Actions\EnsureClinicRolePermissions;
use App\Actions\SaveClinicRolePermissions;
use App\Models\Clinic;
use App\Models\ClinicRolePermission;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RolePermissionAdminProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_remove_protected_administrator_permissions(): void
    {
        $clinic = Clinic::factory()->create();
        app(EnsureClinicRolePermissions::class)->handle($clinic);
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);

        $before = ClinicRolePermission::query()
            ->where('clinic_id', $clinic->id)
            ->where('role', UserRole::ADMINISTRATOR->value)
            ->count();

        try {
            app(SaveClinicRolePermissions::class)->handle($admin, $clinic, UserRole::ADMINISTRATOR, [
                'dashboard.view',
            ]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('acesso administrativo', $exception->getMessage());
        }

        $after = ClinicRolePermission::query()
            ->where('clinic_id', $clinic->id)
            ->where('role', UserRole::ADMINISTRATOR->value)
            ->count();

        $this->assertSame($before, $after);
        $this->assertTrue($admin->fresh()->hasPermission('roles.update'));
        $this->assertTrue($admin->fresh()->hasPermission('users.view'));
    }

    public function test_protected_keys_are_always_present_when_saving_valid_admin_set(): void
    {
        $clinic = Clinic::factory()->create();
        app(EnsureClinicRolePermissions::class)->handle($clinic);
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);

        $keys = PermissionCatalog::defaultsFor(UserRole::ADMINISTRATOR);
        $saved = app(SaveClinicRolePermissions::class)->handle($admin, $clinic, UserRole::ADMINISTRATOR, $keys);

        foreach (PermissionCatalog::protectedAdministratorKeys() as $protected) {
            $this->assertContains($protected, $saved);
        }
    }
}
