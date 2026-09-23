<?php

namespace Tests\Feature;

use App\Livewire\ClinicSettingsManager;
use App\Models\Clinic;
use App\Models\ClinicSetting;
use App\Models\User;
use App\Services\ClinicBranding;
use App\Services\ClinicSettings;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ClinicBrandingLogoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(ClinicSetting::DISK);
    }

    public function test_administrator_can_upload_main_logo(): void
    {
        $admin = $this->administrator();

        Livewire::actingAs($admin)
            ->test(ClinicSettingsManager::class)
            ->set('mainLogoUpload', UploadedFile::fake()->image('logo.png', 200, 80))
            ->call('saveMainLogo')
            ->assertHasNoErrors();

        $path = app(ClinicSettings::class)->get($admin->clinic, 'main_logo_path');
        $this->assertIsString($path);
        $this->assertStringStartsWith('clinic-branding/'.$admin->clinic_id.'/', $path);
        Storage::disk(ClinicSetting::DISK)->assertExists($path);
    }

    public function test_svg_and_invalid_mime_are_rejected(): void
    {
        $admin = $this->administrator();

        Livewire::actingAs($admin)
            ->test(ClinicSettingsManager::class)
            ->set('mainLogoUpload', UploadedFile::fake()->create('logo.svg', 20, 'image/svg+xml'))
            ->call('saveMainLogo')
            ->assertHasErrors(['mainLogoUpload']);

        Livewire::actingAs($admin)
            ->test(ClinicSettingsManager::class)
            ->set('mainLogoUpload', UploadedFile::fake()->create('malware.exe', 20, 'application/x-msdownload'))
            ->call('saveMainLogo')
            ->assertHasErrors(['mainLogoUpload']);
    }

    public function test_oversized_logo_is_rejected(): void
    {
        $admin = $this->administrator();

        Livewire::actingAs($admin)
            ->test(ClinicSettingsManager::class)
            ->set('mainLogoUpload', UploadedFile::fake()->image('big.png')->size(3000))
            ->call('saveMainLogo')
            ->assertHasErrors(['mainLogoUpload']);
    }

    public function test_replace_removes_previous_file_and_remove_clears_path(): void
    {
        $admin = $this->administrator();
        $branding = app(ClinicBranding::class);
        $clinic = $admin->clinic;

        $first = $branding->storeLogo($clinic, 'main_logo_path', UploadedFile::fake()->image('a.png'));
        Storage::disk(ClinicSetting::DISK)->assertExists($first);

        $second = $branding->storeLogo($clinic, 'main_logo_path', UploadedFile::fake()->image('b.png'));
        Storage::disk(ClinicSetting::DISK)->assertExists($second);
        Storage::disk(ClinicSetting::DISK)->assertMissing($first);

        $branding->removeLogo($clinic, 'main_logo_path');
        $this->assertNull(app(ClinicSettings::class)->get($clinic, 'main_logo_path'));
        Storage::disk(ClinicSetting::DISK)->assertMissing($second);
    }

    public function test_tv_and_kiosk_logo_fallback_to_main(): void
    {
        $clinic = Clinic::factory()->create();
        $branding = app(ClinicBranding::class);

        $main = $branding->storeLogo($clinic, 'main_logo_path', UploadedFile::fake()->image('main.png'));
        $resolved = $branding->resolve($clinic);

        $this->assertNotNull($resolved['main_logo_url']);
        $this->assertSame($resolved['main_logo_url'], $resolved['tv_logo_url']);
        $this->assertSame($resolved['main_logo_url'], $resolved['kiosk_logo_url']);

        $tv = $branding->storeLogo($clinic, 'tv_logo_path', UploadedFile::fake()->image('tv.png'));
        $resolved = $branding->resolve($clinic->fresh());

        $this->assertNotSame($resolved['main_logo_url'], $resolved['tv_logo_url']);
        $this->assertSame($resolved['main_logo_url'], $resolved['kiosk_logo_url']);
        $this->assertStringContainsString('clinic-branding/'.$clinic->id, $main);
        $this->assertStringContainsString('clinic-branding/'.$clinic->id, $tv);
    }

    public function test_independent_upload_properties_for_each_logo_slot(): void
    {
        $admin = $this->administrator();

        Livewire::actingAs($admin)
            ->test(ClinicSettingsManager::class)
            ->set('mainLogoUpload', UploadedFile::fake()->image('main.png'))
            ->set('tvLogoUpload', UploadedFile::fake()->image('tv.png'))
            ->set('kioskLogoUpload', UploadedFile::fake()->image('kiosk.png'))
            ->call('saveTvLogo')
            ->assertHasNoErrors()
            ->assertSet('tvLogoUpload', null)
            ->assertNotSet('mainLogoUpload', null);

        $settings = app(ClinicSettings::class);
        $this->assertNotNull($settings->get($admin->clinic, 'tv_logo_path'));
        $this->assertNull($settings->get($admin->clinic, 'main_logo_path'));
        $this->assertNull($settings->get($admin->clinic, 'kiosk_logo_path'));
    }

    public function test_required_without_file_shows_portuguese_message(): void
    {
        $admin = $this->administrator();

        Livewire::actingAs($admin)
            ->test(ClinicSettingsManager::class)
            ->call('setTab', 'identidade')
            ->call('saveMainLogo')
            ->assertHasErrors(['mainLogoUpload'])
            ->assertSee('Selecione uma imagem para a logo principal');
    }

    public function test_cross_tenant_cannot_upload_for_other_clinic_via_livewire(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $adminA = $this->administrator($clinicA);

        Livewire::actingAs($adminA)
            ->test(ClinicSettingsManager::class)
            ->set('mainLogoUpload', UploadedFile::fake()->image('a.png'))
            ->call('saveMainLogo');

        $this->assertNotNull(app(ClinicSettings::class)->get($clinicA, 'main_logo_path'));
        $this->assertNull(app(ClinicSettings::class)->get($clinicB, 'main_logo_path'));
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
