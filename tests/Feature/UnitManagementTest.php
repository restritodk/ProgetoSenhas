<?php

namespace Tests\Feature;

use App\Livewire\UnitsManager;
use App\Models\Clinic;
use App\Models\Unit;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UnitManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_cannot_open_units_manager(): void
    {
        $clinic = Clinic::factory()->create();
        $supervisor = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::SUPERVISOR,
        ]);

        $this->actingAs($supervisor)->get(route('clinic.show'))->assertForbidden();

        Livewire::actingAs($supervisor)
            ->test(UnitsManager::class)
            ->assertForbidden();
    }

    public function test_administrator_lists_own_units_only(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $ownUnit = Unit::factory()->for($clinicA)->create(['name' => 'Unidade Centro A']);
        $foreignUnit = Unit::factory()->for($clinicB)->create(['name' => 'Unidade Centro B']);
        $admin = User::factory()->create([
            'clinic_id' => $clinicA->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);

        $this->actingAs($admin)
            ->get(route('clinic.show'))
            ->assertOk()
            ->assertSee('Unidade Centro A')
            ->assertDontSee('Unidade Centro B');

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->assertSee('Unidade Centro A')
            ->assertDontSee('Unidade Centro B');

        $this->assertTrue($admin->can('view', $ownUnit));
        $this->assertFalse($admin->can('view', $foreignUnit));
    }

    public function test_administrator_creates_unit_in_own_clinic_and_ignores_foreign_clinic_id(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $admin = User::factory()->create([
            'clinic_id' => $clinicA->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->set('name', 'Recepção Norte')
            ->set('slug', 'recepcao-norte')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Unidade criada com sucesso.');

        $this->assertDatabaseHas('units', [
            'clinic_id' => $clinicA->id,
            'name' => 'Recepção Norte',
            'slug' => 'recepcao-norte',
            'active' => true,
        ]);
        $this->assertDatabaseMissing('units', [
            'clinic_id' => $clinicB->id,
            'slug' => 'recepcao-norte',
        ]);
    }

    public function test_administrator_cannot_edit_or_deactivate_foreign_unit(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $foreignUnit = Unit::factory()->for($clinicB)->create(['name' => 'Unidade Centro B', 'slug' => 'centro-b']);
        $admin = User::factory()->create([
            'clinic_id' => $clinicA->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->call('edit', $foreignUnit->id)
            ->assertNotFound();

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->call('confirmDeactivation', $foreignUnit->id)
            ->assertNotFound();

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->call('activate', $foreignUnit->id)
            ->assertNotFound();

        $this->assertDatabaseHas('units', [
            'id' => $foreignUnit->id,
            'name' => 'Unidade Centro B',
            'active' => true,
        ]);
    }

    public function test_administrator_edits_and_toggles_own_unit(): void
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create(['name' => 'Recepção', 'slug' => 'recepcao']);
        $admin = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->call('edit', $unit->id)
            ->set('name', 'Recepção Central')
            ->set('slug', 'recepcao-central')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Unidade atualizada com sucesso.');

        $this->assertDatabaseHas('units', [
            'id' => $unit->id,
            'clinic_id' => $clinic->id,
            'name' => 'Recepção Central',
            'slug' => 'recepcao-central',
        ]);

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->call('confirmDeactivation', $unit->id)
            ->call('deactivate')
            ->assertSee('Unidade desativada.');

        $this->assertDatabaseHas('units', [
            'id' => $unit->id,
            'active' => false,
        ]);

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->call('activate', $unit->id)
            ->assertSee('Unidade ativada.');

        $this->assertDatabaseHas('units', [
            'id' => $unit->id,
            'active' => true,
        ]);
    }

    public function test_unit_slug_is_unique_inside_clinic_and_reusable_in_another_clinic(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        Unit::factory()->for($clinicA)->create(['slug' => 'recepcao']);
        Unit::factory()->for($clinicB)->create(['slug' => 'recepcao']);
        $admin = User::factory()->create([
            'clinic_id' => $clinicA->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->set('name', 'Outra Recepção')
            ->set('slug', 'recepcao')
            ->call('save')
            ->assertHasErrors(['slug']);

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->set('name', 'Triagem')
            ->set('slug', 'triagem')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('units', [
            'clinic_id' => $clinicA->id,
            'slug' => 'triagem',
        ]);
        $this->assertSame(1, Unit::query()->where('clinic_id', $clinicB->id)->where('slug', 'recepcao')->count());
    }
}
