<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_accesses_administrative_dashboard(): void
    {
        $clinic = Clinic::factory()->create(['name' => 'Clínica Norte']);
        $admin = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('humanaClinica')
            ->assertSee('Clínica Norte')
            ->assertSee('Dashboard')
            ->assertDontSee('247 senhas')
            ->assertDontSee('PROGETOSENHAS')
            ->assertDontSee('progetoSenhas');
    }

    #[DataProvider('nonAdministratorRoles')]
    public function test_non_administrator_does_not_manage_clinic(UserRole $role): void
    {
        $clinic = Clinic::factory()->create(['name' => 'Clínica Norte']);
        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => $role,
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->actingAs($user)->get(route('clinic.show'))->assertForbidden();
        $this->assertFalse($user->can('manage', $clinic));
        $this->assertFalse($user->can('update', $clinic));
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
}
