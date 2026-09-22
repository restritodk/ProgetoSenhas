<?php

namespace Tests\Feature;

use App\Actions\CreateDesk;
use App\Livewire\DesksManager;
use App\Models\Clinic;
use App\Models\Desk;
use App\Models\Unit;
use App\Models\User;
use App\UserRole;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DeskManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_accesses_desks_index(): void
    {
        $admin = $this->administrator();

        $this->actingAs($admin)
            ->get(route('desks.index'))
            ->assertOk()
            ->assertSee('Mesas / Guichês')
            ->assertSee('Nova mesa');

        $this->assertTrue($admin->can('viewAny', Desk::class));
        $this->assertTrue($admin->can('create', Desk::class));
    }

    #[DataProvider('nonAdministratorRoles')]
    public function test_non_administrator_cannot_manage_desks(UserRole $role): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => $role,
        ]);

        $this->actingAs($user)->get(route('desks.index'))->assertForbidden();

        Livewire::actingAs($user)
            ->test(DesksManager::class)
            ->assertForbidden();

        $this->assertFalse($user->can('viewAny', Desk::class));
        $this->assertFalse($user->can('create', Desk::class));
    }

    public function test_administrator_creates_desk_in_own_unit_and_rejects_foreign_unit(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $admin = $this->administrator($clinicA);
        $unitA = Unit::factory()->for($clinicA)->create(['name' => 'Recepção A']);
        $unitB = Unit::factory()->for($clinicB)->create(['name' => 'Recepção B']);

        Livewire::actingAs($admin)
            ->test(DesksManager::class)
            ->call('startCreate')
            ->set('name', 'Mesa 01')
            ->set('code', 'm01')
            ->set('unitId', $unitA->id)
            ->set('active', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Mesa/guichê criado com sucesso.');

        $this->assertDatabaseHas('desks', [
            'clinic_id' => $clinicA->id,
            'unit_id' => $unitA->id,
            'name' => 'Mesa 01',
            'code' => 'M01',
            'active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(DesksManager::class)
            ->call('startCreate')
            ->set('name', 'Mesa Invasora')
            ->set('code', 'X01')
            ->set('unitId', $unitB->id)
            ->call('save')
            ->assertHasErrors(['unitId']);

        $this->assertDatabaseMissing('desks', [
            'name' => 'Mesa Invasora',
        ]);

        $this->actingAs($admin);
        $this->expectException(ValidationException::class);
        app(CreateDesk::class)->handle($admin, [
            'name' => 'Mesa Direta',
            'code' => 'D01',
            'unit_id' => $unitB->id,
            'active' => true,
        ]);
    }

    public function test_desk_code_is_unique_per_unit_and_reusable_in_another_unit(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = $this->administrator($clinic);
        $unitA = Unit::factory()->for($clinic)->create();
        $unitB = Unit::factory()->for($clinic)->create();

        Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unitA->id,
            'code' => 'M01',
            'name' => 'Mesa Existente',
        ]);

        Livewire::actingAs($admin)
            ->test(DesksManager::class)
            ->call('startCreate')
            ->set('name', 'Outra Mesa')
            ->set('code', 'M01')
            ->set('unitId', $unitA->id)
            ->call('save')
            ->assertHasErrors(['code']);

        Livewire::actingAs($admin)
            ->test(DesksManager::class)
            ->call('startCreate')
            ->set('name', 'Mesa Unidade B')
            ->set('code', 'M01')
            ->set('unitId', $unitB->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, Desk::query()->where('code', 'M01')->count());
        $this->assertDatabaseHas('desks', [
            'unit_id' => $unitB->id,
            'code' => 'M01',
            'clinic_id' => $clinic->id,
        ]);
    }

    public function test_administrator_lists_only_own_clinic_desks_and_cannot_edit_foreign(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $admin = $this->administrator($clinicA);
        $unitA = Unit::factory()->for($clinicA)->create();
        $unitB = Unit::factory()->for($clinicB)->create();
        $ownDesk = Desk::factory()->create([
            'clinic_id' => $clinicA->id,
            'unit_id' => $unitA->id,
            'name' => 'Mesa Própria',
            'code' => 'A01',
        ]);
        $foreignDesk = Desk::factory()->create([
            'clinic_id' => $clinicB->id,
            'unit_id' => $unitB->id,
            'name' => 'Mesa Externa',
            'code' => 'B01',
        ]);

        $this->actingAs($admin)
            ->get(route('desks.index'))
            ->assertOk()
            ->assertSee('Mesa Própria')
            ->assertDontSee('Mesa Externa');

        Livewire::actingAs($admin)
            ->test(DesksManager::class)
            ->call('edit', $foreignDesk->id)
            ->assertNotFound();

        Livewire::actingAs($admin)
            ->test(DesksManager::class)
            ->call('confirmDeactivation', $foreignDesk->id)
            ->assertNotFound();

        Livewire::actingAs($admin)
            ->test(DesksManager::class)
            ->call('activate', $foreignDesk->id)
            ->assertNotFound();

        $this->assertTrue($admin->can('update', $ownDesk));
        $this->assertFalse($admin->can('update', $foreignDesk));
    }

    public function test_administrator_edits_and_toggles_own_desk(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = $this->administrator($clinic);
        $unitA = Unit::factory()->for($clinic)->create(['name' => 'Recepção']);
        $unitB = Unit::factory()->for($clinic)->create(['name' => 'Triagem']);
        $desk = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unitA->id,
            'name' => 'Mesa 01',
            'code' => 'M01',
        ]);

        Livewire::actingAs($admin)
            ->test(DesksManager::class)
            ->call('edit', $desk->id)
            ->set('name', 'Guichê 01')
            ->set('code', 'G01')
            ->set('unitId', $unitB->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Mesa/guichê atualizado com sucesso.');

        $this->assertDatabaseHas('desks', [
            'id' => $desk->id,
            'clinic_id' => $clinic->id,
            'unit_id' => $unitB->id,
            'name' => 'Guichê 01',
            'code' => 'G01',
        ]);

        Livewire::actingAs($admin)
            ->test(DesksManager::class)
            ->call('confirmDeactivation', $desk->id)
            ->call('deactivate')
            ->assertSee('Mesa/guichê desativado.');

        $this->assertFalse($desk->fresh()->active);

        Livewire::actingAs($admin)
            ->test(DesksManager::class)
            ->call('activate', $desk->id)
            ->assertSee('Mesa/guichê ativado.');

        $this->assertTrue($desk->fresh()->active);
    }

    public function test_database_rejects_desk_with_mismatched_clinic_and_unit(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $unitB = Unit::factory()->for($clinicB)->create();

        $this->expectException(QueryException::class);

        $desk = new Desk;
        $desk->forceFill([
            'clinic_id' => $clinicA->id,
            'unit_id' => $unitB->id,
            'name' => 'Inconsistente',
            'code' => 'BAD',
            'active' => true,
        ])->save();
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
