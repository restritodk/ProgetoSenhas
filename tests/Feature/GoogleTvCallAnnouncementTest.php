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
use App\Models\Sector;
use App\Models\TicketCall;
use App\Models\TicketType;
use App\Models\Unit;
use App\Models\User;
use App\Services\DisplayPanelFeed;
use App\Services\OperationalContext;
use App\Services\TicketCallVoiceFormatter;
use App\TicketCallType;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\Support\FakeTvSpeechSynthesizer;
use Tests\TestCase;

class GoogleTvCallAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_desk_and_code_are_spoken_and_another_desk_is_not(): void
    {
        [$panel, $unit, $deskMesa, $admin, $preferencial, $sector] = $this->ready();
        $this->bindGoogleFake();

        $deskGuiche = Desk::factory()->create([
            'clinic_id' => $unit->clinic_id,
            'unit_id' => $unit->id,
            'sector_id' => $sector->id,
            'name' => 'Guichê 02',
        ]);
        $normal = TicketType::factory()->create([
            'clinic_id' => $unit->clinic_id,
            'name' => 'Normal',
            'prefix' => 'N',
            'priority' => 10,
        ]);

        $attendantMesa = $this->attendant($unit);
        $attendantGuiche = $this->attendant($unit);

        $this->actingAs($admin);
        $preferencialTicket = app(IssueTicket::class)->handle($admin, $unit->id, $preferencial->id);
        $preferencialTicket->forceFill(['sequence_number' => 17])->save();

        $this->actingAs($attendantMesa);
        app(OperationalContext::class)->setActiveUnit($attendantMesa, $unit, session());
        app(ClaimDesk::class)->handle($attendantMesa, $deskMesa);
        $calledMesa = app(CallNextTicket::class)->handle($attendantMesa);
        $this->assertNotNull($calledMesa);
        $this->assertSame('P017', $calledMesa->fresh()->display_code);

        $this->actingAs($admin);
        $normalTicket = app(IssueTicket::class)->handle($admin, $unit->id, $normal->id);
        $normalTicket->forceFill(['sequence_number' => 25])->save();

        $this->actingAs($attendantGuiche);
        app(OperationalContext::class)->setActiveUnit($attendantGuiche, $unit, session());
        app(ClaimDesk::class)->handle($attendantGuiche, $deskGuiche);
        $calledGuiche = app(CallNextTicket::class)->handle($attendantGuiche);
        $this->assertNotNull($calledGuiche);
        $this->assertSame('N025', $calledGuiche->fresh()->display_code);

        $formatter = app(TicketCallVoiceFormatter::class);
        $mesaCall = TicketCall::query()->where('desk_id', $deskMesa->id)->with(['ticket.ticketType', 'desk'])->firstOrFail();
        $guicheCall = TicketCall::query()->where('desk_id', $deskGuiche->id)->with(['ticket.ticketType', 'desk'])->firstOrFail();

        $mesaPhrase = $formatter->announce($mesaCall, includeType: false);
        $guichePhrase = $formatter->announce($guicheCall, includeType: false);

        $this->assertSame('Senha P zero um sete. Dirigir-se à mesa quatro.', $mesaPhrase);
        $this->assertSame('Senha N zero dois cinco. Dirigir-se ao guichê dois.', $guichePhrase);
        $this->assertStringNotContainsString('guichê', $mesaPhrase);
        $this->assertStringNotContainsString('mesa quatro', $guichePhrase);

        $feed = app(DisplayPanelFeed::class)->build($panel->fresh(['clinic', 'unit', 'sectors']));
        $recent = collect($feed['recent_calls'])->keyBy('desk_name');

        $this->assertStringContainsString('mesa quatro', (string) $recent['Mesa 04']['announcement']);
        $this->assertStringContainsString('P zero um sete', (string) $recent['Mesa 04']['announcement']);
        $this->assertStringNotContainsString('guichê', (string) $recent['Mesa 04']['announcement']);

        $this->assertStringContainsString('guichê dois', (string) $recent['Guichê 02']['announcement']);
        $this->assertStringContainsString('N zero dois cinco', (string) $recent['Guichê 02']['announcement']);
        $this->assertStringNotContainsString('P zero um sete', (string) $recent['Guichê 02']['announcement']);
    }

    public function test_recall_reuses_cached_audio_and_polling_does_not_call_google(): void
    {
        [$panel, $unit, $desk, $admin, $type] = $this->ready();
        $fake = $this->bindGoogleFake();
        $fake->extension = 'mp3';
        $fake->mimeType = 'audio/mpeg';

        $this->actingAs($admin);
        $ticket = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        $ticket->forceFill(['sequence_number' => 17])->save();
        $sequence = $ticket->fresh()->sequence_number;

        app(OperationalContext::class)->setActiveUnit($admin, $unit, session());
        app(ClaimDesk::class)->handle($admin, $desk);
        app(CallNextTicket::class)->handle($admin);

        $feedBuilder = app(DisplayPanelFeed::class);
        $feedBuilder->build($panel->fresh(['clinic', 'unit', 'sectors']));
        $feedBuilder->build($panel->fresh(['clinic', 'unit', 'sectors']));
        $this->assertSame(0, $fake->synthesizeCalls, 'Polling the panel must not call the synthesizer');

        $initial = TicketCall::query()->where('ticket_id', $ticket->id)->where('call_type', TicketCallType::INITIAL)->firstOrFail();
        $token = $panel->public_code ?: $panel->public_token;
        $url = route('tv.tts', ['publicToken' => $token, 'ticketCall' => $initial->id]);

        $this->get($url.'?text=Texto+arbitrario+do+navegador')
            ->assertOk()
            ->assertHeader('Content-Type', 'audio/mpeg');

        $this->assertSame(1, $fake->synthesizeCalls);
        $this->assertStringContainsString('P zero um sete', $fake->texts[0]);
        $this->assertStringContainsString('mesa quatro', $fake->texts[0]);
        $this->assertStringNotContainsString('arbitrario', $fake->texts[0]);

        $this->get($url)->assertOk();
        $this->assertSame(1, $fake->synthesizeCalls);

        app(RecallTicket::class)->handle($admin, $ticket->fresh());

        $recalled = $ticket->fresh();
        $this->assertSame($ticket->id, $recalled->id);
        $this->assertSame($sequence, $recalled->sequence_number);
        $this->assertSame(2, TicketCall::query()->where('ticket_id', $ticket->id)->count());

        $recall = TicketCall::query()
            ->where('ticket_id', $ticket->id)
            ->where('call_type', TicketCallType::RECALL)
            ->firstOrFail();

        $this->get(route('tv.tts', ['publicToken' => $token, 'ticketCall' => $recall->id]))
            ->assertOk()
            ->assertHeader('Content-Type', 'audio/mpeg');

        $this->assertSame(1, $fake->synthesizeCalls, 'Recall must reuse the cached MP3');
    }

    public function test_google_failure_does_not_block_the_visual_call(): void
    {
        [$panel, $unit, $desk, $admin, $type] = $this->ready();

        $fake = new class extends FakeTvSpeechSynthesizer
        {
            public function synthesizeWav(string $text): string
            {
                throw new RuntimeException('google down');
            }
        };
        $fake->extension = 'mp3';
        $fake->mimeType = 'audio/mpeg';
        $this->app->instance(TvSpeechSynthesizer::class, $fake);
        config([
            'tv_tts.enabled' => true,
            'tv_tts.driver' => 'google',
            'tv_tts.cache_directory' => 'tv-tts-test-'.uniqid(),
        ]);

        $this->actingAs($admin);
        $ticket = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        app(OperationalContext::class)->setActiveUnit($admin, $unit, session());
        app(ClaimDesk::class)->handle($admin, $desk);
        $called = app(CallNextTicket::class)->handle($admin);

        $this->assertNotNull($called);
        $this->assertTrue($called->is($ticket));

        $feed = app(DisplayPanelFeed::class)->build($panel->fresh(['clinic', 'unit', 'sectors']));
        $this->assertSame($ticket->display_code, $feed['current_call']['display_code']);
        $this->assertNotEmpty($feed['current_call']['announcement']);
        $this->assertNotEmpty($feed['current_call']['audio_url']);

        $token = $panel->public_code ?: $panel->public_token;
        $this->get(route('tv.tts', [
            'publicToken' => $token,
            'ticketCall' => $feed['current_call']['id'],
        ]))->assertStatus(503);

        Livewire::test(TvDisplay::class, ['publicToken' => $panel->public_token])
            ->assertSee($ticket->display_code)
            ->call('refreshFeed')
            ->assertSee($ticket->display_code);
    }

    public function test_panel_payload_never_contains_google_credentials(): void
    {
        [$panel, $unit, $desk, $admin, $type] = $this->ready();
        $this->bindGoogleFake();

        $secret = 'C:/segredo/nao-expor-private_key.json';
        config([
            'services.google_tts.credentials' => $secret,
            'services.google_tts.voice_name' => 'pt-BR-Neural2-A',
        ]);

        $this->actingAs($admin);
        app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        app(OperationalContext::class)->setActiveUnit($admin, $unit, session());
        app(ClaimDesk::class)->handle($admin, $desk);
        app(CallNextTicket::class)->handle($admin);

        $feed = app(DisplayPanelFeed::class)->build($panel->fresh(['clinic', 'unit', 'sectors']));
        $serialized = (string) json_encode($feed);

        $this->assertStringNotContainsString($secret, $serialized);
        $this->assertStringNotContainsString('private_key', $serialized);
        $this->assertStringNotContainsString('GOOGLE_APPLICATION_CREDENTIALS', $serialized);
        $this->assertStringNotContainsString('client_email', $serialized);
        $this->assertStringNotContainsString('"clinic_id"', $serialized);
        $this->assertStringNotContainsString('"unit_id"', $serialized);
    }

    public function test_other_clinic_cannot_fetch_the_call_audio(): void
    {
        [$panel, $unit, $desk, $admin, $type] = $this->ready();
        $this->bindGoogleFake();

        $otherClinic = Clinic::factory()->create();
        $otherUnit = Unit::factory()->for($otherClinic)->create();
        $otherPanel = DisplayPanel::factory()->create([
            'clinic_id' => $otherClinic->id,
            'unit_id' => $otherUnit->id,
            'active' => true,
        ]);

        $this->actingAs($admin);
        $ticket = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        app(OperationalContext::class)->setActiveUnit($admin, $unit, session());
        app(ClaimDesk::class)->handle($admin, $desk);
        app(CallNextTicket::class)->handle($admin);

        $call = TicketCall::query()->latest('id')->firstOrFail();
        $otherToken = $otherPanel->public_code ?: $otherPanel->public_token;

        $this->get(route('tv.tts', ['publicToken' => $otherToken, 'ticketCall' => $call->id]))
            ->assertNotFound();

        $otherFeed = app(DisplayPanelFeed::class)->build($otherPanel->fresh(['clinic', 'unit', 'sectors']));
        $serialized = (string) json_encode($otherFeed);
        $this->assertStringNotContainsString($ticket->display_code, $serialized);
        $this->assertNull($otherFeed['current_call']);
    }

    public function test_existing_panel_refresh_still_announces_a_new_call(): void
    {
        [$panel, $unit, $desk, $admin, $type] = $this->ready();
        $this->bindGoogleFake();

        $component = Livewire::test(TvDisplay::class, ['publicToken' => $panel->public_token]);

        $this->actingAs($admin);
        $ticket = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);
        app(OperationalContext::class)->setActiveUnit($admin, $unit, session());
        app(ClaimDesk::class)->handle($admin, $desk);
        app(CallNextTicket::class)->handle($admin);

        $component->call('refreshFeed')
            ->assertDispatched('tv-new-call')
            ->assertSee($ticket->display_code);

        $this->assertSame(
            TicketCall::query()->where('ticket_id', $ticket->id)->value('id'),
            $component->get('lastAnnouncedCallId'),
        );
    }

    private function bindGoogleFake(): FakeTvSpeechSynthesizer
    {
        $fake = new FakeTvSpeechSynthesizer;
        $fake->extension = 'mp3';
        $fake->mimeType = 'audio/mpeg';
        $this->app->instance(TvSpeechSynthesizer::class, $fake);
        config([
            'tv_tts.enabled' => true,
            'tv_tts.driver' => 'google',
            'tv_tts.cache_directory' => 'tv-tts-test-'.uniqid(),
        ]);

        return $fake;
    }

    private function attendant(Unit $unit): User
    {
        $attendant = User::factory()->create([
            'clinic_id' => $unit->clinic_id,
            'role' => UserRole::ATTENDANT,
        ]);
        $attendant->units()->attach($unit->id, ['clinic_id' => $unit->clinic_id]);

        return $attendant;
    }

    /**
     * @return array{0: DisplayPanel, 1: Unit, 2: Desk, 3: User, 4: TicketType, 5: Sector}
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

        return [$panel, $unit, $desk, $admin, $type, $sector];
    }
}
