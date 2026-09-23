<?php

namespace Tests\Feature;

use App\Actions\CreateKiosk;
use App\Actions\RegenerateKioskToken;
use App\Livewire\KiosksManager;
use App\Models\Clinic;
use App\Models\Kiosk;
use App\Models\Unit;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class KioskManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_creates_kiosk_for_own_unit_and_rejects_foreign_unit(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $admin = $this->administrator($clinicA);
        $unitA = Unit::factory()->for($clinicA)->create(['name' => 'Recepção']);
        $unitB = Unit::factory()->for($clinicB)->create(['name' => 'Externa']);

        $this->actingAs($admin)
            ->get(route('kiosks.index'))
            ->assertOk()
            ->assertSee('Totens');

        Livewire::actingAs($admin)
            ->test(KiosksManager::class)
            ->call('startCreate')
            ->set('name', 'Totem Recepção')
            ->set('code', 'totem-rec')
            ->set('unitId', $unitA->id)
            ->set('active', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Totem criado com sucesso.');

        $kiosk = Kiosk::query()->first();
        $this->assertNotNull($kiosk);
        $this->assertSame($clinicA->id, $kiosk->clinic_id);
        $this->assertSame($unitA->id, $kiosk->unit_id);
        $this->assertSame('TOTEM-REC', $kiosk->code);
        $this->assertSame(64, strlen($kiosk->public_token));
        $this->assertDoesNotMatchRegularExpression('/^\d+$/', $kiosk->public_token);
        $this->assertNotSame((string) $clinicA->id, $kiosk->public_token);

        Livewire::actingAs($admin)
            ->test(KiosksManager::class)
            ->call('startCreate')
            ->set('name', 'Totem Invasor')
            ->set('code', 'TOTEM-X')
            ->set('unitId', $unitB->id)
            ->call('save')
            ->assertHasErrors(['unitId']);

        $this->actingAs($admin);
        $this->expectException(ValidationException::class);
        app(CreateKiosk::class)->handle($admin, [
            'name' => 'Direto',
            'code' => 'DIR',
            'unit_id' => $unitB->id,
            'active' => true,
        ]);
    }

    #[DataProvider('nonAdministratorRoles')]
    public function test_non_administrators_cannot_manage_kiosks(UserRole $role): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => $role,
        ]);

        $response = $this->actingAs($user)->get(route('kiosks.index'));
        if ($role === UserRole::ATTENDANT) {
            $response->assertRedirect(route('attendant.panel'));
        } else {
            $response->assertForbidden();
        }

        Livewire::actingAs($user)
            ->test(KiosksManager::class)
            ->assertForbidden();

        $this->assertFalse($user->can('viewAny', Kiosk::class));
        $this->assertFalse($user->can('create', Kiosk::class));
    }

    public function test_token_regeneration_invalidates_previous_url_and_cross_tenant_is_blocked(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $adminA = $this->administrator($clinicA);
        $adminB = $this->administrator($clinicB);
        $unitA = Unit::factory()->for($clinicA)->create();
        $unitB = Unit::factory()->for($clinicB)->create();

        $kiosk = app(CreateKiosk::class)->handle($adminA, [
            'name' => 'Totem A',
            'code' => 'KA',
            'unit_id' => $unitA->id,
            'active' => true,
        ]);
        $oldToken = $kiosk->public_token;

        $this->get(route('kiosk.panel', $oldToken))->assertOk();

        $regenerated = app(RegenerateKioskToken::class)->handle($adminA, $kiosk);
        $this->assertNotSame($oldToken, $regenerated->public_token);
        $this->get(route('kiosk.panel', $oldToken))->assertNotFound();
        $this->get(route('kiosk.panel', $regenerated->public_token))->assertOk();

        $foreign = Kiosk::factory()->create([
            'clinic_id' => $clinicB->id,
            'unit_id' => $unitB->id,
        ]);

        $this->actingAs($adminA);
        $this->assertFalse($adminA->can('update', $foreign));
        $this->assertFalse($adminB->can('update', $kiosk));
    }

    public function test_kiosk_can_be_deactivated(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = $this->administrator($clinic);
        $unit = Unit::factory()->for($clinic)->create();
        $kiosk = app(CreateKiosk::class)->handle($admin, [
            'name' => 'Totem',
            'code' => 'T1',
            'unit_id' => $unit->id,
            'active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(KiosksManager::class)
            ->call('confirmDeactivation', $kiosk->id)
            ->call('deactivate')
            ->assertSee('Totem desativado.');

        $this->assertFalse($kiosk->fresh()->active);
    }

    public function test_public_tokens_are_unique(): void
    {
        $tokens = collect(range(1, 20))->map(fn (): string => Kiosk::generatePublicToken());
        $this->assertSame($tokens->count(), $tokens->unique()->count());
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

    private function administrator(Clinic $clinic): User
    {
        return User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);
    }
}
