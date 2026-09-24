<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\ClinicSetting;
use App\Services\ClinicBranding;
use App\Services\ClinicSettings;
use App\Support\LoginPresentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LoginPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_uses_premium_copy_and_global_fallback_without_clinic_logo(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Acesse sua conta')
            ->assertSee('Entre com suas credenciais para acessar o sistema.')
            ->assertSee('Atendimento organizado.')
            ->assertSee('humanaClinica')
            ->assertSee('Micro Hard Center')
            ->assertSee('Tecnologia & Desenvolvimento')
            ->assertSee('Mostrar senha')
            ->assertSee('images/telaLogin.png', false)
            ->assertDontSee('Bem-vindo de volta')
            ->assertDontSee('Esqueceu sua senha')
            ->assertDontSee('Manter-me conectado')
            ->assertDontSee('PROGETOSENHAS')
            ->assertDontSee('progetoSenhas');
    }

    public function test_single_active_clinic_logo_is_shown_when_configured(): void
    {
        Storage::fake(ClinicSetting::DISK);

        $clinic = Clinic::factory()->create(['active' => true]);
        $path = app(ClinicBranding::class)->storeLogo(
            $clinic,
            'main_logo_path',
            UploadedFile::fake()->image('login-logo.png', 180, 64),
        );
        $logoUrl = app(ClinicBranding::class)->urlForPath($path);

        $this->get('/login')
            ->assertOk()
            ->assertSee($logoUrl, false)
            ->assertDontSee('>hC</span>', false);
    }

    public function test_multi_clinic_install_does_not_expose_tenant_logo_before_login(): void
    {
        Storage::fake(ClinicSetting::DISK);

        $clinicA = Clinic::factory()->create(['active' => true, 'name' => 'Clinica Alpha']);
        Clinic::factory()->create(['active' => true, 'name' => 'Clinica Beta']);

        $path = app(ClinicBranding::class)->storeLogo(
            $clinicA,
            'main_logo_path',
            UploadedFile::fake()->image('alpha-logo.png', 180, 64),
        );
        $logoUrl = app(ClinicBranding::class)->urlForPath($path);

        $this->get('/login')
            ->assertOk()
            ->assertDontSee($logoUrl, false)
            ->assertSee('humanaClinica');
    }

    public function test_login_presentation_resolves_only_for_single_active_clinic(): void
    {
        Storage::fake(ClinicSetting::DISK);

        $clinic = Clinic::factory()->create(['active' => true]);
        Clinic::factory()->create(['active' => false]);

        $path = app(ClinicBranding::class)->storeLogo(
            $clinic,
            'main_logo_path',
            UploadedFile::fake()->image('only-active.png', 120, 48),
        );
        $logoUrl = app(ClinicBranding::class)->urlForPath($path);

        $presentation = LoginPresentation::forGuest(
            app(ClinicBranding::class),
            app(ClinicSettings::class),
        );

        $this->assertTrue($presentation->hasConfiguredLogo);
        $this->assertSame($logoUrl, $presentation->logoUrl);
        $this->assertSame((string) config('app.name'), $presentation->productName);
    }
}
