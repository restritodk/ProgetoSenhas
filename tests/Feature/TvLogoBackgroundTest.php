<?php

namespace Tests\Feature;

use App\Livewire\ClinicSettingsManager;
use App\Models\Clinic;
use App\Models\ClinicSetting;
use App\Models\DisplayPanel;
use App\Models\Kiosk;
use App\Models\Unit;
use App\Models\User;
use App\Services\ClinicBranding;
use App\Services\ClinicSettings;
use App\Services\DisplayPanelFeed;
use App\Support\LogoSurface;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class TvLogoBackgroundTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_logo_surfaces_are_without_background(): void
    {
        $clinic = Clinic::factory()->create();
        $bag = app(ClinicSettings::class)->all($clinic);

        $this->assertSame(LogoSurface::NONE, $bag['tv_logo_background']);
        $this->assertSame(LogoSurface::NONE, $bag['kiosk_logo_background']);
        $this->assertSame(LogoSurface::NONE, $bag['main_logo_background']);
        $this->assertSame(LogoSurface::DEFAULT_CUSTOM_COLOR, $bag['tv_logo_background_color']);
        $this->assertSame(0, ClinicSetting::query()->where('key', 'like', '%logo_background%')->count());
    }

    public function test_administrator_can_save_all_logo_surface_modes(): void
    {
        $admin = $this->administrator();
        $settings = app(ClinicSettings::class);

        Livewire::actingAs($admin)
            ->test(ClinicSettingsManager::class)
            ->set('tv_logo_background', LogoSurface::NONE)
            ->call('saveLogoAppearance', 'tv')
            ->assertHasNoErrors();
        $this->assertSame(LogoSurface::NONE, $settings->get($admin->clinic, 'tv_logo_background'));

        Livewire::actingAs($admin)
            ->test(ClinicSettingsManager::class)
            ->set('tv_logo_background', LogoSurface::LIGHT)
            ->call('saveLogoAppearance', 'tv')
            ->assertHasNoErrors();
        $this->assertSame(LogoSurface::LIGHT, $settings->get($admin->clinic, 'tv_logo_background'));

        Livewire::actingAs($admin)
            ->test(ClinicSettingsManager::class)
            ->set('tv_logo_background', LogoSurface::DARK)
            ->call('saveLogoAppearance', 'tv')
            ->assertHasNoErrors();
        $this->assertSame(LogoSurface::DARK, $settings->get($admin->clinic, 'tv_logo_background'));

        Livewire::actingAs($admin)
            ->test(ClinicSettingsManager::class)
            ->set('tv_logo_background', LogoSurface::CUSTOM)
            ->set('tv_logo_background_color', '#F5F7FA')
            ->call('saveLogoAppearance', 'tv')
            ->assertHasNoErrors()
            ->assertSee('Aparência da logo salva');

        $this->assertSame(LogoSurface::CUSTOM, $settings->get($admin->clinic, 'tv_logo_background'));
        $this->assertSame('#F5F7FA', $settings->get($admin->clinic, 'tv_logo_background_color'));
    }

    public function test_invalid_mode_and_hex_are_rejected(): void
    {
        $admin = $this->administrator();

        Livewire::actingAs($admin)
            ->test(ClinicSettingsManager::class)
            ->set('tv_logo_background', 'neon-pink')
            ->call('saveLogoAppearance', 'tv')
            ->assertHasErrors(['tv_logo_background']);

        Livewire::actingAs($admin)
            ->test(ClinicSettingsManager::class)
            ->set('tv_logo_background', LogoSurface::CUSTOM)
            ->set('tv_logo_background_color', 'not-a-color')
            ->call('saveLogoAppearance', 'tv')
            ->assertHasErrors(['tv_logo_background_color']);

        $this->expectException(ValidationException::class);
        app(ClinicSettings::class)->putMany($admin->clinic, [
            'tv_logo_background' => 'filter:invert(1)',
        ]);
    }

    public function test_contexts_are_independent_and_cross_tenant_safe(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $adminA = $this->administrator($clinicA);
        $settings = app(ClinicSettings::class);

        $settings->putMany($clinicB, [
            'tv_logo_background' => LogoSurface::DARK,
            'kiosk_logo_background' => LogoSurface::LIGHT,
        ]);

        Livewire::actingAs($adminA)
            ->test(ClinicSettingsManager::class)
            ->set('tv_logo_background', LogoSurface::LIGHT)
            ->set('kiosk_logo_background', LogoSurface::NONE)
            ->set('main_logo_background', LogoSurface::CUSTOM)
            ->set('main_logo_background_color', '#0F172A')
            ->call('saveBranding')
            ->assertHasNoErrors();

        $this->assertSame(LogoSurface::LIGHT, $settings->get($clinicA, 'tv_logo_background'));
        $this->assertSame(LogoSurface::NONE, $settings->get($clinicA, 'kiosk_logo_background'));
        $this->assertSame(LogoSurface::CUSTOM, $settings->get($clinicA, 'main_logo_background'));
        $this->assertSame('#0F172A', $settings->get($clinicA, 'main_logo_background_color'));
        $this->assertSame(LogoSurface::DARK, $settings->get($clinicB, 'tv_logo_background'));
        $this->assertSame(LogoSurface::LIGHT, $settings->get($clinicB, 'kiosk_logo_background'));
    }

    public function test_tv_applies_custom_surface_without_breaking_logo_fallback(): void
    {
        Storage::fake(ClinicSetting::DISK);

        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create();
        $panel = DisplayPanel::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'active' => true,
        ]);

        app(ClinicBranding::class)->storeLogo(
            $clinic,
            'main_logo_path',
            UploadedFile::fake()->image('main.png', 400, 120),
        );
        app(ClinicSettings::class)->putMany($clinic, [
            'tv_logo_background' => LogoSurface::CUSTOM,
            'tv_logo_background_color' => '#FFFFFF',
        ]);

        $feed = app(DisplayPanelFeed::class)->build($panel->fresh(['clinic', 'unit']));
        $this->assertSame(LogoSurface::CUSTOM, $feed['presentation']['logo_background']);
        $this->assertSame('#FFFFFF', $feed['presentation']['logo_background_color']);
        $this->assertNotNull($feed['presentation']['logo_url']);

        $this->get(route('tv.panel', $panel->public_token))
            ->assertOk()
            ->assertSee('class="tv-brand-logo-wrap tv-brand-logo-wrap--custom"', false)
            ->assertSee('background: #FFFFFF', false)
            ->assertSee($feed['presentation']['logo_url'], false)
            ->assertDontSee('filter:', false)
            ->assertDontSee('mix-blend-mode', false);
    }

    public function test_kiosk_receives_independent_logo_surface(): void
    {
        Storage::fake(ClinicSetting::DISK);

        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create();
        $kiosk = Kiosk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'active' => true,
        ]);

        app(ClinicBranding::class)->storeLogo(
            $clinic,
            'kiosk_logo_path',
            UploadedFile::fake()->image('kiosk.png', 320, 100),
        );
        app(ClinicSettings::class)->putMany($clinic, [
            'tv_logo_background' => LogoSurface::LIGHT,
            'kiosk_logo_background' => LogoSurface::DARK,
        ]);

        $this->get(route('kiosk.panel', $kiosk->public_token))
            ->assertOk()
            ->assertSee('rgba(8, 18, 32, 0.72)', false)
            ->assertDontSee('rgba(255, 255, 255, 0.94)', false);
    }

    public function test_cancel_discards_temporary_upload_without_persisting(): void
    {
        Storage::fake(ClinicSetting::DISK);
        $admin = $this->administrator();

        $component = Livewire::actingAs($admin)
            ->test(ClinicSettingsManager::class)
            ->set('mainLogoUpload', UploadedFile::fake()->image('temp.png', 200, 80));

        $this->assertNotNull($component->get('mainLogoUpload'));

        $component
            ->call('cancelLogoUpload', 'mainLogoUpload')
            ->assertSet('mainLogoUpload', null);

        $this->assertNull(app(ClinicSettings::class)->get($admin->clinic, 'main_logo_path'));
    }

    public function test_tv_card_shows_inherited_main_logo_label(): void
    {
        Storage::fake(ClinicSetting::DISK);
        $admin = $this->administrator();

        app(ClinicBranding::class)->storeLogo(
            $admin->clinic,
            'main_logo_path',
            UploadedFile::fake()->image('main.png', 200, 80),
        );

        Livewire::actingAs($admin)
            ->test(ClinicSettingsManager::class)
            ->call('setTab', 'identidade')
            ->assertSee('Logo do Painel da TV')
            ->assertSee('Usando a logo principal')
            ->assertSee('Usar imagem específica')
            ->assertDontSee('+ Adicionar imagem');
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
