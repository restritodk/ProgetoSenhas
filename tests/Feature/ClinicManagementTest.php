<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_views_own_clinic(): void
    {
        $clinic = Clinic::factory()->create(['name' => 'Clínica Norte', 'slug' => 'clinica-norte']);
        $admin = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);

        $this->actingAs($admin)
            ->get(route('clinic.show'))
            ->assertOk()
            ->assertSee('Clínica Norte')
            ->assertSee('clinica-norte');
    }

    public function test_administrator_does_not_access_another_clinic(): void
    {
        $clinicA = Clinic::factory()->create(['name' => 'Clínica Norte']);
        $clinicB = Clinic::factory()->create(['name' => 'Clínica Sul']);
        $admin = User::factory()->create([
            'clinic_id' => $clinicA->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);

        $this->assertFalse($admin->can('view', $clinicB));
        $this->assertFalse($admin->can('update', $clinicB));

        $this->actingAs($admin)
            ->get(route('clinic.show'))
            ->assertOk()
            ->assertSee('Clínica Norte')
            ->assertDontSee('Clínica Sul');
    }

    public function test_administrator_updates_own_clinic_and_ignores_foreign_clinic_id(): void
    {
        $clinicA = Clinic::factory()->create(['name' => 'Clínica Norte', 'slug' => 'clinica-norte']);
        $clinicB = Clinic::factory()->create(['name' => 'Clínica Sul', 'slug' => 'clinica-sul']);
        $admin = User::factory()->create([
            'clinic_id' => $clinicA->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);

        $this->actingAs($admin)
            ->from(route('clinic.show'))
            ->put(route('clinic.update'), [
                'name' => 'Clínica Norte Atualizada',
                'slug' => 'clinica-norte-atualizada',
                'clinic_id' => $clinicB->id,
                'id' => $clinicB->id,
                'active' => false,
            ])
            ->assertRedirect(route('clinic.show'))
            ->assertSessionHas('status', 'Dados da clínica atualizados com sucesso.');

        $this->assertDatabaseHas('clinics', [
            'id' => $clinicA->id,
            'name' => 'Clínica Norte Atualizada',
            'slug' => 'clinica-norte-atualizada',
            'active' => true,
        ]);
        $this->assertDatabaseHas('clinics', [
            'id' => $clinicB->id,
            'name' => 'Clínica Sul',
            'slug' => 'clinica-sul',
        ]);
    }
}
