<?php

namespace Tests\Feature;

use App\Livewire\ClinicSettingsManager;
use App\Livewire\PublicKiosk;
use App\Models\Clinic;
use App\Models\ClinicSetting;
use App\Models\DisplayPanel;
use App\Models\Kiosk;
use App\Models\Unit;
use App\Models\User;
use App\Services\ClinicBranding;
use App\Services\ClinicSettings;
use App\Support\LogoSurface;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MainAndKioskLogoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(ClinicSetting::DISK);
    }

    public function test_administrator_can_save_change_and_remove_main_logo(): void
    {
        $admin = $this->administrator();
        $settings = app(ClinicSettings::class);

        Livewire::actingAs($admin)
            ->test(ClinicSettingsManager::class)
            ->set('mainLogoUpload', UploadedFile::fake()->image('main-a.png', 240, 80))
            ->call('saveMainLogo')
            ->assertHasNoErrors()
            ->assertSet('mainLogoUpload', null);

        $first = $settings->get($admin->clinic, 'main_logo_path');
        $this->assertIsString($first);
        Storage::disk(ClinicSetting::DISK)->assertExists($first);

        Livewire::actingAs($admin)
            ->test(ClinicSettingsManager::class)
            ->set('mainLogoUpload', UploadedFile::fake()->image('main-b.png', 240, 80))
            ->call('saveMainLogo')
            ->assertHasNoErrors();

        $second = $settings->get($admin->clinic, 'main_logo_path');
        $this->assertIsString($second);
        $this->assertNotSame($first, $second);
        Storage::disk(ClinicSetting::DISK)->assertExists($second);
        Storage::disk(ClinicSetting::DISK)->assertMissing($first);

        Livewire::actingAs($admin)
            ->test(ClinicSettingsManager::class)
            ->call('removeMainLogo')
            ->assertHasNoErrors();

        $this->assertNull($settings->get($admin->clinic, 'main_logo_path'));
        Storage::disk(ClinicSetting::DISK)->assertMissing($second);
    }

    public function test_main_logo_background_persists_after_reload(): void
    {
        $admin = $this->administrator();
        $settings = app(ClinicSettings::class);

        Livewire::actingAs($admin)
            ->test(ClinicSettingsManager::class)
            ->set('main_logo_background', LogoSurface::CUSTOM)
            ->set('main_logo_background_color', '#112233')
            ->call('saveLogoAppearance', 'main')
            ->assertHasNoErrors()
            ->assertSee('Aparência da logo salva');

        $this->assertSame(LogoSurface::CUSTOM, $settings->get($admin->clinic, 'main_logo_background'));
        $this->assertSame('#112233', $settings->get($admin->clinic, 'main_logo_background_color'));

        Livewire::actingAs($admin)
            ->test(ClinicSettingsManager::class)
            ->assertSet('main_logo_background', LogoSurface::CUSTOM)
            ->assertSet('main_logo_background_color', '#112233');
    }

    public function test_admin_sidebar_shows_main_logo_with_surface_and_fallback(): void
    {
        $admin = $this->administrator();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('hC', false)
            ->assertSee('humanaClinica')
            ->assertSee('Painel administrativo');

        $path = app(ClinicBranding::class)->storeLogo(
            $admin->clinic,
            'main_logo_path',
            UploadedFile::fake()->image('admin-logo.png', 320, 100),
        );
        app(ClinicSettings::class)->putMany($admin->clinic, [
            'main_logo_background' => LogoSurface::LIGHT,
        ]);

        $url = app(ClinicBranding::class)->urlForPath($path);
        $this->assertNotNull($url);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($url, false)
            ->assertSee('rgba(255, 255, 255, 0.94)', false)
            ->assertSee('Painel administrativo')
            ->assertDontSee('>humanaClinica</p>', false)
            ->assertDontSee('>hC</span>', false);
    }

    public function test_administrator_can_save_change_and_remove_kiosk_logo(): void
    {
        $admin = $this->administrator();
        $settings = app(ClinicSettings::class);

        Livewire::actingAs($admin)
            ->test(ClinicSettingsManager::class)
            ->set('kioskLogoUpload', UploadedFile::fake()->image('kiosk-a.png', 240, 80))
            ->call('saveKioskLogo')
            ->assertHasNoErrors()
            ->assertSet('kioskLogoUpload', null);

        $first = $settings->get($admin->clinic, 'kiosk_logo_path');
        $this->assertIsString($first);
        Storage::disk(ClinicSetting::DISK)->assertExists($first);

        Livewire::actingAs($admin)
            ->test(ClinicSettingsManager::class)
            ->set('kioskLogoUpload', UploadedFile::fake()->image('kiosk-b.png', 240, 80))
            ->call('saveKioskLogo')
            ->assertHasNoErrors();

        $second = $settings->get($admin->clinic, 'kiosk_logo_path');
        $this->assertIsString($second);
        $this->assertNotSame($first, $second);
        Storage::disk(ClinicSetting::DISK)->assertExists($second);
        Storage::disk(ClinicSetting::DISK)->assertMissing($first);

        Livewire::actingAs($admin)
            ->test(ClinicSettingsManager::class)
            ->call('removeKioskLogo')
            ->assertHasNoErrors();

        $this->assertNull($settings->get($admin->clinic, 'kiosk_logo_path'));
        Storage::disk(ClinicSetting::DISK)->assertMissing($second);
    }

    public function test_kiosk_logo_background_persists_after_reload(): void
    {
        $admin = $this->administrator();
        $settings = app(ClinicSettings::class);

        Livewire::actingAs($admin)
            ->test(ClinicSettingsManager::class)
            ->set('kiosk_logo_background', LogoSurface::DARK)
            ->call('saveLogoAppearance', 'kiosk')
            ->assertHasNoErrors();

        $this->assertSame(LogoSurface::DARK, $settings->get($admin->clinic, 'kiosk_logo_background'));

        Livewire::actingAs($admin)
            ->test(ClinicSettingsManager::class)
            ->assertSet('kiosk_logo_background', LogoSurface::DARK);
    }

    public function test_totem_shows_own_logo_and_falls_back_to_main(): void
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create();
        $kiosk = Kiosk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'active' => true,
        ]);
        $branding = app(ClinicBranding::class);
        $settings = app(ClinicSettings::class);

        $mainPath = $branding->storeLogo(
            $clinic,
            'main_logo_path',
            UploadedFile::fake()->image('main.png', 300, 90),
        );
        $settings->putMany($clinic, [
            'kiosk_logo_background' => LogoSurface::CUSTOM,
            'kiosk_logo_background_color' => '#ABCDEF',
        ]);

        $mainUrl = $branding->urlForPath($mainPath);
        $this->assertNotNull($mainUrl);

        $this->get(route('kiosk.panel', $kiosk->public_token))
            ->assertOk()
            ->assertSee($mainUrl, false)
            ->assertSee('background: #ABCDEF', false);

        Livewire::test(PublicKiosk::class, ['publicToken' => $kiosk->public_token])
            ->assertSet('presentation.logo_url', $mainUrl)
            ->assertSet('presentation.logo_background', LogoSurface::CUSTOM)
            ->assertSet('presentation.logo_background_color', '#ABCDEF');

        $kioskPath = $branding->storeLogo(
            $clinic,
            'kiosk_logo_path',
            UploadedFile::fake()->image('totem.png', 300, 90),
        );
        $kioskUrl = $branding->urlForPath($kioskPath);
        $this->assertNotNull($kioskUrl);
        $this->assertNotSame($mainUrl, $kioskUrl);

        $this->get(route('kiosk.panel', $kiosk->public_token))
            ->assertOk()
            ->assertSee($kioskUrl, false)
            ->assertDontSee($mainUrl, false);
    }

    public function test_main_and_kiosk_logo_configurations_are_independent(): void
    {
        $admin = $this->administrator();
        $settings = app(ClinicSettings::class);

        Livewire::actingAs($admin)
            ->test(ClinicSettingsManager::class)
            ->set('mainLogoUpload', UploadedFile::fake()->image('main-indep.png'))
            ->call('saveMainLogo')
            ->set('kioskLogoUpload', UploadedFile::fake()->image('kiosk-indep.png'))
            ->call('saveKioskLogo')
            ->set('main_logo_background', LogoSurface::LIGHT)
            ->call('saveLogoAppearance', 'main')
            ->set('kiosk_logo_background', LogoSurface::DARK)
            ->call('saveLogoAppearance', 'kiosk')
            ->assertHasNoErrors();

        $mainPath = $settings->get($admin->clinic, 'main_logo_path');
        $kioskPath = $settings->get($admin->clinic, 'kiosk_logo_path');
        $this->assertIsString($mainPath);
        $this->assertIsString($kioskPath);
        $this->assertNotSame($mainPath, $kioskPath);
        $this->assertSame(LogoSurface::LIGHT, $settings->get($admin->clinic, 'main_logo_background'));
        $this->assertSame(LogoSurface::DARK, $settings->get($admin->clinic, 'kiosk_logo_background'));

        Livewire::actingAs($admin)
            ->test(ClinicSettingsManager::class)
            ->set('mainLogoUpload', UploadedFile::fake()->image('main-changed.png'))
            ->call('saveMainLogo')
            ->set('main_logo_background', LogoSurface::CUSTOM)
            ->set('main_logo_background_color', '#010101')
            ->call('saveLogoAppearance', 'main')
            ->assertHasNoErrors();

        $this->assertNotSame($mainPath, $settings->get($admin->clinic, 'main_logo_path'));
        $this->assertSame($kioskPath, $settings->get($admin->clinic, 'kiosk_logo_path'));
        $this->assertSame(LogoSurface::DARK, $settings->get($admin->clinic, 'kiosk_logo_background'));
        $this->assertSame(LogoSurface::CUSTOM, $settings->get($admin->clinic, 'main_logo_background'));
        $this->assertSame('#010101', $settings->get($admin->clinic, 'main_logo_background_color'));

        Livewire::actingAs($admin)
            ->test(ClinicSettingsManager::class)
            ->set('kioskLogoUpload', UploadedFile::fake()->image('kiosk-changed.png'))
            ->call('saveKioskLogo')
            ->set('kiosk_logo_background', LogoSurface::NONE)
            ->call('saveLogoAppearance', 'kiosk')
            ->assertHasNoErrors();

        $this->assertNotSame($kioskPath, $settings->get($admin->clinic, 'kiosk_logo_path'));
        $this->assertSame(LogoSurface::CUSTOM, $settings->get($admin->clinic, 'main_logo_background'));
        $this->assertSame('#010101', $settings->get($admin->clinic, 'main_logo_background_color'));
        $this->assertNotNull($settings->get($admin->clinic, 'main_logo_path'));
    }

    public function test_tv_logo_configuration_is_untouched_by_main_and_kiosk_changes(): void
    {
        $admin = $this->administrator();
        $settings = app(ClinicSettings::class);
        $branding = app(ClinicBranding::class);

        $tvPath = $branding->storeLogo(
            $admin->clinic,
            'tv_logo_path',
            UploadedFile::fake()->image('tv-safe.png', 280, 90),
        );
        $settings->putMany($admin->clinic, [
            'tv_logo_background' => LogoSurface::CUSTOM,
            'tv_logo_background_color' => '#F5F7FA',
        ]);

        Livewire::actingAs($admin)
            ->test(ClinicSettingsManager::class)
            ->set('mainLogoUpload', UploadedFile::fake()->image('main-safe.png'))
            ->call('saveMainLogo')
            ->set('kioskLogoUpload', UploadedFile::fake()->image('kiosk-safe.png'))
            ->call('saveKioskLogo')
            ->set('main_logo_background', LogoSurface::DARK)
            ->call('saveLogoAppearance', 'main')
            ->set('kiosk_logo_background', LogoSurface::LIGHT)
            ->call('saveLogoAppearance', 'kiosk')
            ->assertHasNoErrors();

        $this->assertSame($tvPath, $settings->get($admin->clinic, 'tv_logo_path'));
        $this->assertSame(LogoSurface::CUSTOM, $settings->get($admin->clinic, 'tv_logo_background'));
        $this->assertSame('#F5F7FA', $settings->get($admin->clinic, 'tv_logo_background_color'));
        Storage::disk(ClinicSetting::DISK)->assertExists($tvPath);

        $unit = Unit::factory()->for($admin->clinic)->create();
        $panel = DisplayPanel::factory()->create([
            'clinic_id' => $admin->clinic->id,
            'unit_id' => $unit->id,
            'active' => true,
        ]);

        $tvUrl = $branding->urlForPath($tvPath);
        $this->assertNotNull($tvUrl);

        $this->get(route('tv.panel', $panel->public_token))
            ->assertOk()
            ->assertSee($tvUrl, false)
            ->assertSee('class="tv-brand-logo-wrap tv-brand-logo-wrap--custom"', false)
            ->assertSee('background: #F5F7FA', false);
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
