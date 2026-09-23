<?php

namespace Tests\Feature;

use App\Livewire\PublicKiosk;
use App\Models\Clinic;
use App\Models\ClinicSetting;
use App\Models\Kiosk;
use App\Models\TicketType;
use App\Models\Unit;
use App\Models\UnitTicketType;
use App\Models\User;
use App\Services\ClinicBranding;
use App\Services\ClinicSettings;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ClinicSettingsKioskIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_kiosk_uses_configured_texts_logo_and_auto_return(): void
    {
        Storage::fake(ClinicSetting::DISK);

        $clinic = Clinic::factory()->create(['name' => 'Legal']);
        $unit = Unit::factory()->for($clinic)->create();
        $type = new TicketType;
        $type->forceFill([
            'clinic_id' => $clinic->id,
            'name' => 'Normal',
            'prefix' => 'N',
            'priority' => 10,
            'active' => true,
        ])->save();

        $offer = new UnitTicketType;
        $offer->forceFill([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'ticket_type_id' => $type->id,
            'position' => 1,
            'display_name' => null,
            'active' => true,
        ])->save();

        $kiosk = Kiosk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'active' => true,
        ]);

        app(ClinicSettings::class)->putMany($clinic, [
            'display_name' => 'Totem Brand',
            'kiosk_title' => 'Pegue sua senha',
            'kiosk_subtitle' => 'Escolha o atendimento',
            'kiosk_instruction_text' => 'Toque aqui',
            'kiosk_issued_message' => 'Aguarde no painel digital.',
            'kiosk_finish_button_text' => 'Concluir',
            'kiosk_auto_return_seconds' => 20,
        ]);

        app(ClinicBranding::class)->storeLogo(
            $clinic,
            'kiosk_logo_path',
            UploadedFile::fake()->image('kiosk.png'),
        );

        $this->get(route('kiosk.panel', $kiosk->public_token))
            ->assertOk()
            ->assertSee('Totem Brand')
            ->assertSee('Pegue sua senha')
            ->assertSee('Escolha o atendimento')
            ->assertSee('Toque aqui');

        Livewire::test(PublicKiosk::class, ['publicToken' => $kiosk->public_token])
            ->assertSet('presentation.auto_return_seconds', 20)
            ->assertSet('presentation.finish_button_text', 'Concluir')
            ->assertSet('presentation.issued_message', 'Aguarde no painel digital.')
            ->assertNotSet('presentation.logo_url', null);
    }

    public function test_kiosk_auto_return_out_of_bounds_rejected_in_settings(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);

        $this->expectException(ValidationException::class);
        app(ClinicSettings::class)->putMany($clinic, [
            'kiosk_auto_return_seconds' => 999,
        ]);

        unset($admin);
    }
}
