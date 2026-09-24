<?php

namespace Tests\Feature;

use App\Actions\CallNextTicket;
use App\Actions\ClaimDesk;
use App\Actions\EnsureDefaultSectorForUnit;
use App\Actions\IssueTicket;
use App\Actions\RecallTicket;
use App\Contracts\TvSpeechSynthesizer;
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
use App\Services\TvTts\TvTtsService;
use App\TicketCallType;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FakeTvSpeechSynthesizer;
use Tests\TestCase;

class TvTtsFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_includes_audio_url_when_tts_enabled_and_omits_when_disabled(): void
    {
        [$panel, $unit, $desk, $admin, $type] = $this->ready();
        $this->bindFakeSynthesizer();

        $this->actingAs($admin);
        app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        app(OperationalContext::class)->setActiveUnit($admin, $unit, session());
        app(ClaimDesk::class)->handle($admin, $desk);
        app(CallNextTicket::class)->handle($admin);

        config(['tv_tts.enabled' => true]);
        $feed = app(DisplayPanelFeed::class)->build($panel->fresh(['clinic', 'unit', 'sectors']));
        $this->assertNotEmpty($feed['current_call']['announcement']);
        $this->assertNotEmpty($feed['current_call']['audio_url']);
        $this->assertStringContainsString('/tts/', $feed['current_call']['audio_url']);

        config(['tv_tts.enabled' => false]);
        $feedOff = app(DisplayPanelFeed::class)->build($panel->fresh(['clinic', 'unit', 'sectors']));
        $this->assertNull($feedOff['current_call']['audio_url']);
    }

    public function test_tts_endpoint_serves_wav_for_initial_and_recall_with_cache_reuse(): void
    {
        [$panel, $unit, $desk, $admin, $type] = $this->ready();
        $fake = $this->bindFakeSynthesizer();
        config([
            'tv_tts.enabled' => true,
            'tv_tts.cache_directory' => 'tv-tts-test-'.uniqid('', true),
        ]);

        $this->actingAs($admin);
        $ticket = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        app(OperationalContext::class)->setActiveUnit($admin, $unit, session());
        app(ClaimDesk::class)->handle($admin, $desk);
        app(CallNextTicket::class)->handle($admin);

        $initial = TicketCall::query()
            ->where('ticket_id', $ticket->id)
            ->where('call_type', TicketCallType::INITIAL)
            ->firstOrFail();

        $token = $panel->public_code ?: $panel->public_token;
        $url = route('tv.tts', ['publicToken' => $token, 'ticketCall' => $initial->id]);

        $response = $this->get($url);
        $response->assertOk();
        $this->assertStringStartsWith('audio/wav', (string) $response->headers->get('Content-Type'));

        $this->assertSame(1, $fake->synthesizeCalls);

        $this->get($url)->assertOk();
        $this->assertSame(1, $fake->synthesizeCalls, 'Identical announcement must reuse cached WAV');

        app(RecallTicket::class)->handle($admin, $ticket->fresh());
        $recall = TicketCall::query()
            ->where('ticket_id', $ticket->id)
            ->where('call_type', TicketCallType::RECALL)
            ->orderByDesc('id')
            ->firstOrFail();

        $recallResponse = $this->get(route('tv.tts', ['publicToken' => $token, 'ticketCall' => $recall->id]));
        $recallResponse->assertOk();
        $this->assertStringStartsWith('audio/wav', (string) $recallResponse->headers->get('Content-Type'));

        // INITIAL and RECALL share the same spoken phrase today → still one synthesis.
        $this->assertSame(1, $fake->synthesizeCalls);
    }

    public function test_tts_endpoint_returns_404_for_foreign_panel_or_unknown_call(): void
    {
        [$panel, $unit, $desk, $admin, $type] = $this->ready();
        $this->bindFakeSynthesizer();
        config(['tv_tts.enabled' => true]);

        $otherClinic = Clinic::factory()->create();
        $otherUnit = Unit::factory()->for($otherClinic)->create();
        $otherPanel = DisplayPanel::factory()->create([
            'clinic_id' => $otherClinic->id,
            'unit_id' => $otherUnit->id,
            'active' => true,
        ]);

        $this->actingAs($admin);
        app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        app(OperationalContext::class)->setActiveUnit($admin, $unit, session());
        app(ClaimDesk::class)->handle($admin, $desk);
        app(CallNextTicket::class)->handle($admin);

        $call = TicketCall::query()->latest('id')->firstOrFail();
        $token = $panel->public_code ?: $panel->public_token;
        $otherToken = $otherPanel->public_code ?: $otherPanel->public_token;

        $this->get(route('tv.tts', ['publicToken' => $otherToken, 'ticketCall' => $call->id]))
            ->assertNotFound();

        $this->get(route('tv.tts', ['publicToken' => $token, 'ticketCall' => 999999]))
            ->assertNotFound();
    }

    public function test_tts_unavailable_returns_503_without_breaking_tv_feed(): void
    {
        [$panel, $unit, $desk, $admin, $type] = $this->ready();
        $fake = $this->bindFakeSynthesizer();
        $fake->available = false;
        config(['tv_tts.enabled' => true]);

        $this->actingAs($admin);
        app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        app(OperationalContext::class)->setActiveUnit($admin, $unit, session());
        app(ClaimDesk::class)->handle($admin, $desk);
        app(CallNextTicket::class)->handle($admin);

        $feed = app(DisplayPanelFeed::class)->build($panel->fresh(['clinic', 'unit', 'sectors']));
        $this->assertNotNull($feed['current_call']);
        $this->assertNotEmpty($feed['current_call']['announcement']);
        $this->assertNotEmpty($feed['current_call']['audio_url']);

        $callId = (int) $feed['current_call']['id'];
        $token = $panel->public_code ?: $panel->public_token;

        $this->get(route('tv.tts', ['publicToken' => $token, 'ticketCall' => $callId]))
            ->assertStatus(503);

        Livewire::test(TvDisplay::class, ['publicToken' => $panel->public_token])
            ->assertSee($feed['current_call']['display_code'])
            ->call('refreshFeed');
    }

    public function test_two_panels_same_sector_each_get_independent_audio_urls(): void
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

        $this->bindFakeSynthesizer();
        config(['tv_tts.enabled' => true]);

        $this->actingAs($admin);
        app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        app(OperationalContext::class)->setActiveUnit($admin, $unit, session());
        app(ClaimDesk::class)->handle($admin, $desk);
        app(CallNextTicket::class)->handle($admin);

        $feed = app(DisplayPanelFeed::class);
        $a = $feed->build($panelA->fresh(['clinic', 'unit', 'sectors']));
        $b = $feed->build($panelB->fresh(['clinic', 'unit', 'sectors']));

        $this->assertSame($a['current_call']['id'], $b['current_call']['id']);
        $this->assertNotSame($a['current_call']['audio_url'], $b['current_call']['audio_url']);
        $this->assertStringContainsString((string) ($panelA->public_code ?: $panelA->public_token), $a['current_call']['audio_url']);
        $this->assertStringContainsString((string) ($panelB->public_code ?: $panelB->public_token), $b['current_call']['audio_url']);
    }

    public function test_ensure_cached_wav_reuses_identical_text(): void
    {
        $fake = $this->bindFakeSynthesizer();
        config([
            'tv_tts.enabled' => true,
            'tv_tts.cache_directory' => 'tv-tts-test-'.uniqid('', true),
        ]);

        $service = app(TvTtsService::class);
        $text = 'Senha preferencial P zero zero seis, dirigir-se ao guichê dois.';

        $first = $service->ensureCachedWav($text);
        $second = $service->ensureCachedWav($text);

        $this->assertNotNull($first);
        $this->assertSame($first, $second);
        $this->assertSame(1, $fake->synthesizeCalls);
        $this->assertFileExists($first);

        @unlink($first);
    }

    private function bindFakeSynthesizer(): FakeTvSpeechSynthesizer
    {
        $fake = new FakeTvSpeechSynthesizer;
        $this->app->instance(TvSpeechSynthesizer::class, $fake);

        return $fake;
    }

    /**
     * @return array{0: DisplayPanel, 1: Unit, 2: Desk, 3: User, 4: TicketType}
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
        $type = TicketType::factory()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Preferencial',
            'prefix' => 'P',
            'priority' => 20,
        ]);

        return [$panel, $unit, $desk, $admin, $type];
    }
}
