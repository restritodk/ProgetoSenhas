<?php

namespace Tests\Feature;

use App\Actions\EnsureClinicRolePermissions;
use App\Actions\SaveClinicRolePermissions;
use App\Livewire\RolePermissionsManager;
use App\Models\Clinic;
use App\Models\ClinicRolePermission;
use App\Models\Permission;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RolePermissionLivewireTest extends TestCase
{
    use RefreshDatabase;

    public function test_loads_existing_permissions_into_editor_state(): void
    {
        [$admin] = $this->adminClinic();
        $defaults = PermissionCatalog::defaultsFor(UserRole::ATTENDANT);

        $component = Livewire::actingAs($admin)
            ->test(RolePermissionsManager::class)
            ->call('editRole', UserRole::ATTENDANT->value);

        $this->assertEqualsCanonicalizing($defaults, $component->get('selectedPermissions'));
        $this->assertEqualsCanonicalizing($defaults, $component->get('originalPermissions'));
        $this->assertFalse($component->instance()->isDirty());
    }

    public function test_marking_a_permission_updates_state_without_persisting(): void
    {
        [$admin, $clinic] = $this->adminClinic();

        $component = Livewire::actingAs($admin)
            ->test(RolePermissionsManager::class)
            ->call('editRole', UserRole::ATTENDANT->value);

        $before = $component->get('selectedPermissions');
        $this->assertNotContains('users.view', $before);

        $component->set('selectedPermissions', [...$before, 'users.view']);

        $this->assertContains('users.view', $component->get('selectedPermissions'));
        $this->assertTrue($component->instance()->isDirty());
        $this->assertFalse($this->roleHasPermission($clinic->id, UserRole::ATTENDANT, 'users.view'));
    }

    public function test_unmarking_a_permission_updates_state(): void
    {
        [$admin] = $this->adminClinic();

        $component = Livewire::actingAs($admin)
            ->test(RolePermissionsManager::class)
            ->call('editRole', UserRole::ATTENDANT->value);

        $before = $component->get('selectedPermissions');
        $this->assertContains('tickets.transfer', $before);

        $component->set(
            'selectedPermissions',
            array_values(array_filter($before, fn (string $key): bool => $key !== 'tickets.transfer')),
        );

        $this->assertNotContains('tickets.transfer', $component->get('selectedPermissions'));
        $this->assertTrue($component->instance()->isDirty());
    }

    public function test_module_counter_reflects_selection(): void
    {
        [$admin] = $this->adminClinic();

        $component = Livewire::actingAs($admin)
            ->test(RolePermissionsManager::class)
            ->call('editRole', UserRole::SUPERVISOR->value);

        $userKeys = collect(PermissionCatalog::definitions())
            ->where('module', 'users')
            ->pluck('key')
            ->all();

        $selected = array_values(array_filter(
            $component->get('selectedPermissions'),
            fn (string $key): bool => ! in_array($key, $userKeys, true),
        ));
        $component->set('selectedPermissions', $selected);
        $this->assertSame(0, count(array_intersect($component->get('selectedPermissions'), $userKeys)));

        $component->set('selectedPermissions', [...$component->get('selectedPermissions'), 'users.view']);
        $this->assertSame(1, count(array_intersect($component->get('selectedPermissions'), $userKeys)));

        $component->call('selectModule', 'users');
        $this->assertSame(count($userKeys), count(array_intersect($component->get('selectedPermissions'), $userKeys)));
    }

    public function test_marking_dependent_auto_enables_requirement(): void
    {
        [$admin] = $this->adminClinic();

        $component = Livewire::actingAs($admin)
            ->test(RolePermissionsManager::class)
            ->call('editRole', UserRole::ATTENDANT->value);

        $withoutUsers = array_values(array_filter(
            $component->get('selectedPermissions'),
            fn (string $key): bool => ! str_starts_with($key, 'users.'),
        ));
        $component->set('selectedPermissions', $withoutUsers);
        $this->assertNotContains('users.view', $component->get('selectedPermissions'));

        $component->set('selectedPermissions', [...$component->get('selectedPermissions'), 'users.create']);

        $this->assertContains('users.create', $component->get('selectedPermissions'));
        $this->assertContains('users.view', $component->get('selectedPermissions'));
    }

    public function test_unchecking_requirement_opens_cascade_confirm_and_removes_dependents(): void
    {
        [$admin] = $this->adminClinic();

        $component = Livewire::actingAs($admin)
            ->test(RolePermissionsManager::class)
            ->call('editRole', UserRole::SUPERVISOR->value)
            ->call('selectModule', 'users');

        $this->assertContains('users.view', $component->get('selectedPermissions'));
        $this->assertContains('users.create', $component->get('selectedPermissions'));

        $withoutView = array_values(array_filter(
            $component->get('selectedPermissions'),
            fn (string $key): bool => $key !== 'users.view',
        ));
        $component->set('selectedPermissions', $withoutView);

        $this->assertTrue($component->get('showUncheckDependentsConfirm'));
        $this->assertContains('users.view', $component->get('selectedPermissions'));
        $this->assertSame('users.view', $component->get('pendingUncheckKey'));

        $component->call('confirmUncheckDependents');

        $this->assertFalse($component->get('showUncheckDependentsConfirm'));
        $this->assertNotContains('users.view', $component->get('selectedPermissions'));
        $this->assertNotContains('users.create', $component->get('selectedPermissions'));
        $this->assertNotContains('users.update', $component->get('selectedPermissions'));
    }

    public function test_select_all_marks_module_without_persisting(): void
    {
        [$admin, $clinic] = $this->adminClinic();

        $component = Livewire::actingAs($admin)
            ->test(RolePermissionsManager::class)
            ->call('editRole', UserRole::ATTENDANT->value)
            ->call('selectModule', 'users');

        $userKeys = collect(PermissionCatalog::definitions())
            ->where('module', 'users')
            ->pluck('key')
            ->all();

        foreach ($userKeys as $key) {
            $this->assertContains($key, $component->get('selectedPermissions'));
        }
        $this->assertTrue($component->instance()->isDirty());
        $this->assertFalse($this->roleHasPermission($clinic->id, UserRole::ATTENDANT, 'users.create'));
    }

    public function test_clear_module_removes_removable_keys(): void
    {
        [$admin] = $this->adminClinic();

        $component = Livewire::actingAs($admin)
            ->test(RolePermissionsManager::class)
            ->call('editRole', UserRole::ATTENDANT->value)
            ->call('selectModule', 'users')
            ->call('clearModule', 'users');

        $userKeys = collect(PermissionCatalog::definitions())
            ->where('module', 'users')
            ->pluck('key')
            ->all();

        foreach ($userKeys as $key) {
            $this->assertNotContains($key, $component->get('selectedPermissions'));
        }
    }

    public function test_protected_administrator_permission_cannot_be_removed_from_state(): void
    {
        [$admin] = $this->adminClinic();

        $component = Livewire::actingAs($admin)
            ->test(RolePermissionsManager::class)
            ->call('editRole', UserRole::ADMINISTRATOR->value);

        $withoutProtected = array_values(array_filter(
            $component->get('selectedPermissions'),
            fn (string $key): bool => $key !== 'roles.update',
        ));
        $component->set('selectedPermissions', $withoutProtected);

        $this->assertContains('roles.update', $component->get('selectedPermissions'));
        $this->assertNotSame('', $component->get('errorMessage'));
    }

    public function test_dirty_state_clears_when_selection_matches_original(): void
    {
        [$admin] = $this->adminClinic();

        $component = Livewire::actingAs($admin)
            ->test(RolePermissionsManager::class)
            ->call('editRole', UserRole::ATTENDANT->value);

        $original = $component->get('originalPermissions');
        $component->set('selectedPermissions', [...$original, 'users.view']);
        $this->assertTrue($component->instance()->isDirty());

        $component->set('selectedPermissions', $original);
        $this->assertFalse($component->instance()->isDirty());
    }

    public function test_discard_restores_original_selection(): void
    {
        [$admin] = $this->adminClinic();

        $component = Livewire::actingAs($admin)
            ->test(RolePermissionsManager::class)
            ->call('editRole', UserRole::ATTENDANT->value);

        $original = $component->get('originalPermissions');
        $component->set('selectedPermissions', [...$original, 'users.view']);
        $this->assertNotEqualsCanonicalizing($original, $component->get('selectedPermissions'));

        $component->call('discardChanges');
        $this->assertEqualsCanonicalizing($original, $component->get('selectedPermissions'));
        $this->assertFalse($component->instance()->isDirty());
    }

    public function test_search_preserves_temporary_selection(): void
    {
        [$admin] = $this->adminClinic();

        $component = Livewire::actingAs($admin)
            ->test(RolePermissionsManager::class)
            ->call('editRole', UserRole::ATTENDANT->value);

        $before = $component->get('selectedPermissions');
        $component->set('selectedPermissions', [...$before, 'users.view']);
        $component->set('search', 'senha');
        $component->assertDontSee('Visualizar usuários');
        $this->assertContains('users.view', $component->get('selectedPermissions'));

        $component->set('search', '');
        $component->assertSee('Visualizar usuários');
        $this->assertContains('users.view', $component->get('selectedPermissions'));
    }

    public function test_save_persists_permissions(): void
    {
        [$admin, $clinic] = $this->adminClinic();

        $component = Livewire::actingAs($admin)
            ->test(RolePermissionsManager::class)
            ->assertSee('Perfis e Permissões')
            ->call('editRole', UserRole::SUPERVISOR->value);

        $selected = [...$component->get('selectedPermissions'), 'users.view'];

        $component
            ->set('selectedPermissions', $selected)
            ->call('confirmSave')
            ->call('save')
            ->assertSet('statusMessage', 'Permissões do perfil "Supervisor" atualizadas com sucesso.');

        $this->assertTrue($this->roleHasPermission($clinic->id, UserRole::SUPERVISOR, 'users.view'));

        $supervisor = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::SUPERVISOR]);
        $this->assertTrue($supervisor->hasPermission('users.view'));
    }

    public function test_user_without_roles_update_cannot_change_permissions(): void
    {
        $clinic = Clinic::factory()->create();
        app(EnsureClinicRolePermissions::class)->handle($clinic);
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);

        app(SaveClinicRolePermissions::class)->handle($admin, $clinic, UserRole::SUPERVISOR, [
            'dashboard.view',
            'attendant.access',
            'tickets.call',
            'tickets.recall',
            'tickets.start',
            'tickets.complete',
            'tickets.no_show',
            'tickets.transfer',
            'roles.view',
        ]);

        $supervisor = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::SUPERVISOR]);
        $this->assertTrue($supervisor->fresh()->hasPermission('roles.view'));
        $this->assertFalse($supervisor->fresh()->hasPermission('roles.update'));

        Livewire::actingAs($supervisor->fresh())
            ->test(RolePermissionsManager::class)
            ->call('editRole', UserRole::ATTENDANT->value)
            ->set('selectedPermissions', ['dashboard.view'])
            ->assertForbidden();
    }

    public function test_accordion_toggle_preserves_selection(): void
    {
        [$admin] = $this->adminClinic();

        $component = Livewire::actingAs($admin)
            ->test(RolePermissionsManager::class)
            ->call('editRole', UserRole::ATTENDANT->value);

        $before = $component->get('selectedPermissions');
        $component->set('selectedPermissions', [...$before, 'users.view']);
        $component->call('toggleModule', 'users');
        $component->call('toggleModule', 'users');

        $this->assertContains('users.view', $component->get('selectedPermissions'));
        $this->assertTrue($component->instance()->isDirty());
    }

    /**
     * @return array{0: User, 1: Clinic}
     */
    private function adminClinic(): array
    {
        $clinic = Clinic::factory()->create();
        app(EnsureClinicRolePermissions::class)->handle($clinic);
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);

        return [$admin, $clinic];
    }

    private function roleHasPermission(int $clinicId, UserRole $role, string $permissionKey): bool
    {
        $permissionId = Permission::query()->where('key', $permissionKey)->value('id');

        if ($permissionId === null) {
            return false;
        }

        return ClinicRolePermission::query()
            ->where('clinic_id', $clinicId)
            ->where('role', $role->value)
            ->where('permission_id', $permissionId)
            ->exists();
    }
}
