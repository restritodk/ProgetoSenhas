<?php

namespace Tests\Feature;

use App\Actions\CallNextTicket;
use App\Actions\ClaimDesk;
use App\Actions\EnsureDefaultSectorForUnit;
use App\Actions\IssueTicket;
use App\Actions\RecallTicket;
use App\Actions\ReleaseDesk;
use App\Livewire\TvDisplay;
use App\Models\Clinic;
use App\Models\Desk;
use App\Models\DisplayPanel;
use App\Models\TicketCall;
use App\Models\TicketType;
use App\Models\Unit;
use App\Models\User;
use App\Services\DisplayPanelFeed;
use App\Services\OperationalContext;
use App\TicketCallType;
use App\TicketStatus;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TvSmartTvCallEffectTest extends TestCase
{
    use RefreshDatabase;

    private const SAMSUNG_TV_UA = 'Mozilla/5.0 (SMART-TV; Linux; Tizen 6.0) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/4.0 Chrome/76.0.3809.146 TV Safari/537.36';

    private const DESKTOP_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    public function test_smart_tv_panel_preloads_effect_mp3_and_exposes_detection(): void
    {
        $panel = $this->panel();

        $html = $this->withHeader('User-Agent', self::SAMSUNG_TV_UA)
            ->get(route('tv.panel', ['publicToken' => $panel->public_token]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('EfeitoSonoroTV.mp3', $html);
        $this->assertStringContainsString('<audio', $html);
        $this->assertStringContainsString('data-tv-call-effect', $html);
        $this->assertStringContainsString('preload="auto"', $html);
        $this->assertStringContainsString('isSmartTvBrowser', $html);
        $this->assertStringContainsString('smart-tv effect selected', $html);
        $this->assertTrue(
            str_contains($html, '"isSmartTv":true')
            || str_contains($html, '\u0022isSmartTv\u0022:true')
            || str_contains($html, '&quot;isSmartTv&quot;:true'),
            'Smart TV flag must be true in the audio UI bootstrap payload'
        );
        // Smart TV plays the ding, then the server audio. It must not call speechSynthesis.
        $this->assertStringContainsString('Never speechSynthesis on this branch', $html);
        $this->assertStringContainsString('playNext', $html);
    }

    public function test_desktop_panel_does_not_embed_smart_tv_effect_element(): void
    {
        $panel = $this->panel();

        $html = $this->withHeader('User-Agent', self::DESKTOP_UA)
            ->get(route('tv.panel', ['publicToken' => $panel->public_token]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('isSmartTvBrowser', $html);
        $this->assertTrue(
            str_contains($html, '"isSmartTv":false')
            || str_contains($html, '\u0022isSmartTv\u0022:false')
            || str_contains($html, '&quot;isSmartTv&quot;:false'),
            'Desktop flag must be false in the audio UI bootstrap payload'
        );
        $this->assertStringContainsString('speechSynthesis', $html);
        $this->assertStringContainsString('playChime', $html);
        $this->assertStringContainsString('SpeechSynthesisUtterance', $html);
        // Desktop keeps the preload element but announce must not select the Smart TV branch.
        $this->assertStringContainsString('smart-tv effect selected', $html);
        $this->assertStringContainsString('/sond/EfeitoSonoroTV.mp3', $html);
        $this->assertStringContainsString('Do not call audio.load() here', $html);
    }

    public function test_initial_and_recall_dispatch_for_smart_tv_without_role_filter(): void
    {
        [$panel, $unit, $desk, $attendant, $admin, $type] = $this->ready();

        $this->actingAs($admin);
        $ticket = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);

        $component = Livewire::test(TvDisplay::class, ['publicToken' => $panel->public_token]);

        $this->actingAs($attendant);
        app(OperationalContext::class)->setActiveUnit($attendant, $unit, session());
        app(ClaimDesk::class)->handle($attendant, $desk);
        app(CallNextTicket::class)->handle($attendant);

        $component->call('refreshFeed')->assertDispatched('tv-new-call');
        $initialId = (int) $component->get('lastAnnouncedCallId');
        $this->assertGreaterThan(0, $initialId);

        $this->actingAs($attendant);
        app(RecallTicket::class)->handle($attendant, $ticket->fresh());

        $component->call('refreshFeed')->assertDispatched('tv-new-call');
        $this->assertNotSame($initialId, (int) $component->get('lastAnnouncedCallId'));

        $recall = TicketCall::query()
            ->where('ticket_id', $ticket->id)
            ->where('call_type', TicketCallType::RECALL)
            ->orderByDesc('id')
            ->firstOrFail();

        $this->assertSame($recall->id, (int) $component->get('lastAnnouncedCallId'));
    }

    public function test_admin_supervisor_and_attendant_calls_reach_same_panel_feed(): void
    {
        [$panel, $unit, $desk, $attendant, $admin, $type] = $this->ready();
        $supervisor = User::factory()->create([
            'clinic_id' => $admin->clinic_id,
            'role' => UserRole::SUPERVISOR,
        ]);
        $supervisor->units()->attach($unit->id, ['clinic_id' => $admin->clinic_id]);

        foreach ([$attendant, $admin, $supervisor] as $actor) {
            $this->actingAs($admin);
            app(IssueTicket::class)->handle($admin, $unit->id, $type->id);

            $this->actingAs($actor);
            app(OperationalContext::class)->setActiveUnit($actor, $unit, session());
            app(ClaimDesk::class)->handle($actor, $desk);
            $called = app(CallNextTicket::class)->handle($actor);
            $this->assertNotNull($called);

            $feed = app(DisplayPanelFeed::class)->build($panel->fresh(['clinic', 'unit', 'sectors']));
            $this->assertSame($called->display_code, $feed['current_call']['display_code']);
            $this->assertNotEmpty($feed['current_call']['announcement']);

            $called->forceFill([
                'status' => TicketStatus::COMPLETED,
                'completed_at' => now(),
                'current_desk_id' => null,
            ])->save();
            app(ReleaseDesk::class)->handle($actor);
            app(OperationalContext::class)->clear(session());
        }
    }

    public function test_two_panels_same_sector_both_see_call_independently(): void
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create();
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);
        $sector = app(EnsureDefaultSectorForUnit::class)->handle($unit);

        $panelA = DisplayPanel::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'active' => true,
        ]);
        $panelB = DisplayPanel::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'active' => true,
        ]);
        $panelA->sectors()->sync([$sector->id => ['clinic_id' => $clinic->id]]);
        $panelB->sectors()->sync([$sector->id => ['clinic_id' => $clinic->id]]);

        $desk = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'sector_id' => $sector->id,
        ]);
        $type = TicketType::factory()->create([
            'clinic_id' => $clinic->id,
            'prefix' => 'P',
            'priority' => 20,
        ]);

        $this->actingAs($admin);
        app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        app(OperationalContext::class)->setActiveUnit($admin, $unit, session());
        app(ClaimDesk::class)->handle($admin, $desk);
        app(CallNextTicket::class)->handle($admin);

        $feed = app(DisplayPanelFeed::class);
        $a = $feed->build($panelA->fresh(['clinic', 'unit', 'sectors']));
        $b = $feed->build($panelB->fresh(['clinic', 'unit', 'sectors']));

        $this->assertSame($a['current_call']['id'], $b['current_call']['id']);

        Livewire::test(TvDisplay::class, ['publicToken' => $panelA->public_token])
            ->call('refreshFeed');

        $stillB = $feed->build($panelB->fresh(['clinic', 'unit', 'sectors']));
        $this->assertSame($a['current_call']['id'], $stillB['current_call']['id']);
    }

    public function test_effect_file_is_present_in_public_sond(): void
    {
        $this->assertFileExists(public_path('sond/EfeitoSonoroTV.mp3'));
        $this->assertGreaterThan(1000, filesize(public_path('sond/EfeitoSonoroTV.mp3')));
    }

    public function test_effect_mp3_is_served_as_audio_mpeg(): void
    {
        $path = public_path('sond/EfeitoSonoroTV.mp3');
        $this->assertFileExists($path);

        $response = $this->get(route('tv.call-effect'));

        $response->assertOk();
        $contentType = (string) $response->headers->get('Content-Type');
        $this->assertTrue(
            str_contains($contentType, 'audio/mpeg')
            || str_contains($contentType, 'audio/mp3'),
            'Expected audio/mpeg Content-Type, got: '.$contentType
        );
        $length = (int) $response->headers->get('Content-Length');
        if ($length === 0) {
            $length = (int) filesize($path);
        }
        $this->assertGreaterThan(1000, $length);
    }

    private function panel(array $overrides = []): DisplayPanel
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create();
        $sector = app(EnsureDefaultSectorForUnit::class)->handle($unit);
        $panel = DisplayPanel::factory()->create(array_merge([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'active' => true,
        ], $overrides));
        $panel->sectors()->sync([$sector->id => ['clinic_id' => $clinic->id]]);

        return $panel->fresh(['clinic', 'unit', 'sectors']);
    }

    /**
     * @return array{0: DisplayPanel, 1: Unit, 2: Desk, 3: User, 4: User, 5: TicketType}
     */
    private function ready(): array
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create();
        $sector = app(EnsureDefaultSectorForUnit::class)->handle($unit);
        $panel = DisplayPanel::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'active' => true,
        ]);
        $panel->sectors()->sync([$sector->id => ['clinic_id' => $clinic->id]]);
        $desk = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'sector_id' => $sector->id,
        ]);
        $admin = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);
        $admin->units()->attach($unit->id, ['clinic_id' => $clinic->id]);
        $attendant = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ATTENDANT,
        ]);
        $attendant->units()->attach($unit->id, ['clinic_id' => $clinic->id]);
        $type = TicketType::factory()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Preferencial',
            'prefix' => 'P',
            'priority' => 20,
        ]);

        return [$panel, $unit, $desk, $attendant, $admin, $type];
    }
}
