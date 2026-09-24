<?php

namespace Tests\Feature;

use App\Actions\ClaimDesk;
use App\Actions\IssueTicket;
use App\Models\Clinic;
use App\Models\ClinicSetting;
use App\Models\Desk;
use App\Models\DisplayPanel;
use App\Models\TicketCall;
use App\Models\TicketType;
use App\Models\Unit;
use App\Models\User;
use App\Services\ClinicBranding;
use App\Services\ClinicSettings;
use App\Services\DisplayPanelFeed;
use App\Services\OperationalContext;
use App\TicketCallType;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClinicSettingsTvIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tv_respects_display_name_footer_count_and_toggles(): void
    {
        Storage::fake(ClinicSetting::DISK);

        $clinic = Clinic::factory()->create(['name' => 'Nome Legal']);
        $unit = Unit::factory()->for($clinic)->create(['name' => 'Recepção']);
        $panel = DisplayPanel::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'name' => 'TV 1',
        ]);

        $settings = app(ClinicSettings::class);
        $settings->putMany($clinic, [
            'display_name' => 'Marca TV',
            'tv_show_date' => false,
            'tv_show_ticket_type' => false,
            'tv_footer_enabled' => true,
            'tv_footer_1_title' => 'Bloco Custom',
            'tv_recent_calls_count' => 3,
            'tv_chime_enabled' => false,
            'tv_speech_enabled' => true,
        ]);

        app(ClinicBranding::class)->storeLogo(
            $clinic,
            'tv_logo_path',
            UploadedFile::fake()->image('tv.png'),
        );

        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);
        $type = $this->type($clinic, 'Preferencial', 'P', 20);
        $desk = Desk::factory()->create(['clinic_id' => $clinic->id, 'unit_id' => $unit->id, 'name' => 'Mesa 02']);
        $attendant = $this->attendant($clinic, $unit);

        $this->actingAs($admin);
        $tickets = collect([
            app(IssueTicket::class)->handle($admin, $unit->id, $type->id),
            app(IssueTicket::class)->handle($admin, $unit->id, $type->id),
            app(IssueTicket::class)->handle($admin, $unit->id, $type->id),
            app(IssueTicket::class)->handle($admin, $unit->id, $type->id),
        ]);

        $this->actingAs($attendant);
        app(OperationalContext::class)->setActiveUnit($attendant, $unit, session());
        app(ClaimDesk::class)->handle($attendant, $desk);

        foreach ($tickets as $index => $ticket) {
            $call = new TicketCall;
            $call->forceFill([
                'clinic_id' => $clinic->id,
                'unit_id' => $unit->id,
                'ticket_id' => $ticket->id,
                'desk_id' => $desk->id,
                'called_by_user_id' => $attendant->id,
                'call_type' => TicketCallType::INITIAL,
                'called_at' => now()->subMinutes(4 - $index),
            ])->save();
        }

        $feed = app(DisplayPanelFeed::class)->build($panel->fresh(['clinic', 'unit']));

        $this->assertSame('Marca TV', $feed['clinic_name']);
        $this->assertFalse($feed['presentation']['show_date']);
        $this->assertFalse($feed['presentation']['show_ticket_type']);
        $this->assertTrue($feed['presentation']['footer_enabled']);
        $this->assertSame('Bloco Custom', $feed['presentation']['footer_1']['title']);
        $this->assertFalse($feed['presentation']['chime_enabled']);
        $this->assertCount(3, $feed['recent_calls']);
        $this->assertNotNull($feed['presentation']['logo_url']);

        $response = $this->get(route('tv.panel', $panel->public_token))->assertOk();

        $response
            ->assertSee('Bloco Custom')
            ->assertSee('tv-brand-logo', false)
            ->assertSee($feed['presentation']['logo_url'], false)
            ->assertDontSee('<p class="min-w-0 truncate text-base font-bold tracking-tight sm:text-xl md:text-2xl lg:text-3xl">', false)
            ->assertDontSee('Painel de chamadas');
    }

    public function test_tv_footer_band_uses_clinic_brand_colors_without_oklab_gradient(): void
    {
        $clinicA = Clinic::factory()->create();
        $unitA = Unit::factory()->for($clinicA)->create();
        $panelA = DisplayPanel::factory()->create([
            'clinic_id' => $clinicA->id,
            'unit_id' => $unitA->id,
            'active' => true,
        ]);

        $clinicB = Clinic::factory()->create();
        $unitB = Unit::factory()->for($clinicB)->create();
        $panelB = DisplayPanel::factory()->create([
            'clinic_id' => $clinicB->id,
            'unit_id' => $unitB->id,
            'active' => true,
        ]);

        app(ClinicSettings::class)->putMany($clinicA, [
            'primary_color' => '#1E3A5F',
            'accent_color' => '#2563EB',
        ]);
        app(ClinicSettings::class)->putMany($clinicB, [
            'primary_color' => '#7C2D12',
            'accent_color' => '#EA580C',
        ]);

        $feedA = app(DisplayPanelFeed::class)->build($panelA->fresh(['clinic', 'unit']));
        $feedB = app(DisplayPanelFeed::class)->build($panelB->fresh(['clinic', 'unit']));

        $this->assertSame('#1E3A5F', strtoupper((string) $feedA['presentation']['primary_color']));
        $this->assertSame('#2563EB', strtoupper((string) $feedA['presentation']['accent_color']));
        $this->assertSame('#7C2D12', strtoupper((string) $feedB['presentation']['primary_color']));
        $this->assertSame('#EA580C', strtoupper((string) $feedB['presentation']['accent_color']));

        $htmlA = $this->get(route('tv.panel', $panelA->public_token))->assertOk()->getContent();
        $htmlB = $this->get(route('tv.panel', $panelB->public_token))->assertOk()->getContent();

        $this->assertStringContainsString('linear-gradient(to right', $htmlA);
        $this->assertStringContainsString('#2563EB', strtoupper($htmlA));
        $this->assertStringContainsString('#1E3A5F', strtoupper($htmlA));
        $this->assertStringNotContainsString('bg-gradient-to-r', $htmlA);
        $this->assertStringNotContainsString('from-success', $htmlA);
        $this->assertStringNotContainsString('via-accent', $htmlA);
        $this->assertStringNotContainsString('in oklab', $htmlA);

        $this->assertStringContainsString('#EA580C', strtoupper($htmlB));
        $this->assertStringContainsString('#7C2D12', strtoupper($htmlB));
        $this->assertStringNotContainsString('#2563EB', strtoupper($htmlB));
    }

    private function attendant(Clinic $clinic, Unit $unit): User
    {
        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ATTENDANT,
        ]);
        $user->units()->detach();
        $user->units()->attach($unit->id, ['clinic_id' => $clinic->id]);

        return $user;
    }

    private function type(Clinic $clinic, string $name, string $prefix, int $priority): TicketType
    {
        $type = new TicketType;
        $type->forceFill([
            'clinic_id' => $clinic->id,
            'name' => $name,
            'prefix' => $prefix,
            'priority' => $priority,
            'active' => true,
        ])->save();

        return $type->refresh();
    }
}
