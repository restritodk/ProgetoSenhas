<?php

namespace Tests\Feature;

use App\Actions\AttachMediaToDisplayPanel;
use App\Actions\CreateMediaItem;
use App\Actions\ReorderDisplayPanelMedia;
use App\Actions\UpdateDisplayPanelMediaItem;
use App\Livewire\DisplayPanelPlaylistManager;
use App\MediaType;
use App\Models\Clinic;
use App\Models\DisplayPanel;
use App\Models\MediaItem;
use App\Models\Unit;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class DisplayPanelPlaylistTest extends TestCase
{
    use RefreshDatabase;

    public function test_associates_media_only_within_same_clinic_and_supports_reorder_duration_and_toggle(): void
    {
        Storage::fake(MediaItem::DISK);
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $admin = $this->administrator($clinicA);
        $unitA = Unit::factory()->for($clinicA)->create();
        $unitB = Unit::factory()->for($clinicB)->create();
        $panel = DisplayPanel::factory()->create([
            'clinic_id' => $clinicA->id,
            'unit_id' => $unitA->id,
            'name' => 'TV Recepção',
        ]);
        $foreignPanel = DisplayPanel::factory()->create([
            'clinic_id' => $clinicB->id,
            'unit_id' => $unitB->id,
        ]);

        $this->actingAs($admin);
        $first = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'Img 1',
            'type' => MediaType::IMAGE->value,
            'active' => true,
            'duration_seconds' => 8,
        ], UploadedFile::fake()->image('1.jpg'));
        $second = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'Img 2',
            'type' => MediaType::IMAGE->value,
            'active' => true,
            'duration_seconds' => 10,
        ], UploadedFile::fake()->image('2.jpg'));
        $foreignMedia = MediaItem::factory()->create(['clinic_id' => $clinicB->id]);

        $entry1 = app(AttachMediaToDisplayPanel::class)->handle($admin, $panel, $first);
        $entry2 = app(AttachMediaToDisplayPanel::class)->handle($admin, $panel, $second);

        $this->assertSame(1, $entry1->position);
        $this->assertSame(2, $entry2->position);

        app(ReorderDisplayPanelMedia::class)->handle($admin, $panel, $entry2, 'up');
        $this->assertSame(1, $entry2->fresh()->position);
        $this->assertSame(2, $entry1->fresh()->position);

        app(UpdateDisplayPanelMediaItem::class)->handle($admin, $panel, $entry1->fresh(), [
            'duration_seconds' => 20,
            'active' => false,
        ]);
        $this->assertSame(20, $entry1->fresh()->duration_seconds);
        $this->assertFalse($entry1->fresh()->active);

        $this->actingAs($admin)
            ->get(route('display-panels.playlist', $panel))
            ->assertOk()
            ->assertSee('Playlist do painel');

        Livewire::actingAs($admin)
            ->test(DisplayPanelPlaylistManager::class, ['panelId' => $panel->id])
            ->assertSee('Img 1')
            ->assertSee('Img 2');

        $this->expectException(ValidationException::class);
        app(AttachMediaToDisplayPanel::class)->handle($admin, $panel, $foreignMedia);
    }

    public function test_cross_tenant_cannot_manage_foreign_playlist(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $admin = $this->administrator($clinicA);
        $unitB = Unit::factory()->for($clinicB)->create();
        $panelB = DisplayPanel::factory()->create([
            'clinic_id' => $clinicB->id,
            'unit_id' => $unitB->id,
        ]);

        $this->actingAs($admin)
            ->get(route('display-panels.playlist', $panelB))
            ->assertNotFound();

        Livewire::actingAs($admin)
            ->test(DisplayPanelPlaylistManager::class, ['panelId' => $panelB->id])
            ->assertNotFound();
    }

    public function test_inactive_media_is_rejected_on_attach(): void
    {
        Storage::fake(MediaItem::DISK);
        $admin = $this->administrator();
        $unit = Unit::factory()->for($admin->clinic)->create();
        $panel = DisplayPanel::factory()->create([
            'clinic_id' => $admin->clinic_id,
            'unit_id' => $unit->id,
        ]);

        $this->actingAs($admin);
        $media = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'Off',
            'type' => MediaType::IMAGE->value,
            'active' => false,
            'duration_seconds' => 10,
        ], UploadedFile::fake()->image('off.jpg'));

        $this->expectException(ValidationException::class);
        app(AttachMediaToDisplayPanel::class)->handle($admin, $panel, $media);
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
