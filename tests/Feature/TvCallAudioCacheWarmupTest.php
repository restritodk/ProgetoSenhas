<?php

namespace Tests\Feature;

use App\Actions\CallNextTicket;
use App\Actions\ClaimDesk;
use App\Actions\EnsureDefaultSectorForUnit;
use App\Actions\IssueTicket;
use App\Actions\RecallTicket;
use App\Actions\WarmTicketCallAnnouncementAudio;
use App\Contracts\TvSpeechSynthesizer;
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
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Support\FakeTvSpeechSynthesizer;
use Tests\TestCase;

class TvCallAudioCacheWarmupTest extends TestCase
{
    use RefreshDatabase;

    public function test_call_and_recall_audio_is_stored_before_the_tv_requests_it(): void
    {
        [$panel, $unit, $desk, $admin, $type] = $this->ready();
        $fake = $this->bindSynthesizer(new FakeTvSpeechSynthesizer);
        $this->withoutDefer();

        $this->actingAs($admin);
        $ticket = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        app(OperationalContext::class)->setActiveUnit($admin, $unit, session());
        app(ClaimDesk::class)->handle($admin, $desk);
        app(CallNextTicket::class)->handle($admin);

        $this->assertSame(1, $fake->synthesizeCalls);
        $this->assertCount(1, Storage::disk('local')->files('tv-tts'));

        $initial = TicketCall::query()->where('ticket_id', $ticket->id)->firstOrFail();
        $token = $panel->public_code ?: $panel->public_token;

        $this->get(route('tv.tts', ['publicToken' => $token, 'ticketCall' => $initial->id]))
            ->assertOk()
            ->assertHeader('Content-Type', 'audio/mpeg');
        $this->assertSame(1, $fake->synthesizeCalls, 'The TV must receive the stored file');

        app(RecallTicket::class)->handle($admin, $ticket->fresh());
        $recall = TicketCall::query()
            ->where('ticket_id', $ticket->id)
            ->where('call_type', TicketCallType::RECALL)
            ->firstOrFail();

        $this->get(route('tv.tts', ['publicToken' => $token, 'ticketCall' => $recall->id]))
            ->assertOk();
        $this->assertSame(1, $fake->synthesizeCalls, 'Recall reuses the same stored phrase');
    }

    public function test_synthesis_failure_does_not_block_the_attendant_call(): void
    {
        [, $unit, $desk, $admin, $type] = $this->ready();
        $this->bindSynthesizer(new class extends FakeTvSpeechSynthesizer
        {
            public function synthesizeWav(string $text): string
            {
                throw new RuntimeException('google down');
            }
        });
        $this->withoutDefer();

        $this->actingAs($admin);
        $ticket = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        app(OperationalContext::class)->setActiveUnit($admin, $unit, session());
        app(ClaimDesk::class)->handle($admin, $desk);

        $called = app(CallNextTicket::class)->handle($admin);

        $this->assertNotNull($called);
        $this->assertTrue($called->is($ticket));
        $this->assertSame([], Storage::disk('local')->files('tv-tts'));
    }

    public function test_warmup_skips_missing_calls_and_unavailable_synthesizer(): void
    {
        $fake = $this->bindSynthesizer(new FakeTvSpeechSynthesizer);

        app(WarmTicketCallAnnouncementAudio::class)->handle(999999);
        $this->assertSame(0, $fake->synthesizeCalls);

        [, $unit, $desk, $admin, $type] = $this->ready();
        $fake->available = false;
        $this->withoutDefer();

        $this->actingAs($admin);
        app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        app(OperationalContext::class)->setActiveUnit($admin, $unit, session());
        app(ClaimDesk::class)->handle($admin, $desk);
        app(CallNextTicket::class)->handle($admin);

        $this->assertSame(0, $fake->synthesizeCalls);
    }

    public function test_feed_audio_url_is_relative_to_the_host_the_tv_used(): void
    {
        [$panel, $unit, $desk, $admin, $type] = $this->ready();
        $this->bindSynthesizer(new FakeTvSpeechSynthesizer);

        $this->actingAs($admin);
        app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        app(OperationalContext::class)->setActiveUnit($admin, $unit, session());
        app(ClaimDesk::class)->handle($admin, $desk);
        app(CallNextTicket::class)->handle($admin);

        $feed = app(DisplayPanelFeed::class)->build($panel->fresh(['clinic', 'unit', 'sectors']));
        $audioUrl = (string) $feed['current_call']['audio_url'];

        $this->assertStringStartsWith('/painel/', $audioUrl);
        $this->assertStringNotContainsString('://', $audioUrl);
        $this->assertStringNotContainsString(storage_path(), $audioUrl);
    }

    private function bindSynthesizer(FakeTvSpeechSynthesizer $fake): FakeTvSpeechSynthesizer
    {
        Storage::fake('local');
        $fake->extension = 'mp3';
        $fake->mimeType = 'audio/mpeg';
        $this->app->instance(TvSpeechSynthesizer::class, $fake);
        config([
            'tv_tts.enabled' => true,
            'tv_tts.driver' => 'google',
            'tv_tts.cache_directory' => 'tv-tts',
        ]);

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
            'name' => 'Mesa 04',
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
