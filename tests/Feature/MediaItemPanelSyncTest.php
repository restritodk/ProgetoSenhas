<?php

namespace Tests\Feature;

use App\Actions\AttachMediaToDisplayPanel;
use App\Actions\CreateMediaItem;
use App\Actions\SyncMediaItemDisplayPanels;
use App\Actions\UpdateMediaItem;
use App\Livewire\MediaItemsManager;
use App\Livewire\TvMediaPlayer;
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
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class MediaItemPanelSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_media_without_panels_stays_library_only(): void
    {
        Storage::fake(MediaItem::DISK);
        $admin = $this->administrator();
        $this->panel($admin);

        Livewire::actingAs($admin)
            ->test(MediaItemsManager::class)
            ->call('startCreate')
            ->set('name', 'Só biblioteca')
            ->set('type', MediaType::IMAGE->value)
            ->set('durationSeconds', 10)
            ->set('selectedPanelIds', [])
            ->set('upload', UploadedFile::fake()->image('a.jpg'))
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Não será exibida até ser adicionada');

        $media = MediaItem::query()->first();
        $this->assertNotNull($media);
        $this->assertSame(0, DisplayPanelMedia::query()->where('media_item_id', $media->id)->count());
    }

    public function test_create_image_linked_to_panel_uses_initial_duration_and_appends_position(): void
    {
        Storage::fake(MediaItem::DISK);
        $admin = $this->administrator();
        $panel = $this->panel($admin);

        $this->actingAs($admin);
        $existing = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'Já na playlist',
            'type' => MediaType::IMAGE->value,
            'active' => true,
            'duration_seconds' => 8,
        ], UploadedFile::fake()->image('old.jpg'));
        app(AttachMediaToDisplayPanel::class)->handle($admin, $panel, $existing, 8);

        Livewire::actingAs($admin)
            ->test(MediaItemsManager::class)
            ->call('startCreate')
            ->set('name', 'Nova imagem')
            ->set('type', MediaType::IMAGE->value)
            ->set('durationSeconds', 12)
            ->set('selectedPanelIds', [$panel->id])
            ->set('upload', UploadedFile::fake()->image('new.jpg'))
            ->call('save')
            ->assertHasNoErrors();

        $media = MediaItem::query()->where('name', 'Nova imagem')->first();
        $this->assertNotNull($media);

        $entry = DisplayPanelMedia::query()
            ->where('display_panel_id', $panel->id)
            ->where('media_item_id', $media->id)
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame(2, $entry->position);
        $this->assertSame(12, $entry->duration_seconds);
    }

    public function test_create_image_linked_to_multiple_panels(): void
    {
        Storage::fake(MediaItem::DISK);
        $admin = $this->administrator();
        $panelA = $this->panel($admin, 'TV Recepção');
        $panelB = $this->panel($admin, 'TV Consultório');

        Livewire::actingAs($admin)
            ->test(MediaItemsManager::class)
            ->call('startCreate')
            ->set('name', 'Multi')
            ->set('type', MediaType::IMAGE->value)
            ->set('durationSeconds', 15)
            ->set('selectedPanelIds', [$panelA->id, $panelB->id])
            ->set('upload', UploadedFile::fake()->image('m.jpg'))
            ->call('save')
            ->assertHasNoErrors();

        $media = MediaItem::query()->where('name', 'Multi')->first();
        $this->assertNotNull($media);
        $this->assertSame(2, DisplayPanelMedia::query()->where('media_item_id', $media->id)->count());
        $this->assertSame(
            [15, 15],
            DisplayPanelMedia::query()->where('media_item_id', $media->id)->pluck('duration_seconds')->all()
        );
    }

    public function test_cross_tenant_panel_is_rejected(): void
    {
        Storage::fake(MediaItem::DISK);
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $admin = $this->administrator($clinicA);
        $foreignPanel = DisplayPanel::factory()->create([
            'clinic_id' => $clinicB->id,
            'unit_id' => Unit::factory()->for($clinicB)->create()->id,
        ]);

        $this->actingAs($admin);
        $media = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'Local',
            'type' => MediaType::IMAGE->value,
            'active' => true,
            'duration_seconds' => 10,
        ], UploadedFile::fake()->image('a.jpg'));

        $this->expectException(ValidationException::class);
        app(SyncMediaItemDisplayPanels::class)->handle($admin, $media, [$foreignPanel->id], 10);
    }

    public function test_edit_adds_and_removes_panels_without_duplicating_or_overwriting_duration(): void
    {
        Storage::fake(MediaItem::DISK);
        $admin = $this->administrator();
        $panelA = $this->panel($admin, 'TV Recepção');
        $panelB = $this->panel($admin, 'TV Consultório');

        $this->actingAs($admin);
        $media = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'Banner',
            'type' => MediaType::IMAGE->value,
            'active' => true,
            'duration_seconds' => 10,
        ], UploadedFile::fake()->image('a.jpg'));

        $entryA = app(AttachMediaToDisplayPanel::class)->handle($admin, $panelA, $media, 10);
        $entryA->forceFill(['duration_seconds' => 30])->save();

        Livewire::actingAs($admin)
            ->test(MediaItemsManager::class)
            ->call('edit', $media->id)
            ->assertSet('selectedPanelIds', [$panelA->id])
            ->set('name', 'Banner Renomeado')
            ->set('durationSeconds', 10)
            ->set('selectedPanelIds', [$panelA->id, $panelB->id])
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false);

        $this->assertSame('Banner Renomeado', $media->fresh()->name);
        $this->assertSame(30, $entryA->fresh()->duration_seconds);

        $entryB = DisplayPanelMedia::query()
            ->where('display_panel_id', $panelB->id)
            ->where('media_item_id', $media->id)
            ->first();
        $this->assertNotNull($entryB);
        $this->assertSame(10, $entryB->duration_seconds);

        $this->assertSame(
            1,
            DisplayPanelMedia::query()->where('display_panel_id', $panelA->id)->where('media_item_id', $media->id)->count()
        );

        Livewire::actingAs($admin)
            ->test(MediaItemsManager::class)
            ->call('edit', $media->id)
            ->set('selectedPanelIds', [$panelB->id])
            ->call('save')
            ->assertSet('confirmingPanelDetach', true)
            ->call('confirmPanelDetachAndSave')
            ->assertHasNoErrors()
            ->assertSet('showForm', false);

        $this->assertDatabaseMissing('display_panel_media', [
            'display_panel_id' => $panelA->id,
            'media_item_id' => $media->id,
        ]);
        $this->assertDatabaseHas('display_panel_media', [
            'display_panel_id' => $panelB->id,
            'media_item_id' => $media->id,
            'duration_seconds' => 10,
        ]);
    }

    public function test_edit_name_preserves_per_panel_durations(): void
    {
        Storage::fake(MediaItem::DISK);
        $admin = $this->administrator();
        $panelA = $this->panel($admin, 'TV A');
        $panelB = $this->panel($admin, 'TV B');
        $this->actingAs($admin);

        $media = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'Img',
            'type' => MediaType::IMAGE->value,
            'active' => true,
            'duration_seconds' => 10,
        ], UploadedFile::fake()->image('a.jpg'));

        $a = app(AttachMediaToDisplayPanel::class)->handle($admin, $panelA, $media, 10);
        $b = app(AttachMediaToDisplayPanel::class)->handle($admin, $panelB, $media, 10);
        $a->forceFill(['duration_seconds' => 10])->save();
        $b->forceFill(['duration_seconds' => 30])->save();

        app(UpdateMediaItem::class)->handle($admin, $media, [
            'name' => 'Img Novo',
            'active' => true,
            'duration_seconds' => 10,
        ]);
        app(SyncMediaItemDisplayPanels::class)->handle($admin, $media->fresh(), [$panelA->id, $panelB->id], 10);

        $this->assertSame(10, $a->fresh()->duration_seconds);
        $this->assertSame(30, $b->fresh()->duration_seconds);
    }

    public function test_tv_payload_includes_active_playlist_items_with_correct_duration_and_youtube(): void
    {
        Storage::fake(MediaItem::DISK);
        $admin = $this->administrator();
        $panel = $this->panel($admin);
        $this->actingAs($admin);

        $image = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'Banner',
            'type' => MediaType::IMAGE->value,
            'active' => true,
            'duration_seconds' => 10,
        ], UploadedFile::fake()->image('a.jpg'));
        $youtube = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'Casa',
            'type' => MediaType::YOUTUBE->value,
            'active' => true,
            'youtube_url' => 'https://www.youtube.com/watch?v=jTowyYFXIUY',
        ]);
        $libraryOnly = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'Fora',
            'type' => MediaType::IMAGE->value,
            'active' => true,
            'duration_seconds' => 10,
        ], UploadedFile::fake()->image('b.jpg'));
        $inactiveMedia = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'Inativa',
            'type' => MediaType::IMAGE->value,
            'active' => true,
            'duration_seconds' => 10,
        ], UploadedFile::fake()->image('c.jpg'));

        $entryImage = app(AttachMediaToDisplayPanel::class)->handle($admin, $panel, $image, 10);
        $entryImage->forceFill(['duration_seconds' => 5])->save();
        app(AttachMediaToDisplayPanel::class)->handle($admin, $panel, $youtube);
        $inactiveEntry = app(AttachMediaToDisplayPanel::class)->handle($admin, $panel, $inactiveMedia, 10);
        $inactiveEntry->forceFill(['active' => false])->save();
        $inactiveMedia->forceFill(['active' => false])->save();

        $playlist = app(DisplayPanelPlaylist::class)->forPanel($panel->fresh(['clinic', 'unit']));

        $this->assertCount(2, $playlist);
        $this->assertSame('Banner', $playlist[0]['name']);
        $this->assertSame(5, $playlist[0]['duration_seconds']);
        $this->assertSame('Casa', $playlist[1]['name']);
        $this->assertSame('youtube', $playlist[1]['type']);
        $this->assertSame('jTowyYFXIUY', $playlist[1]['video_id']);
        $this->assertSame(0, $playlist[1]['duration_seconds']);

        $ids = collect($playlist)->pluck('media_item_id')->all();
        $this->assertNotContains($libraryOnly->id, $ids);
        $this->assertNotContains($inactiveMedia->id, $ids);

        Livewire::test(TvMediaPlayer::class, ['publicToken' => $panel->public_token])
            ->assertSet('items', function (array $items): bool {
                return count($items) === 2
                    && $items[0]['duration_seconds'] === 5
                    && $items[1]['type'] === 'youtube';
            });
    }

    public function test_edit_youtube_casa_persists_panel_checkbox_via_save_click(): void
    {
        Storage::fake(MediaItem::DISK);
        $admin = $this->administrator();
        $panel = $this->panel($admin, 'TV Recepção');
        $this->actingAs($admin);

        $media = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'Casa',
            'type' => MediaType::YOUTUBE->value,
            'active' => true,
            'youtube_url' => 'https://www.youtube.com/watch?v=jTowyYFXIUY',
        ]);

        $this->assertSame(0, DisplayPanelMedia::query()->where('media_item_id', $media->id)->count());

        Livewire::actingAs($admin)
            ->test(MediaItemsManager::class)
            ->call('edit', $media->id)
            ->assertSee('Exibir nos painéis')
            ->assertSee('TV Recepção')
            ->set('selectedPanelIds', [(string) $panel->id])
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->assertSee('TV Recepção');

        $entry = DisplayPanelMedia::query()
            ->where('media_item_id', $media->id)
            ->where('display_panel_id', $panel->id)
            ->first();

        $this->assertNotNull($entry);
        $this->assertTrue($entry->active);
        $this->assertSame(1, $entry->position);
    }

    public function test_listing_shows_exhibition_hint(): void
    {
        Storage::fake(MediaItem::DISK);
        $admin = $this->administrator();
        $panel = $this->panel($admin, 'TV Recepção');
        $this->actingAs($admin);

        $linked = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'Com TV',
            'type' => MediaType::YOUTUBE->value,
            'active' => true,
            'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ',
        ]);
        app(AttachMediaToDisplayPanel::class)->handle($admin, $panel, $linked);

        app(CreateMediaItem::class)->handle($admin, [
            'name' => 'Sem TV',
            'type' => MediaType::IMAGE->value,
            'active' => true,
            'duration_seconds' => 10,
        ], UploadedFile::fake()->image('x.jpg'));

        Livewire::actingAs($admin)
            ->test(MediaItemsManager::class)
            ->assertSee('TV Recepção')
            ->assertSee('Nenhum painel');
    }

    private function administrator(?Clinic $clinic = null): User
    {
        $clinic ??= Clinic::factory()->create();

        return User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);
    }

    private function panel(User $admin, string $name = 'TV Recepção'): DisplayPanel
    {
        $unit = Unit::factory()->for($admin->clinic)->create();

        return DisplayPanel::factory()->create([
            'clinic_id' => $admin->clinic_id,
            'unit_id' => $unit->id,
            'name' => $name,
            'active' => true,
        ]);
    }
}
