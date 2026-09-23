<?php

namespace Tests\Feature;

use App\Actions\CreateDisplayPanel;
use App\Actions\RegenerateDisplayPanelToken;
use App\Livewire\DisplayPanelsManager;
use App\Models\Clinic;
use App\Models\DisplayPanel;
use App\Models\Unit;
use App\Models\User;
use App\UserRole;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DisplayPanelManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_creates_panel_for_own_unit_and_rejects_foreign_unit(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $admin = $this->administrator($clinicA);
        $unitA = Unit::factory()->for($clinicA)->create(['name' => 'Recepção']);
        $unitB = Unit::factory()->for($clinicB)->create(['name' => 'Externa']);

        $this->actingAs($admin)
            ->get(route('display-panels.index'))
            ->assertOk()
            ->assertSee('Painéis / TVs');

        Livewire::actingAs($admin)
            ->test(DisplayPanelsManager::class)
            ->call('startCreate')
            ->set('name', 'TV Recepção')
            ->set('code', 'tv-rec')
            ->set('unitId', $unitA->id)
            ->set('active', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Painel/TV criado com sucesso.');

        $panel = DisplayPanel::query()->first();
        $this->assertNotNull($panel);
        $this->assertSame($clinicA->id, $panel->clinic_id);
        $this->assertSame($unitA->id, $panel->unit_id);
        $this->assertSame('TV-REC', $panel->code);
        $this->assertCount(1, $panel->sectors);
        $this->assertSame(64, strlen($panel->public_token));
        $this->assertDoesNotMatchRegularExpression('/^\d+$/', $panel->public_token);
        $this->assertNotSame((string) $clinicA->id, $panel->public_token);
        $this->assertNotSame(base64_encode((string) $clinicA->id), $panel->public_token);

        Livewire::actingAs($admin)
            ->test(DisplayPanelsManager::class)
            ->call('startCreate')
            ->set('name', 'TV Invasora')
            ->set('code', 'TV-X')
            ->set('unitId', $unitB->id)
            ->call('save')
            ->assertHasErrors(['unitId']);

        $this->actingAs($admin);
        $this->expectException(ValidationException::class);
        app(CreateDisplayPanel::class)->handle($admin, [
            'name' => 'Direto',
            'code' => 'DIR',
            'unit_id' => $unitB->id,
            'active' => true,
        ]);
    }

    #[DataProvider('nonAdministratorRoles')]
    public function test_non_administrators_cannot_manage_panels(UserRole $role): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => $role,
        ]);

        $response = $this->actingAs($user)->get(route('display-panels.index'));
        if ($role === UserRole::ATTENDANT) {
            $response->assertRedirect(route('attendant.panel'));
        } else {
            $response->assertForbidden();
        }

        Livewire::actingAs($user)
            ->test(DisplayPanelsManager::class)
            ->assertForbidden();

        $this->assertFalse($user->can('viewAny', DisplayPanel::class));
        $this->assertFalse($user->can('create', DisplayPanel::class));
    }

    public function test_cross_tenant_panel_management_is_blocked(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $admin = $this->administrator($clinicA);
        $unitA = Unit::factory()->for($clinicA)->create();
        $unitB = Unit::factory()->for($clinicB)->create();
        $own = DisplayPanel::factory()->create([
            'clinic_id' => $clinicA->id,
            'unit_id' => $unitA->id,
            'name' => 'TV Própria',
            'code' => 'OWN',
        ]);
        $foreign = DisplayPanel::factory()->create([
            'clinic_id' => $clinicB->id,
            'unit_id' => $unitB->id,
            'name' => 'TV Externa',
            'code' => 'EXT',
        ]);

        $this->actingAs($admin)
            ->get(route('display-panels.index'))
            ->assertSee('TV Própria')
            ->assertDontSee('TV Externa');

        Livewire::actingAs($admin)
            ->test(DisplayPanelsManager::class)
            ->call('edit', $foreign->id)
            ->assertNotFound();

        $this->assertTrue($admin->can('update', $own));
        $this->assertFalse($admin->can('update', $foreign));
    }

    public function test_panel_can_be_deactivated_and_token_regeneration_invalidates_previous_url(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = $this->administrator($clinic);
        $unit = Unit::factory()->for($clinic)->create();
        $panel = DisplayPanel::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'name' => 'TV Principal',
            'code' => 'MAIN',
        ]);
        $oldToken = $panel->public_token;

        Livewire::actingAs($admin)
            ->test(DisplayPanelsManager::class)
            ->call('confirmDeactivation', $panel->id)
            ->call('deactivate')
            ->assertSee('Painel/TV desativado.');

        $this->assertFalse($panel->fresh()->active);

        Livewire::actingAs($admin)
            ->test(DisplayPanelsManager::class)
            ->call('activate', $panel->id)
            ->assertSee('Painel/TV ativado.');

        $this->assertTrue($panel->fresh()->active);

        Livewire::actingAs($admin)
            ->test(DisplayPanelsManager::class)
            ->call('confirmTokenRegen', $panel->id)
            ->call('regenerateToken')
            ->assertSee('Token do painel regenerado');

        $panel->refresh();
        $this->assertNotSame($oldToken, $panel->public_token);
        $this->assertSame(64, strlen($panel->public_token));

        $this->get(route('tv.panel', ['publicToken' => $oldToken]))->assertNotFound();
        $this->get(route('tv.panel', ['publicToken' => $panel->public_token]))->assertOk();
    }

    public function test_database_rejects_panel_with_mismatched_clinic_and_unit(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $unitB = Unit::factory()->for($clinicB)->create();

        $this->expectException(QueryException::class);

        $panel = new DisplayPanel;
        $panel->forceFill([
            'clinic_id' => $clinicA->id,
            'unit_id' => $unitB->id,
            'name' => 'Inconsistente',
            'code' => 'BAD',
            'public_token' => DisplayPanel::generatePublicToken(),
            'active' => true,
        ])->save();
    }

    public function test_regenerate_token_action_requires_authorization(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = $this->administrator($clinic);
        $unit = Unit::factory()->for($clinic)->create();
        $panel = DisplayPanel::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
        ]);
        $old = $panel->public_token;

        $this->actingAs($admin);
        $updated = app(RegenerateDisplayPanelToken::class)->handle($admin, $panel);

        $this->assertNotSame($old, $updated->public_token);
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
