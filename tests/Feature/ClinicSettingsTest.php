<?php

namespace Tests\Feature;

use App\Livewire\ClinicSettingsManager;
use App\Models\Clinic;
use App\Models\ClinicSetting;
use App\Models\User;
use App\Services\ClinicSettings;
use App\Support\ClinicSettingCatalog;
use App\Support\LogoSurface;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClinicSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_open_settings_page(): void
    {
        $admin = $this->administrator();

        $this->actingAs($admin)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Central de configurações');
    }

    #[DataProvider('nonAdministratorRoles')]
    public function test_non_administrators_cannot_open_settings(UserRole $role): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => $role,
        ]);

        $response = $this->actingAs($user)->get(route('settings.index'));

        if ($role === UserRole::ATTENDANT) {
            $response->assertRedirect(route('attendant.panel'));
        } else {
            $response->assertForbidden();
        }
    }

    public function test_defaults_work_without_persisted_rows(): void
    {
        $clinic = Clinic::factory()->create();
        $bag = app(ClinicSettings::class)->all($clinic);

        $this->assertSame(5, $bag['tv_recent_calls_count']);
        $this->assertSame(3, $bag['kiosk_auto_return_seconds']);
        $this->assertTrue($bag['tv_chime_enabled']);
        $this->assertSame(ClinicSettingCatalog::DEFAULT_PRIMARY_COLOR, strtolower((string) $bag['primary_color']));
        $this->assertSame(LogoSurface::NONE, $bag['tv_logo_background']);
        $this->assertSame(0, ClinicSetting::query()->count());
    }

    public function test_administrator_can_save_general_and_tv_settings(): void
    {
        $admin = $this->administrator();
        $clinic = $admin->clinic;

        Livewire::actingAs($admin)
            ->test(ClinicSettingsManager::class)
            ->set('display_name', 'Clínica Vista Mar')
            ->set('slogan', 'Cuidando de você')
            ->call('saveGeneral')
            ->assertSee('Configurações gerais salvas')
            ->set('tv_recent_calls_count', 7)
            ->set('tv_footer_1_title', 'Atenção à senha')
            ->set('tv_footer_enabled', false)
            ->call('saveTv')
            ->assertSee('Configurações do Painel da TV salvas');

        $bag = app(ClinicSettings::class)->all($clinic);
        $this->assertSame('Clínica Vista Mar', $bag['display_name']);
        $this->assertSame('Cuidando de você', $bag['slogan']);
        $this->assertSame(7, $bag['tv_recent_calls_count']);
        $this->assertFalse($bag['tv_footer_enabled']);
        $this->assertSame('Atenção à senha', $bag['tv_footer_1_title']);
    }

    public function test_invalid_values_are_rejected(): void
    {
        $admin = $this->administrator();

        Livewire::actingAs($admin)
            ->test(ClinicSettingsManager::class)
            ->set('tv_recent_calls_count', 100)
            ->call('saveTv')
            ->assertHasErrors();

        Livewire::actingAs($admin)
            ->test(ClinicSettingsManager::class)
            ->set('kiosk_auto_return_seconds', 2)
            ->call('saveKiosk')
            ->assertHasErrors();

        Livewire::actingAs($admin)
            ->test(ClinicSettingsManager::class)
            ->set('primary_color', 'blue')
            ->call('saveColors')
            ->assertHasErrors();
    }

    public function test_cross_tenant_cannot_mutate_other_clinic_settings(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $adminA = $this->administrator($clinicA);

        app(ClinicSettings::class)->putMany($clinicB, [
            'display_name' => 'Clínica B Original',
        ]);

        Livewire::actingAs($adminA)
            ->test(ClinicSettingsManager::class)
            ->set('display_name', 'Tentativa A')
            ->call('saveGeneral');

        $this->assertSame('Tentativa A', app(ClinicSettings::class)->get($clinicA, 'display_name'));
        $this->assertSame('Clínica B Original', app(ClinicSettings::class)->get($clinicB, 'display_name'));
    }

    public function test_cache_is_invalidated_after_save(): void
    {
        $clinic = Clinic::factory()->create();
        $settings = app(ClinicSettings::class);
        $settings->all($clinic);

        $this->assertTrue(Cache::has($settings->cacheKey($clinic->id)));

        $settings->putMany($clinic, ['display_name' => 'Novo Nome']);

        $this->assertSame('Novo Nome', $settings->get($clinic, 'display_name'));
    }

    public function test_html_in_text_is_stripped(): void
    {
        $clinic = Clinic::factory()->create();
        $bag = app(ClinicSettings::class)->putMany($clinic, [
            'slogan' => '<script>alert(1)</script>Cuidado',
        ]);

        $this->assertSame('alert(1)Cuidado', $bag['slogan']);
        $this->assertStringNotContainsString('<script>', $bag['slogan']);
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
