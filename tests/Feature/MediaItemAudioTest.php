<?php

namespace Tests\Feature;

use App\Actions\AttachMediaToDisplayPanel;
use App\Actions\CreateMediaItem;
use App\Actions\SyncMediaItemDisplayPanels;
use App\Actions\UpdateMediaItem;
use App\Livewire\MediaItemsManager;
use App\MediaType;
use App\Models\Clinic;
use App\Models\DisplayPanel;
use App\Models\DisplayPanelMedia;
use App\Models\MediaItem;
use App\Models\Unit;
use App\Models\User;
use App\Services\DisplayPanelPlaylist;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MediaItemAudioTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_youtube_defaults_to_no_audio(): void
    {
        $admin = $this->administrator();

        Livewire::actingAs($admin)
            ->test(MediaItemsManager::class)
            ->call('startCreate')
            ->assertSet('playWithAudio', false)
            ->set('name', 'YT Sem áudio')
            ->set('type', MediaType::YOUTUBE->value)
            ->assertSet('playWithAudio', false)
            ->assertSee('Áudio do vídeo')
            ->set('youtubeUrl', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
            ->call('save')
            ->assertHasNoErrors();

        $item = MediaItem::query()->where('type', MediaType::YOUTUBE)->first();
        $this->assertNotNull($item);
        $this->assertFalse($item->play_with_audio);
        $this->assertFalse($item->playsWithAudio());
    }

    public function test_new_mp4_defaults_to_no_audio(): void
    {
        Storage::fake(MediaItem::DISK);
        $admin = $this->administrator();

        Livewire::actingAs($admin)
            ->test(MediaItemsManager::class)
            ->call('startCreate')
            ->set('name', 'MP4 Sem áudio')
            ->set('type', MediaType::VIDEO->value)
            ->assertSet('playWithAudio', false)
            ->assertSee('Áudio do vídeo')
            ->set('upload', UploadedFile::fake()->create('clip.mp4', 200, 'video/mp4'))
            ->call('save')
            ->assertHasNoErrors();

        $item = MediaItem::query()->where('type', MediaType::VIDEO)->first();
        $this->assertNotNull($item);
        $this->assertFalse($item->play_with_audio);
    }

    public function test_image_does_not_depend_on_audio_setting(): void
    {
        Storage::fake(MediaItem::DISK);
        $admin = $this->administrator();

        Livewire::actingAs($admin)
            ->test(MediaItemsManager::class)
            ->call('startCreate')
            ->set('name', 'Banner')
            ->set('type', MediaType::IMAGE->value)
            ->set('playWithAudio', true)
            ->set('durationSeconds', 10)
            ->set('upload', UploadedFile::fake()->image('a.jpg'))
            ->call('save')
            ->assertHasNoErrors()
            ->assertDontSee('Áudio do vídeo');

        $item = MediaItem::query()->where('type', MediaType::IMAGE)->first();
        $this->assertNotNull($item);
        $this->assertFalse($item->play_with_audio);
        $this->assertFalse($item->supportsAudioSetting());
        $this->assertFalse($item->playsWithAudio());
    }

    public function test_can_save_youtube_and_mp4_with_audio(): void
    {
        Storage::fake(MediaItem::DISK);
        $admin = $this->administrator();

        Livewire::actingAs($admin)
            ->test(MediaItemsManager::class)
            ->call('startCreate')
            ->set('name', 'YT Com áudio')
            ->set('type', MediaType::YOUTUBE->value)
            ->set('playWithAudio', true)
            ->set('youtubeUrl', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
            ->call('save')
            ->assertHasNoErrors();

        $youtube = MediaItem::query()->where('name', 'YT Com áudio')->first();
        $this->assertNotNull($youtube);
        $this->assertTrue($youtube->play_with_audio);

        Livewire::actingAs($admin)
            ->test(MediaItemsManager::class)
            ->call('startCreate')
            ->set('name', 'MP4 Com áudio')
            ->set('type', MediaType::VIDEO->value)
            ->set('playWithAudio', true)
            ->set('upload', UploadedFile::fake()->create('sound.mp4', 200, 'video/mp4'))
            ->call('save')
            ->assertHasNoErrors();

        $video = MediaItem::query()->where('name', 'MP4 Com áudio')->first();
        $this->assertNotNull($video);
        $this->assertTrue($video->play_with_audio);
    }

    public function test_edit_toggles_audio_without_destroying_panel_links(): void
    {
        Storage::fake(MediaItem::DISK);
        $admin = $this->administrator();
        $panel = $this->panel($admin);

        $this->actingAs($admin);
        $youtube = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'YT Toggle',
            'type' => MediaType::YOUTUBE->value,
            'active' => true,
            'play_with_audio' => true,
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);
        $video = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'MP4 Toggle',
            'type' => MediaType::VIDEO->value,
            'active' => true,
            'play_with_audio' => false,
        ], UploadedFile::fake()->create('a.mp4', 120, 'video/mp4'));

        app(SyncMediaItemDisplayPanels::class)->handle($admin, $youtube, [$panel->id]);
        app(SyncMediaItemDisplayPanels::class)->handle($admin, $video, [$panel->id]);

        $youtubePivotId = DisplayPanelMedia::query()
            ->where('media_item_id', $youtube->id)
            ->where('display_panel_id', $panel->id)
            ->value('id');
        $videoPivotId = DisplayPanelMedia::query()
            ->where('media_item_id', $video->id)
            ->where('display_panel_id', $panel->id)
            ->value('id');
        $this->assertNotNull($youtubePivotId);
        $this->assertNotNull($videoPivotId);

        Livewire::actingAs($admin)
            ->test(MediaItemsManager::class)
            ->call('edit', $youtube->id)
            ->assertSet('playWithAudio', true)
            ->set('playWithAudio', false)
            ->call('save')
            ->assertHasNoErrors();

        $youtube->refresh();
        $this->assertFalse($youtube->play_with_audio);
        $this->assertSame('dQw4w9WgXcQ', $youtube->external_id);
        $this->assertTrue(
            DisplayPanelMedia::query()->whereKey($youtubePivotId)->where('media_item_id', $youtube->id)->exists()
        );

        Livewire::actingAs($admin)
            ->test(MediaItemsManager::class)
            ->call('edit', $video->id)
            ->assertSet('playWithAudio', false)
            ->set('playWithAudio', true)
            ->call('save')
            ->assertHasNoErrors();

        $video->refresh();
        $this->assertTrue($video->play_with_audio);
        $this->assertSame(
            DisplayPanelMedia::query()->whereKey($videoPivotId)->value('media_item_id'),
            $video->id
        );
    }

    public function test_playlist_payload_includes_individual_play_with_audio(): void
    {
        Storage::fake(MediaItem::DISK);
        $admin = $this->administrator();
        $panel = $this->panel($admin);

        $this->actingAs($admin);
        $image = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'Imagem',
            'type' => MediaType::IMAGE->value,
            'active' => true,
            'duration_seconds' => 8,
            'play_with_audio' => true,
        ], UploadedFile::fake()->image('a.jpg'));
        $mutedVideo = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'MP4 muted',
            'type' => MediaType::VIDEO->value,
            'active' => true,
            'play_with_audio' => false,
        ], UploadedFile::fake()->create('m.mp4', 100, 'video/mp4'));
        $audioVideo = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'MP4 audio',
            'type' => MediaType::VIDEO->value,
            'active' => true,
            'play_with_audio' => true,
        ], UploadedFile::fake()->create('a.mp4', 100, 'video/mp4'));
        $mutedYt = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'YT muted',
            'type' => MediaType::YOUTUBE->value,
            'active' => true,
            'play_with_audio' => false,
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);
        $audioYt = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'YT audio',
            'type' => MediaType::YOUTUBE->value,
            'active' => true,
            'play_with_audio' => true,
            'youtube_url' => 'https://www.youtube.com/watch?v=oHg5SJYRHA0',
        ]);

        app(AttachMediaToDisplayPanel::class)->handle($admin, $panel, $image, 8);
        app(AttachMediaToDisplayPanel::class)->handle($admin, $panel, $mutedVideo);
        app(AttachMediaToDisplayPanel::class)->handle($admin, $panel, $audioVideo);
        app(AttachMediaToDisplayPanel::class)->handle($admin, $panel, $mutedYt);
        app(AttachMediaToDisplayPanel::class)->handle($admin, $panel, $audioYt);

        $playlist = app(DisplayPanelPlaylist::class)->forPanel($panel->fresh(['clinic', 'unit']));

        $this->assertCount(5, $playlist);
        $this->assertFalse($playlist[0]['play_with_audio']);
        $this->assertSame('image', $playlist[0]['type']);
        $this->assertFalse($playlist[1]['play_with_audio']);
        $this->assertTrue($playlist[2]['play_with_audio']);
        $this->assertFalse($playlist[3]['play_with_audio']);
        $this->assertTrue($playlist[4]['play_with_audio']);

        $serialized = json_encode($playlist);
        $this->assertStringContainsString('"play_with_audio":false', $serialized);
        $this->assertStringContainsString('"play_with_audio":true', $serialized);
        $this->assertStringNotContainsString('"clinic_id"', $serialized);
        $this->assertStringNotContainsString('created_by', $serialized);
    }

    public function test_update_preserves_audio_when_attribute_omitted_and_cross_tenant_blocked(): void
    {
        Storage::fake(MediaItem::DISK);
        $admin = $this->administrator();
        $foreign = $this->administrator();

        $this->actingAs($admin);
        $video = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'Clip',
            'type' => MediaType::VIDEO->value,
            'active' => true,
            'play_with_audio' => true,
        ], UploadedFile::fake()->create('a.mp4', 100, 'video/mp4'));

        app(UpdateMediaItem::class)->handle($admin, $video, [
            'name' => 'Clip 2',
            'active' => true,
        ]);

        $this->assertTrue($video->fresh()->play_with_audio);
        $this->assertSame('Clip 2', $video->fresh()->name);

        $this->expectException(HttpException::class);
        app(UpdateMediaItem::class)->handle($foreign, $video->fresh(), [
            'name' => 'Hacked',
            'active' => true,
            'play_with_audio' => false,
        ]);
    }

    public function test_listing_shows_audio_hint_for_videos_only(): void
    {
        Storage::fake(MediaItem::DISK);
        $admin = $this->administrator();

        $this->actingAs($admin);
        app(CreateMediaItem::class)->handle($admin, [
            'name' => 'Foto',
            'type' => MediaType::IMAGE->value,
            'active' => true,
            'duration_seconds' => 10,
        ], UploadedFile::fake()->image('a.jpg'));
        app(CreateMediaItem::class)->handle($admin, [
            'name' => 'Som',
            'type' => MediaType::YOUTUBE->value,
            'active' => true,
            'play_with_audio' => true,
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);
        app(CreateMediaItem::class)->handle($admin, [
            'name' => 'Mudo',
            'type' => MediaType::VIDEO->value,
            'active' => true,
            'play_with_audio' => false,
        ], UploadedFile::fake()->create('m.mp4', 80, 'video/mp4'));

        Livewire::actingAs($admin)
            ->test(MediaItemsManager::class)
            ->assertSee('Com áudio')
            ->assertSee('Sem áudio')
            ->assertSee('Foto')
            ->assertSee('Som')
            ->assertSee('Mudo');
    }

    private function administrator(?Clinic $clinic = null): User
    {
        $clinic ??= Clinic::factory()->create();

        return User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);
    }

    private function panel(User $admin): DisplayPanel
    {
        $unit = Unit::factory()->for($admin->clinic)->create();

        return DisplayPanel::factory()->create([
            'clinic_id' => $admin->clinic_id,
            'unit_id' => $unit->id,
            'active' => true,
        ]);
    }
}
