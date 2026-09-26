<?php

namespace Tests\Feature;

use App\Actions\AttachMediaToDisplayPanel;
use App\Actions\CallNextTicket;
use App\Actions\ClaimDesk;
use App\Actions\CreateMediaItem;
use App\Actions\IssueTicket;
use App\Actions\RecallTicket;
use App\Livewire\TvDisplay;
use App\Livewire\TvMediaPlayer;
use App\MediaType;
use App\Models\Clinic;
use App\Models\Desk;
use App\Models\DisplayPanel;
use App\Models\MediaItem;
use App\Models\TicketType;
use App\Models\Unit;
use App\Models\User;
use App\Services\OperationalContext;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class TvMediaCallIndependenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_player_keeps_call_sound_independent_from_media_mute(): void
    {
        $panel = $this->panelWithPlaylist();

        $html = Livewire::test(TvMediaPlayer::class, [
            'publicToken' => $panel->public_token,
            'clinicName' => $panel->clinic->name,
        ])->html();

        $this->assertStringContainsString('tvMediaPlayer', $html);
        $this->assertStringContainsString('wire:ignore', $html);
        $this->assertStringContainsString('callDucked', $html);
        $this->assertStringContainsString('restoreMediaAudioAfterCall', $html);
        $this->assertStringContainsString('enforceConfiguredMute', $html);
        $this->assertStringContainsString('play_with_audio === true', $html);
        $this->assertStringContainsString('tv-call-audio-begin', $html);
        $this->assertStringContainsString('tv-call-audio-end', $html);
        // Soft-resume remount regression must stay gone.
        $this->assertStringNotContainsString('resumeCurrentPlayback', $html);
        $this->assertStringNotContainsString('scheduleSoftResume', $html);
        $this->assertStringNotContainsString('pauseVideo', $html);
        $this->assertStringNotContainsString('stopVideo', $html);
        $this->assertStringNotContainsString('video.pause(', $html);
        // External pauses (TV audio focus) continue the same element after the call.
        $this->assertStringContainsString('onExternalInterruption', $html);
        $this->assertStringContainsString('continueAfterInterruption', $html);
        $this->assertStringNotContainsString('location.reload', $html);
    }

    public function test_call_audio_queue_is_guarded_against_stalls_and_duplicates(): void
    {
        $panel = $this->panelWithPlaylist();

        $html = Livewire::test(TvDisplay::class, [
            'publicToken' => $panel->public_token,
        ])->html();

        $this->assertStringContainsString('MAX_CALL_MS', $html);
        $this->assertStringContainsString('LOAD_TIMEOUT_MS', $html);
        $this->assertStringContainsString('playedIds', $html);
        $this->assertStringContainsString('isCurrentItem', $html);
        $this->assertStringContainsString('tv-call-audio-blocked', $html);
        $this->assertStringNotContainsString('location.reload', $html);
    }

    public function test_tv_display_isolates_media_island_from_call_updates(): void
    {
        $panel = $this->panelWithPlaylist();

        $html = Livewire::test(TvDisplay::class, [
            'publicToken' => $panel->public_token,
        ])->html();

        $this->assertStringContainsString('tvMediaPlayer', $html);
        $this->assertStringContainsString('__humanaTvCallAudio', $html);
        $this->assertStringContainsString('type=island|name=tv-media', $html);
        $this->assertStringContainsString('ENDFRAGMENT:type=island|name=tv-media', $html);
    }

    public function test_ticket_call_and_recall_update_call_ui_without_playlist_event(): void
    {
        Storage::fake(MediaItem::DISK);
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create();
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);
        $attendant = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ATTENDANT]);
        $attendant->units()->detach();
        $attendant->units()->attach($unit->id, ['clinic_id' => $clinic->id]);

        $panel = DisplayPanel::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'active' => true,
        ]);

        $this->actingAs($admin);
        $image = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'Banner',
            'type' => MediaType::IMAGE->value,
            'active' => true,
            'duration_seconds' => 15,
        ], UploadedFile::fake()->image('banner.jpg'));
        app(AttachMediaToDisplayPanel::class)->handle($admin, $panel, $image, 15);

        $type = new TicketType;
        $type->forceFill([
            'clinic_id' => $clinic->id,
            'name' => 'Normal',
            'prefix' => 'N',
            'priority' => 10,
            'active' => true,
        ])->save();

        $desk = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'name' => 'Guichê 01',
            'code' => 'G01',
            'active' => true,
        ]);

        app(IssueTicket::class)->handle($admin, $unit->id, $type->id);

        $tv = Livewire::test(TvDisplay::class, ['publicToken' => $panel->public_token])
            ->assertSee('Aguardando chamada');
        $media = Livewire::test(TvMediaPlayer::class, ['publicToken' => $panel->public_token]);
        $signature = $media->get('playlistSignature');
        $items = $media->get('items');

        $this->actingAs($attendant);
        app(OperationalContext::class)->setActiveUnit($attendant, $unit, session());
        app(ClaimDesk::class)->handle($attendant, $desk);
        $ticket = app(CallNextTicket::class)->handle($attendant);

        $tv->call('refreshFeed')
            ->assertSee($ticket->display_code)
            ->assertSee('Guichê 01')
            ->assertDispatched('tv-new-call');

        $media->call('refreshPlaylist')
            ->assertSet('playlistSignature', $signature)
            ->assertSet('items', $items)
            ->assertNotDispatched('tv-playlist-updated');

        app(RecallTicket::class)->handle($attendant, $ticket);

        $tv->call('refreshFeed')
            ->assertSee($ticket->fresh()->display_code)
            ->assertDispatched('tv-new-call');

        $media->call('refreshPlaylist')
            ->assertSet('playlistSignature', $signature)
            ->assertSet('items', $items)
            ->assertNotDispatched('tv-playlist-updated');
    }

    public function test_playlist_poll_skips_render_when_signature_unchanged(): void
    {
        $panel = $this->panelWithPlaylist();

        $component = Livewire::test(TvMediaPlayer::class, ['publicToken' => $panel->public_token]);
        $signature = $component->get('playlistSignature');

        $component->call('refreshPlaylist')
            ->assertSet('playlistSignature', $signature)
            ->assertNotDispatched('tv-playlist-updated');
    }

    private function panelWithPlaylist(): DisplayPanel
    {
        Storage::fake(MediaItem::DISK);
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create();
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);

        $panel = DisplayPanel::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'active' => true,
        ]);

        $this->actingAs($admin);
        $image = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'Institucional',
            'type' => MediaType::IMAGE->value,
            'active' => true,
            'duration_seconds' => 12,
        ], UploadedFile::fake()->image('i.jpg'));
        $video = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'Campanha',
            'type' => MediaType::VIDEO->value,
            'active' => true,
        ], UploadedFile::fake()->create('v.mp4', 120, 'video/mp4'));
        $youtube = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'YouTube',
            'type' => MediaType::YOUTUBE->value,
            'active' => true,
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        app(AttachMediaToDisplayPanel::class)->handle($admin, $panel, $image, 15);
        app(AttachMediaToDisplayPanel::class)->handle($admin, $panel, $video);
        app(AttachMediaToDisplayPanel::class)->handle($admin, $panel, $youtube);

        return $panel->fresh(['clinic', 'unit']);
    }
}
