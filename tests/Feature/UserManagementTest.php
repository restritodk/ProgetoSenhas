<?php

namespace Tests\Feature;

use App\Actions\CreateUser;
use App\Actions\UpdateUser;
use App\Exceptions\LastAdministratorProtectedException;
use App\Livewire\UsersManager;
use App\Models\Clinic;
use App\Models\Unit;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_accesses_users_index(): void
    {
        $admin = $this->administrator();

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Usuários')
            ->assertSee('Novo usuário')
            ->assertSee(route('users.index'), false);

        $this->assertTrue($admin->can('viewAny', User::class));
        $this->assertTrue($admin->can('create', User::class));
    }

    #[DataProvider('nonAdministratorRoles')]
    public function test_non_administrator_cannot_access_users_module(UserRole $role): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => $role,
        ]);

        $response = $this->actingAs($user)->get(route('users.index'));
        if ($role === UserRole::ATTENDANT) {
            $response->assertRedirect(route('attendant.panel'));
        } else {
            $response->assertForbidden();
        }

        Livewire::actingAs($user)
            ->test(UsersManager::class)
            ->assertForbidden();

        $this->assertFalse($user->can('viewAny', User::class));
        $this->assertFalse($user->can('create', User::class));
    }

    public function test_administrator_lists_only_users_from_own_clinic(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $admin = $this->administrator($clinicA);
        $ownUser = User::factory()->create([
            'clinic_id' => $clinicA->id,
            'name' => 'Carla Própria',
            'email' => 'carla.propria@clinica.test',
            'role' => UserRole::ATTENDANT,
        ]);
        $foreignUser = User::factory()->create([
            'clinic_id' => $clinicB->id,
            'name' => 'Bruno Externo',
            'email' => 'bruno.externo@clinica.test',
            'role' => UserRole::SUPERVISOR,
        ]);

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Carla Própria')
            ->assertSee('carla.propria@clinica.test')
            ->assertDontSee('Bruno Externo')
            ->assertDontSee('bruno.externo@clinica.test');

        Livewire::actingAs($admin)
            ->test(UsersManager::class)
            ->assertSee('Carla Própria')
            ->assertDontSee('Bruno Externo');

        $this->assertTrue($admin->can('view', $ownUser));
        $this->assertFalse($admin->can('view', $foreignUser));
        $this->assertFalse($admin->can('update', $foreignUser));
    }

    public function test_administrator_creates_attendant_in_own_clinic_and_ignores_foreign_clinic_id(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $admin = $this->administrator($clinicA);

        Livewire::actingAs($admin)
            ->test(UsersManager::class)
            ->call('startCreate')
            ->set('name', 'Ana Atendente')
            ->set('email', 'Ana.Atendente@Clinica.TEST')
            ->set('role', UserRole::ATTENDANT->value)
            ->set('password', 'senha-inicial')
            ->set('password_confirmation', 'senha-inicial')
            ->set('active', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Usuário criado com sucesso.');

        $created = User::query()->where('email', 'ana.atendente@clinica.test')->first();

        $this->assertNotNull($created);
        $this->assertSame($clinicA->id, $created->clinic_id);
        $this->assertSame(UserRole::ATTENDANT, $created->role);
        $this->assertTrue($created->active);
        $this->assertTrue(Hash::isHashed($created->getRawOriginal('password')));
        $this->assertTrue(Hash::check('senha-inicial', $created->password));
        $this->assertDatabaseMissing('users', [
            'email' => 'ana.atendente@clinica.test',
            'clinic_id' => $clinicB->id,
        ]);

        $this->actingAs($admin);
        $viaAction = app(CreateUser::class)->handle($admin, [
            'name' => 'Pedro Extra',
            'email' => 'pedro.extra@clinica.test',
            'role' => UserRole::ATTENDANT,
            'password' => 'senha-inicial',
            'active' => true,
            'unit_ids' => [],
            'clinic_id' => $clinicB->id,
        ]);

        $this->assertSame($clinicA->id, $viaAction->clinic_id);
        $this->assertFalse(property_exists(new UsersManager, 'clinic_id'));
    }

    public function test_administrator_creates_supervisor_with_persisted_role(): void
    {
        $admin = $this->administrator();

        Livewire::actingAs($admin)
            ->test(UsersManager::class)
            ->call('startCreate')
            ->set('name', 'Sérgio Supervisor')
            ->set('email', 'sergio.supervisor@clinica.test')
            ->set('role', UserRole::SUPERVISOR->value)
            ->set('password', 'senha-inicial')
            ->set('password_confirmation', 'senha-inicial')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'sergio.supervisor@clinica.test',
            'clinic_id' => $admin->clinic_id,
            'role' => UserRole::SUPERVISOR->value,
        ]);
    }

    public function test_administrator_associates_valid_units_and_rejects_foreign_unit(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $admin = $this->administrator($clinicA);
        $unitA = Unit::factory()->for($clinicA)->create(['name' => 'Recepção A']);
        $unitB = Unit::factory()->for($clinicA)->create(['name' => 'Triagem A']);
        $foreignUnit = Unit::factory()->for($clinicB)->create(['name' => 'Recepção B']);

        Livewire::actingAs($admin)
            ->test(UsersManager::class)
            ->call('startCreate')
            ->set('name', 'Lia Unidades')
            ->set('email', 'lia.unidades@clinica.test')
            ->set('role', UserRole::ATTENDANT->value)
            ->set('password', 'senha-inicial')
            ->set('password_confirmation', 'senha-inicial')
            ->set('unitIds', [$unitA->id])
            ->call('save')
            ->assertHasNoErrors();

        $single = User::query()->where('email', 'lia.unidades@clinica.test')->firstOrFail();
        $this->assertEqualsCanonicalizing([$unitA->id], $single->units()->pluck('units.id')->all());
        $this->assertDatabaseHas('unit_user', [
            'user_id' => $single->id,
            'unit_id' => $unitA->id,
            'clinic_id' => $clinicA->id,
        ]);

        Livewire::actingAs($admin)
            ->test(UsersManager::class)
            ->call('edit', $single->id)
            ->set('unitIds', [$unitA->id, $unitB->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEqualsCanonicalizing(
            [$unitA->id, $unitB->id],
            $single->fresh()->units()->pluck('units.id')->all(),
        );
        $this->assertDatabaseHas('unit_user', [
            'user_id' => $single->id,
            'unit_id' => $unitB->id,
            'clinic_id' => $clinicA->id,
        ]);

        Livewire::actingAs($admin)
            ->test(UsersManager::class)
            ->call('startCreate')
            ->set('name', 'Invasor')
            ->set('email', 'invasor@clinica.test')
            ->set('role', UserRole::ATTENDANT->value)
            ->set('password', 'senha-inicial')
            ->set('password_confirmation', 'senha-inicial')
            ->set('unitIds', [$foreignUnit->id])
            ->call('save')
            ->assertHasErrors(['unitIds.0']);

        $this->assertDatabaseMissing('users', ['email' => 'invasor@clinica.test']);

        $this->actingAs($admin);
        $this->expectException(ValidationException::class);
        app(CreateUser::class)->handle($admin, [
            'name' => 'Invasor Direto',
            'email' => 'invasor.direto@clinica.test',
            'role' => UserRole::ATTENDANT,
            'password' => 'senha-inicial',
            'active' => true,
            'unit_ids' => [$foreignUnit->id],
        ]);
    }

    public function test_administrator_edits_own_clinic_user_and_cannot_edit_foreign_user(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $admin = $this->administrator($clinicA);
        $ownUser = User::factory()->create([
            'clinic_id' => $clinicA->id,
            'name' => 'Nome Antigo',
            'email' => 'antigo@clinica.test',
            'role' => UserRole::ATTENDANT,
        ]);
        $foreignUser = User::factory()->create([
            'clinic_id' => $clinicB->id,
            'name' => 'Usuário B',
            'email' => 'usuario.b@clinica.test',
            'role' => UserRole::ATTENDANT,
        ]);
        $unit = Unit::factory()->for($clinicA)->create();

        Livewire::actingAs($admin)
            ->test(UsersManager::class)
            ->call('edit', $ownUser->id)
            ->set('name', 'Nome Novo')
            ->set('email', 'novo@clinica.test')
            ->set('role', UserRole::SUPERVISOR->value)
            ->set('unitIds', [$unit->id])
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Usuário atualizado com sucesso.');

        $ownUser->refresh();
        $this->assertSame('Nome Novo', $ownUser->name);
        $this->assertSame('novo@clinica.test', $ownUser->email);
        $this->assertSame(UserRole::SUPERVISOR, $ownUser->role);
        $this->assertSame($clinicA->id, $ownUser->clinic_id);
        $this->assertEqualsCanonicalizing([$unit->id], $ownUser->units()->pluck('units.id')->all());

        Livewire::actingAs($admin)
            ->test(UsersManager::class)
            ->call('edit', $foreignUser->id)
            ->assertNotFound();

        Livewire::actingAs($admin)
            ->test(UsersManager::class)
            ->call('confirmDeactivation', $foreignUser->id)
            ->assertNotFound();

        Livewire::actingAs($admin)
            ->test(UsersManager::class)
            ->call('activate', $foreignUser->id)
            ->assertNotFound();

        $this->actingAs($admin);
        $this->expectException(HttpException::class);
        app(UpdateUser::class)->handle($admin, $foreignUser, [
            'name' => 'Hack',
            'email' => $foreignUser->email,
            'role' => UserRole::ADMINISTRATOR,
            'active' => true,
            'unit_ids' => [],
        ]);
    }

    public function test_administrator_deactivates_and_reactivates_user_who_cannot_operate_while_inactive(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = $this->administrator($clinic);
        $attendant = User::factory()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Atendente Operacional',
            'email' => 'atendente.operacional@clinica.test',
            'role' => UserRole::ATTENDANT,
        ]);

        Livewire::actingAs($admin)
            ->test(UsersManager::class)
            ->call('confirmDeactivation', $attendant->id)
            ->call('deactivate')
            ->assertSee('Usuário desativado.');

        $this->assertFalse($attendant->fresh()->active);

        $this->actingAs($attendant->fresh())
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        Livewire::actingAs($admin)
            ->test(UsersManager::class)
            ->call('activate', $attendant->id)
            ->assertSee('Usuário ativado.');

        $this->assertTrue($attendant->fresh()->active);

        $this->actingAs($attendant->fresh())
            ->get(route('dashboard'))
            ->assertRedirect(route('attendant.panel'));
    }

    public function test_last_active_administrator_cannot_be_deactivated_or_demoted(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = $this->administrator($clinic);

        Livewire::actingAs($admin)
            ->test(UsersManager::class)
            ->call('confirmDeactivation', $admin->id)
            ->call('deactivate')
            ->assertHasErrors(['status']);

        $this->assertTrue($admin->fresh()->active);
        $this->assertSame(UserRole::ADMINISTRATOR, $admin->fresh()->role);

        Livewire::actingAs($admin)
            ->test(UsersManager::class)
            ->call('edit', $admin->id)
            ->set('role', UserRole::SUPERVISOR->value)
            ->call('save')
            ->assertHasErrors(['role']);

        $this->assertSame(UserRole::ADMINISTRATOR, $admin->fresh()->role);
        $this->assertTrue($admin->fresh()->active);

        $this->actingAs($admin);
        $this->expectException(LastAdministratorProtectedException::class);
        app(UpdateUser::class)->handle($admin, $admin, [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => UserRole::ATTENDANT,
            'active' => true,
            'unit_ids' => $admin->units()->pluck('units.id')->map(fn ($id): int => (int) $id)->all(),
        ]);
    }

    public function test_last_active_administrator_can_update_own_name_and_email(): void
    {
        $admin = $this->administrator();

        Livewire::actingAs($admin)
            ->test(UsersManager::class)
            ->call('edit', $admin->id)
            ->set('name', 'Administrador Atualizado')
            ->set('email', 'admin.atualizado@clinica.test')
            ->call('save')
            ->assertHasNoErrors();

        $admin->refresh();
        $this->assertSame('Administrador Atualizado', $admin->name);
        $this->assertSame('admin.atualizado@clinica.test', $admin->email);
        $this->assertSame(UserRole::ADMINISTRATOR, $admin->role);
        $this->assertTrue($admin->active);
    }

    public function test_password_is_not_exposed_empty_edit_keeps_current_and_provided_password_is_hashed(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = $this->administrator($clinic);
        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Senha Segura',
            'email' => 'senha.segura@clinica.test',
            'password' => 'senha-atual-123',
            'role' => UserRole::ATTENDANT,
        ]);
        $storedHash = $user->getRawOriginal('password');

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertDontSee($storedHash)
            ->assertDontSee('remember_token');

        Livewire::actingAs($admin)
            ->test(UsersManager::class)
            ->call('edit', $user->id)
            ->assertSet('password', '')
            ->assertSet('password_confirmation', '')
            ->set('name', 'Senha Segura Editada')
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertSame('Senha Segura Editada', $user->name);
        $this->assertTrue(Hash::check('senha-atual-123', $user->password));
        $this->assertSame($storedHash, $user->getRawOriginal('password'));

        Livewire::actingAs($admin)
            ->test(UsersManager::class)
            ->call('edit', $user->id)
            ->set('password', 'nova-senha-456')
            ->set('password_confirmation', 'nova-senha-456')
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertTrue(Hash::isHashed($user->getRawOriginal('password')));
        $this->assertTrue(Hash::check('nova-senha-456', $user->password));
        $this->assertFalse(Hash::check('senha-atual-123', $user->password));
        $this->assertNotSame($storedHash, $user->getRawOriginal('password'));
    }

    public function test_role_and_clinic_are_not_mass_assignable(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $user = User::factory()->create([
            'clinic_id' => $clinicA->id,
            'role' => UserRole::ATTENDANT,
            'active' => true,
        ]);

        $user->fill([
            'name' => 'Preenchido',
            'role' => UserRole::ADMINISTRATOR->value,
            'clinic_id' => $clinicB->id,
            'active' => false,
        ]);

        $this->assertSame('Preenchido', $user->name);
        $this->assertSame(UserRole::ATTENDANT, $user->role);
        $this->assertSame($clinicA->id, $user->clinic_id);
        $this->assertTrue($user->active);
    }

    public function test_search_filters_users_and_shows_empty_state(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = $this->administrator($clinic);
        User::factory()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Marina Busca',
            'email' => 'marina.busca@clinica.test',
            'role' => UserRole::ATTENDANT,
        ]);

        Livewire::actingAs($admin)
            ->test(UsersManager::class)
            ->set('search', 'marina.busca')
            ->assertSee('Marina Busca')
            ->set('search', 'zzzz-inexistente')
            ->assertSee('Nenhum usuário encontrado.');
    }

    /**
     * @return array<string, array{0: UserRole}>
     */
    public static function nonAdministratorRoles(): array
    {
        return [
            'supervisor' => [UserRole::SUPERVISOR],
            'attendant' => [UserRole::ATTENDANT],
        ];
    }

    private function administrator(?Clinic $clinic = null): User
    {
        $clinic ??= Clinic::factory()->create();

        return User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);
    }
}
