<?php

namespace Tests\Feature;

use App\Actions\CreateMediaItem;
use App\Actions\DeleteMediaItem;
use App\Actions\UpdateMediaItem;
use App\Livewire\MediaItemsManager;
use App\MediaType;
use App\Models\Clinic;
use App\Models\DisplayPanel;
use App\Models\DisplayPanelMedia;
use App\Models\MediaItem;
use App\Models\Unit;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MediaItemManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_nova_midia_opens_modal_and_cancel_clears_state(): void
    {
        $admin = $this->administrator();

        Livewire::actingAs($admin)
            ->test(MediaItemsManager::class)
            ->assertSet('showForm', false)
            ->call('startCreate')
            ->assertSet('showForm', true)
            ->assertSee('Cadastrar mídia')
            ->set('name', 'Rascunho')
            ->set('youtubeUrl', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
            ->call('cancel')
            ->assertSet('showForm', false)
            ->assertSet('name', '')
            ->assertSet('youtubeUrl', '')
            ->call('startCreate')
            ->assertSet('name', '')
            ->assertSet('type', MediaType::IMAGE->value);
    }

    public function test_administrator_creates_image_and_video_with_server_generated_path(): void
    {
        Storage::fake(MediaItem::DISK);
        $admin = $this->administrator();

        $this->actingAs($admin)
            ->get(route('media-items.index'))
            ->assertOk()
            ->assertSee('Mídia da TV');

        Livewire::actingAs($admin)
            ->test(MediaItemsManager::class)
            ->call('startCreate')
            ->assertSee('Nova mídia')
            ->set('name', 'Banner Institucional')
            ->set('type', MediaType::IMAGE->value)
            ->set('durationSeconds', 12)
            ->set('active', true)
            ->set('upload', UploadedFile::fake()->image('foto.jpg', 800, 600))
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->assertSee('Mídia cadastrada');

        $image = MediaItem::query()->where('type', MediaType::IMAGE)->first();
        $this->assertNotNull($image);
        $this->assertSame($admin->clinic_id, $image->clinic_id);
        $this->assertSame('image/jpeg', $image->mime_type);
        $this->assertStringStartsWith('tv-media/'.$admin->clinic_id.'/', $image->file_path);
        $this->assertStringNotContainsString('foto.jpg', $image->file_path);
        Storage::disk(MediaItem::DISK)->assertExists($image->file_path);

        Livewire::actingAs($admin)
            ->test(MediaItemsManager::class)
            ->call('startCreate')
            ->set('name', 'Vídeo Campanha')
            ->set('type', MediaType::VIDEO->value)
            ->set('upload', UploadedFile::fake()->create('clip.mp4', 512, 'video/mp4'))
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false);

        $video = MediaItem::query()->where('type', MediaType::VIDEO)->first();
        $this->assertNotNull($video);
        $this->assertSame('video/mp4', $video->mime_type);
        $this->assertNull($video->duration_seconds);
        $this->assertFalse($video->play_with_audio);
        Storage::disk(MediaItem::DISK)->assertExists($video->file_path);
    }

    public function test_image_requires_file_on_create_and_can_edit_without_reupload(): void
    {
        Storage::fake(MediaItem::DISK);
        $admin = $this->administrator();

        Livewire::actingAs($admin)
            ->test(MediaItemsManager::class)
            ->call('startCreate')
            ->set('name', 'Sem arquivo')
            ->set('type', MediaType::IMAGE->value)
            ->call('save')
            ->assertHasErrors(['upload']);

        $this->actingAs($admin);
        $image = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'Original',
            'type' => MediaType::IMAGE->value,
            'active' => true,
            'duration_seconds' => 10,
        ], UploadedFile::fake()->image('a.jpg'));

        $path = $image->file_path;

        Livewire::actingAs($admin)
            ->test(MediaItemsManager::class)
            ->call('edit', $image->id)
            ->assertSet('showForm', true)
            ->assertSee('Editar mídia')
            ->assertSet('name', 'Original')
            ->set('name', 'Renomeado')
            ->set('durationSeconds', 20)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false);

        $image->refresh();
        $this->assertSame('Renomeado', $image->name);
        $this->assertSame(20, $image->duration_seconds);
        $this->assertSame($path, $image->file_path);
    }

    public function test_changing_type_clears_upload_and_youtube_fields(): void
    {
        Storage::fake(MediaItem::DISK);
        $admin = $this->administrator();

        Livewire::actingAs($admin)
            ->test(MediaItemsManager::class)
            ->call('startCreate')
            ->set('type', MediaType::IMAGE->value)
            ->set('upload', UploadedFile::fake()->image('a.jpg'))
            ->set('type', MediaType::YOUTUBE->value)
            ->assertSet('upload', null)
            ->set('youtubeUrl', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
            ->set('type', MediaType::VIDEO->value)
            ->assertSet('youtubeUrl', '');
    }

    public function test_youtube_media_can_be_created_edited_and_listed(): void
    {
        $admin = $this->administrator();

        Livewire::actingAs($admin)
            ->test(MediaItemsManager::class)
            ->call('startCreate')
            ->set('name', 'Institucional YT')
            ->set('type', MediaType::YOUTUBE->value)
            ->set('youtubeUrl', 'https://youtu.be/dQw4w9WgXcQ')
            ->set('active', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->assertSee('Institucional YT')
            ->assertSee('YouTube');

        $item = MediaItem::query()->where('type', MediaType::YOUTUBE)->first();
        $this->assertNotNull($item);
        $this->assertSame('dQw4w9WgXcQ', $item->external_id);
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $item->external_url);
        $this->assertNull($item->file_path);
        $this->assertNull($item->duration_seconds);
        $this->assertFalse($item->play_with_audio);

        Livewire::actingAs($admin)
            ->test(MediaItemsManager::class)
            ->call('edit', $item->id)
            ->assertSet('youtubeUrl', $item->external_url)
            ->set('name', 'Institucional YT 2')
            ->set('youtubeUrl', 'https://www.youtube.com/watch?v=oHg5SJYRHA0')
            ->call('save')
            ->assertHasNoErrors();

        $item->refresh();
        $this->assertSame('Institucional YT 2', $item->name);
        $this->assertSame('oHg5SJYRHA0', $item->external_id);
    }

    #[DataProvider('invalidYoutubeInputs')]
    public function test_youtube_rejects_invalid_urls(string $url): void
    {
        $admin = $this->administrator();

        Livewire::actingAs($admin)
            ->test(MediaItemsManager::class)
            ->call('startCreate')
            ->set('name', 'Ruim')
            ->set('type', MediaType::YOUTUBE->value)
            ->set('youtubeUrl', $url)
            ->call('save')
            ->assertHasErrors(['youtubeUrl']);

        $this->assertSame(0, MediaItem::query()->count());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidYoutubeInputs(): array
    {
        return [
            'iframe' => ['<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>'],
            'fake_host' => ['https://youtube.com.evil.test/watch?v=dQw4w9WgXcQ'],
            'vimeo' => ['https://vimeo.com/123456789'],
            'empty_id' => ['https://www.youtube.com/watch?v='],
        ];
    }

    public function test_invalid_mime_svg_and_executable_are_rejected(): void
    {
        Storage::fake(MediaItem::DISK);
        $admin = $this->administrator();

        Livewire::actingAs($admin)
            ->test(MediaItemsManager::class)
            ->call('startCreate')
            ->set('name', 'SVG Ruim')
            ->set('type', MediaType::IMAGE->value)
            ->set('upload', UploadedFile::fake()->create('evil.svg', 20, 'image/svg+xml'))
            ->call('save')
            ->assertHasErrors(['upload']);

        Livewire::actingAs($admin)
            ->test(MediaItemsManager::class)
            ->call('startCreate')
            ->set('name', 'PHP')
            ->set('type', MediaType::IMAGE->value)
            ->set('upload', UploadedFile::fake()->create('shell.php', 20, 'application/x-php'))
            ->call('save')
            ->assertHasErrors(['upload']);

        $this->assertSame(0, MediaItem::query()->count());
    }

    public function test_oversized_image_is_rejected(): void
    {
        Storage::fake(MediaItem::DISK);
        $admin = $this->administrator();

        Livewire::actingAs($admin)
            ->test(MediaItemsManager::class)
            ->call('startCreate')
            ->set('name', 'Grande')
            ->set('type', MediaType::IMAGE->value)
            ->set('upload', UploadedFile::fake()->create('big.jpg', MediaItem::IMAGE_MAX_KILOBYTES + 10, 'image/jpeg'))
            ->call('save')
            ->assertHasErrors(['upload']);
    }

    #[DataProvider('nonAdministratorRoles')]
    public function test_non_administrators_cannot_manage_media(UserRole $role): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => $role,
        ]);

        $this->actingAs($user)->get(route('media-items.index'))->assertForbidden();
        $this->assertFalse($user->can('create', MediaItem::class));
    }

    public function test_cross_tenant_cannot_edit_or_delete_foreign_media(): void
    {
        Storage::fake(MediaItem::DISK);
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $adminA = $this->administrator($clinicA);
        $foreign = MediaItem::factory()->youtube()->create([
            'clinic_id' => $clinicB->id,
            'name' => 'Mídia B',
        ]);

        $this->actingAs($adminA)
            ->get(route('media-items.index'))
            ->assertDontSee('Mídia B');

        Livewire::actingAs($adminA)
            ->test(MediaItemsManager::class)
            ->call('edit', $foreign->id)
            ->assertNotFound();

        Livewire::actingAs($adminA)
            ->test(MediaItemsManager::class)
            ->call('confirmDeletion', $foreign->id)
            ->assertNotFound();

        $this->assertFalse($adminA->can('update', $foreign));
        $this->assertFalse($adminA->can('delete', $foreign));
    }

    public function test_unused_media_can_be_deleted_and_used_media_is_blocked(): void
    {
        Storage::fake(MediaItem::DISK);
        $admin = $this->administrator();
        $unit = Unit::factory()->for($admin->clinic)->create();
        $panel = DisplayPanel::factory()->create([
            'clinic_id' => $admin->clinic_id,
            'unit_id' => $unit->id,
        ]);

        $this->actingAs($admin);
        $unused = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'Livre',
            'type' => MediaType::IMAGE->value,
            'active' => true,
            'duration_seconds' => 10,
        ], UploadedFile::fake()->image('a.jpg'));

        $used = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'Em uso',
            'type' => MediaType::YOUTUBE->value,
            'active' => true,
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $entry = new DisplayPanelMedia;
        $entry->forceFill([
            'clinic_id' => $admin->clinic_id,
            'display_panel_id' => $panel->id,
            'media_item_id' => $used->id,
            'position' => 1,
            'duration_seconds' => null,
            'active' => true,
        ])->save();

        $path = $unused->file_path;
        app(DeleteMediaItem::class)->handle($admin, $unused);
        $this->assertDatabaseMissing('media_items', ['id' => $unused->id]);
        Storage::disk(MediaItem::DISK)->assertMissing($path);

        $this->expectException(ValidationException::class);
        app(DeleteMediaItem::class)->handle($admin, $used);
    }

    public function test_video_edit_without_reupload_and_replace_file(): void
    {
        Storage::fake(MediaItem::DISK);
        $admin = $this->administrator();
        $this->actingAs($admin);

        $video = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'Clip',
            'type' => MediaType::VIDEO->value,
            'active' => true,
        ], UploadedFile::fake()->create('a.mp4', 100, 'video/mp4'));

        $oldPath = $video->file_path;

        app(UpdateMediaItem::class)->handle($admin, $video, [
            'name' => 'Clip 2',
            'active' => true,
        ]);
        $this->assertSame($oldPath, $video->fresh()->file_path);
        $this->assertSame('Clip 2', $video->fresh()->name);

        app(UpdateMediaItem::class)->handle($admin, $video->fresh(), [
            'name' => 'Clip 3',
            'active' => true,
        ], UploadedFile::fake()->create('b.mp4', 120, 'video/mp4'));

        $video->refresh();
        $this->assertNotSame($oldPath, $video->file_path);
        Storage::disk(MediaItem::DISK)->assertMissing($oldPath);
        Storage::disk(MediaItem::DISK)->assertExists($video->file_path);
    }

    /**
     * @return array<string, array{0: UserRole}>
     */
    public static function nonAdministratorRoles(): array
    {
        return [
            'supervisor' => [UserRole::SUPERVISOR],
            'attendant' => [UserRole::ATTENDANT],
        ];
    }

    private function administrator(?Clinic $clinic = null): User
    {
        $clinic ??= Clinic::factory()->create();

        return User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);
    }
}
