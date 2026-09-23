<?php

namespace Tests\Feature;

use App\Actions\AttachMediaToDisplayPanel;
use App\Actions\CreateMediaItem;
use App\Livewire\TvMediaPlayer;
use App\MediaType;
use App\Models\Clinic;
use App\Models\DisplayPanel;
use App\Models\MediaItem;
use App\Models\Unit;
use App\Models\User;
use App\Services\DisplayPanelPlaylist;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class TvMediaPlaylistTest extends TestCase
{
    use RefreshDatabase;

    public function test_tv_playlist_returns_ordered_active_items_only_for_panel_unit_clinic(): void
    {
        Storage::fake(MediaItem::DISK);
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create();
        $otherUnit = Unit::factory()->for($clinic)->create();
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);

        $panel = DisplayPanel::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'active' => true,
        ]);
        $otherPanel = DisplayPanel::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $otherUnit->id,
            'active' => true,
        ]);

        $this->actingAs($admin);
        $image = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'Institucional',
            'type' => MediaType::IMAGE->value,
            'active' => true,
            'duration_seconds' => 9,
        ], UploadedFile::fake()->image('a.jpg'));
        $video = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'Campanha',
            'type' => MediaType::VIDEO->value,
            'active' => true,
        ], UploadedFile::fake()->create('b.mp4', 200, 'video/mp4'));
        $youtube = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'YouTube Institucional',
            'type' => MediaType::YOUTUBE->value,
            'active' => true,
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);
        $inactive = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'Inativa',
            'type' => MediaType::IMAGE->value,
            'active' => true,
            'duration_seconds' => 5,
        ], UploadedFile::fake()->image('c.jpg'));

        app(AttachMediaToDisplayPanel::class)->handle($admin, $panel, $image, 15);
        app(AttachMediaToDisplayPanel::class)->handle($admin, $panel, $video);
        app(AttachMediaToDisplayPanel::class)->handle($admin, $panel, $youtube);
        $inactiveEntry = app(AttachMediaToDisplayPanel::class)->handle($admin, $panel, $inactive);
        $inactiveEntry->forceFill(['active' => false])->save();

        app(AttachMediaToDisplayPanel::class)->handle($admin, $otherPanel, $image);

        $playlist = app(DisplayPanelPlaylist::class)->forPanel($panel->fresh(['clinic', 'unit']));

        $this->assertCount(3, $playlist);
        $this->assertSame('Institucional', $playlist[0]['name']);
        $this->assertSame('image', $playlist[0]['type']);
        $this->assertSame(15, $playlist[0]['duration_seconds']);
        $this->assertSame('Campanha', $playlist[1]['name']);
        $this->assertSame('video', $playlist[1]['type']);
        $this->assertFalse($playlist[1]['play_with_audio']);
        $this->assertFalse($playlist[0]['play_with_audio']);
        $this->assertSame('YouTube Institucional', $playlist[2]['name']);
        $this->assertSame('youtube', $playlist[2]['type']);
        $this->assertFalse($playlist[2]['play_with_audio']);
        $this->assertSame('dQw4w9WgXcQ', $playlist[2]['video_id']);
        $this->assertSame(0, $playlist[2]['duration_seconds']);
        $this->assertStringContainsString('youtube-nocookie.com/embed/dQw4w9WgXcQ', (string) $playlist[2]['url']);
        $this->assertNotNull($playlist[0]['url']);
        $this->assertStringContainsString('/storage/', $playlist[0]['url']);

        $serialized = json_encode($playlist);
        $this->assertStringNotContainsString('"clinic_id"', $serialized);
        $this->assertStringNotContainsString('email', $serialized);
        $this->assertStringNotContainsString('<iframe', $serialized);

        Livewire::test(TvMediaPlayer::class, ['publicToken' => $panel->public_token])
            ->assertSet('items', function (array $items) use ($image, $video, $youtube): bool {
                return count($items) === 3
                    && $items[0]['media_item_id'] === $image->id
                    && $items[1]['media_item_id'] === $video->id
                    && $items[2]['media_item_id'] === $youtube->id
                    && $items[2]['type'] === 'youtube';
            })
            ->assertSee('tvMediaPlayer', false);

        $inactivePanel = DisplayPanel::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'active' => false,
        ]);
        $this->assertSame([], app(DisplayPanelPlaylist::class)->forPanel($inactivePanel->fresh(['clinic', 'unit'])));

        $foreignPanel = DisplayPanel::factory()->create([
            'clinic_id' => $otherClinic->id,
            'unit_id' => Unit::factory()->for($otherClinic)->create()->id,
        ]);
        $this->assertSame([], app(DisplayPanelPlaylist::class)->forPanel($foreignPanel->load(['clinic', 'unit'])));
    }

    public function test_empty_playlist_is_safe_fallback_on_tv(): void
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create();
        $panel = DisplayPanel::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
        ]);

        $this->get(route('tv.panel', $panel->public_token))
            ->assertOk()
            ->assertSee($clinic->name);

        Livewire::test(TvMediaPlayer::class, [
            'publicToken' => $panel->public_token,
            'clinicName' => $clinic->name,
        ])
            ->assertSet('items', [])
            ->assertSee('Cuidado com clareza');
    }

    public function test_payload_image_uses_duration_videos_do_not_rely_on_it_for_timing(): void
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
            'name' => 'Slide',
            'type' => MediaType::IMAGE->value,
            'active' => true,
            'duration_seconds' => 10,
        ], UploadedFile::fake()->image('s.jpg'));
        $video = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'Clip',
            'type' => MediaType::VIDEO->value,
            'active' => true,
            'play_with_audio' => false,
        ], UploadedFile::fake()->create('c.mp4', 100, 'video/mp4'));
        $youtube = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'YT',
            'type' => MediaType::YOUTUBE->value,
            'active' => true,
            'play_with_audio' => true,
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        app(AttachMediaToDisplayPanel::class)->handle($admin, $panel, $image, 7);
        app(AttachMediaToDisplayPanel::class)->handle($admin, $panel, $video);
        app(AttachMediaToDisplayPanel::class)->handle($admin, $panel, $youtube);

        $playlist = app(DisplayPanelPlaylist::class)->forPanel($panel->fresh(['clinic', 'unit']));

        $this->assertSame(7, $playlist[0]['duration_seconds']);
        $this->assertSame('image', $playlist[0]['type']);
        $this->assertSame(0, $playlist[1]['duration_seconds']);
        $this->assertSame('video', $playlist[1]['type']);
        $this->assertFalse($playlist[1]['play_with_audio']);
        $this->assertSame(0, $playlist[2]['duration_seconds']);
        $this->assertSame('youtube', $playlist[2]['type']);
        $this->assertTrue($playlist[2]['play_with_audio']);
    }

    public function test_playlist_refresh_keeps_items_when_signature_unchanged(): void
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
            'name' => 'Banner',
            'type' => MediaType::IMAGE->value,
            'active' => true,
            'duration_seconds' => 5,
        ], UploadedFile::fake()->image('b.jpg'));
        app(AttachMediaToDisplayPanel::class)->handle($admin, $panel, $image, 5);

        $component = Livewire::test(TvMediaPlayer::class, ['publicToken' => $panel->public_token]);
        $signature = $component->get('playlistSignature');
        $items = $component->get('items');

        $this->assertNotSame('', $signature);
        $this->assertCount(1, $items);
        $this->assertSame(5, $items[0]['duration_seconds']);

        $component->call('refreshPlaylist')
            ->assertSet('playlistSignature', $signature)
            ->assertSet('items', $items)
            ->assertNotDispatched('tv-playlist-updated');
    }

    public function test_playlist_order_is_stable_for_infinite_cycle_payload(): void
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
        $a = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'A',
            'type' => MediaType::IMAGE->value,
            'active' => true,
            'duration_seconds' => 5,
        ], UploadedFile::fake()->image('a.jpg'));
        $b = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'B',
            'type' => MediaType::IMAGE->value,
            'active' => true,
            'duration_seconds' => 10,
        ], UploadedFile::fake()->image('b.jpg'));
        $c = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'C',
            'type' => MediaType::YOUTUBE->value,
            'active' => true,
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        app(AttachMediaToDisplayPanel::class)->handle($admin, $panel, $a, 5);
        app(AttachMediaToDisplayPanel::class)->handle($admin, $panel, $b, 10);
        app(AttachMediaToDisplayPanel::class)->handle($admin, $panel, $c);

        $first = app(DisplayPanelPlaylist::class)->forPanel($panel->fresh(['clinic', 'unit']));
        $second = app(DisplayPanelPlaylist::class)->forPanel($panel->fresh(['clinic', 'unit']));

        $this->assertSame(
            array_column($first, 'media_item_id'),
            array_column($second, 'media_item_id')
        );
        $this->assertSame(['A', 'B', 'C'], array_column($first, 'name'));
        $this->assertSame(5, $first[0]['duration_seconds']);
        $this->assertSame(10, $first[1]['duration_seconds']);
        $this->assertSame(0, $first[2]['duration_seconds']);
    }
}
