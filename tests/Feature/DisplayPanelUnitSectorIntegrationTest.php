<?php

namespace Tests\Feature;

use App\Actions\CreateDisplayPanel;
use App\Actions\CreateSector;
use App\Actions\SyncMediaItemDisplayPanels;
use App\Livewire\DisplayPanelsManager;
use App\Livewire\MediaItemsManager;
use App\MediaType;
use App\Models\Clinic;
use App\Models\Desk;
use App\Models\DisplayPanel;
use App\Models\DisplayPanelMedia;
use App\Models\MediaItem;
use App\Models\Sector;
use App\Models\Ticket;
use App\Models\TicketCall;
use App\Models\TicketType;
use App\Models\Unit;
use App\Models\User;
use App\Services\DisplayPanelFeed;
use App\TicketCallType;
use App\TicketStatus;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class DisplayPanelUnitSectorIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_panel_persists_exactly_one_sector_for_unit(): void
    {
        [$admin, $unit, $recepcion, $exames] = $this->toledoWithRecepcionAndExames();

        Livewire::actingAs($admin)
            ->test(DisplayPanelsManager::class)
            ->call('startCreate')
            ->set('name', 'TV Recepção')
            ->set('code', 'TV-REC')
            ->set('unitId', $unit->id)
            ->set('sectorId', $recepcion->id)
            ->set('active', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Painel/TV criado com sucesso.');

        $panelRec = DisplayPanel::query()->where('code', 'TV-REC')->first();
        $this->assertNotNull($panelRec);
        $this->assertSame($unit->id, $panelRec->unit_id);
        $this->assertSame([$recepcion->id], $panelRec->sectorIds());

        Livewire::actingAs($admin)
            ->test(DisplayPanelsManager::class)
            ->call('startCreate')
            ->set('name', 'TV Exames')
            ->set('code', 'TV-EXA')
            ->set('unitId', $unit->id)
            ->set('sectorId', $exames->id)
            ->call('save')
            ->assertHasNoErrors();

        $panelExa = DisplayPanel::query()->where('code', 'TV-EXA')->first();
        $this->assertNotNull($panelExa);
        $this->assertSame([$exames->id], $panelExa->sectorIds());
    }

    public function test_changing_unit_clears_incompatible_sector_selection(): void
    {
        [$admin, $unitToledo, $recepcion] = $this->toledoWithRecepcionAndExames();
        $unitCentro = Unit::factory()->for($admin->clinic)->create(['name' => 'Unidade Centro']);
        $examesCentro = app(CreateSector::class)->handle($admin, [
            'name' => 'Exames',
            'code' => 'EXA-C',
            'unit_id' => $unitCentro->id,
            'active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(DisplayPanelsManager::class)
            ->call('startCreate')
            ->set('unitId', $unitToledo->id)
            ->set('sectorId', $recepcion->id)
            ->assertSet('sectorId', $recepcion->id)
            ->set('unitId', $unitCentro->id)
            ->assertNotSet('sectorId', $recepcion->id)
            ->set('name', 'TV Centro')
            ->set('code', 'TV-CEN')
            ->set('sectorId', $examesCentro->id)
            ->call('save')
            ->assertHasNoErrors();

        $panel = DisplayPanel::query()->where('code', 'TV-CEN')->firstOrFail();
        $this->assertSame($unitCentro->id, $panel->unit_id);
        $this->assertSame([$examesCentro->id], $panel->sectorIds());
    }

    public function test_create_rejects_sector_from_another_unit_server_side(): void
    {
        [$admin, $unitToledo] = $this->toledoWithRecepcionAndExames();
        $unitCentro = Unit::factory()->for($admin->clinic)->create(['name' => 'Unidade Centro']);
        $examesCentro = app(CreateSector::class)->handle($admin, [
            'name' => 'Exames',
            'code' => 'EXA-C',
            'unit_id' => $unitCentro->id,
            'active' => true,
        ]);

        $this->actingAs($admin);
        $this->expectException(ValidationException::class);
        app(CreateDisplayPanel::class)->handle($admin, [
            'name' => 'TV Inválida',
            'code' => 'TV-BAD',
            'unit_id' => $unitToledo->id,
            'sector_ids' => [$examesCentro->id],
            'active' => true,
        ]);
    }

    public function test_create_rejects_multiple_sectors_in_this_phase(): void
    {
        [$admin, $unit, $recepcion, $exames] = $this->toledoWithRecepcionAndExames();

        $this->actingAs($admin);
        $this->expectException(ValidationException::class);
        app(CreateDisplayPanel::class)->handle($admin, [
            'name' => 'TV Multi',
            'code' => 'TV-MUL',
            'unit_id' => $unit->id,
            'sector_ids' => [$recepcion->id, $exames->id],
            'active' => true,
        ]);
    }

    public function test_feed_filters_calls_by_panel_sector_and_unit(): void
    {
        [$admin, $unit, $recepcion, $exames] = $this->toledoWithRecepcionAndExames();
        $unitCentro = Unit::factory()->for($admin->clinic)->create(['name' => 'Unidade Centro']);
        $recepcionCentro = app(CreateSector::class)->handle($admin, [
            'name' => 'Recepção',
            'code' => 'REC-C',
            'unit_id' => $unitCentro->id,
            'active' => true,
        ]);

        $type = TicketType::query()->where('clinic_id', $admin->clinic_id)->firstOrFail();
        $desk = Desk::factory()->create([
            'clinic_id' => $admin->clinic_id,
            'unit_id' => $unit->id,
            'sector_id' => $recepcion->id,
            'active' => true,
        ]);

        $tvRec = app(CreateDisplayPanel::class)->handle($admin, [
            'name' => 'TV Recepção',
            'code' => 'TV-REC',
            'unit_id' => $unit->id,
            'sector_ids' => [$recepcion->id],
            'active' => true,
        ]);

        $callRec = $this->makeCall($admin, $unit, $recepcion, $type, $desk, 1);
        $callExa = $this->makeCall($admin, $unit, $exames, $type, $desk, 2);
        $callCentro = $this->makeCall($admin, $unitCentro, $recepcionCentro, $type, $desk, 3);

        $feed = app(DisplayPanelFeed::class)->build($tvRec->fresh(['clinic', 'unit', 'sectors']));
        $ids = collect($feed['recent_calls'])->pluck('id')->all();

        $this->assertContains($callRec->id, $ids);
        $this->assertNotContains($callExa->id, $ids);
        $this->assertNotContains($callCentro->id, $ids);
    }

    public function test_two_panels_on_same_sector_both_receive_call_without_consuming(): void
    {
        [$admin, $unit, $recepcion] = $this->toledoWithRecepcionAndExames();
        $type = TicketType::query()->where('clinic_id', $admin->clinic_id)->firstOrFail();
        $desk = Desk::factory()->create([
            'clinic_id' => $admin->clinic_id,
            'unit_id' => $unit->id,
            'sector_id' => $recepcion->id,
            'active' => true,
        ]);

        $tv01 = app(CreateDisplayPanel::class)->handle($admin, [
            'name' => 'TV Recepção 01',
            'code' => 'TV-R01',
            'unit_id' => $unit->id,
            'sector_ids' => [$recepcion->id],
            'active' => true,
        ]);
        $tv02 = app(CreateDisplayPanel::class)->handle($admin, [
            'name' => 'TV Recepção 02',
            'code' => 'TV-R02',
            'unit_id' => $unit->id,
            'sector_ids' => [$recepcion->id],
            'active' => true,
        ]);

        $call = $this->makeCall($admin, $unit, $recepcion, $type, $desk, 1);

        $feed1 = app(DisplayPanelFeed::class)->build($tv01->fresh(['clinic', 'unit', 'sectors']));
        $feed2 = app(DisplayPanelFeed::class)->build($tv02->fresh(['clinic', 'unit', 'sectors']));

        $this->assertSame($call->id, $feed1['current_call']['id']);
        $this->assertSame($call->id, $feed2['current_call']['id']);
        $this->assertSame(1, TicketCall::query()->whereKey($call->id)->count());
    }

    public function test_media_links_to_panels_not_sectors_and_ui_shows_unit_sector_context(): void
    {
        Storage::fake(MediaItem::DISK);
        [$admin, $unit, $recepcion, $exames] = $this->toledoWithRecepcionAndExames();

        $tvRec = app(CreateDisplayPanel::class)->handle($admin, [
            'name' => 'TV Recepção Teste',
            'code' => 'TV-RT',
            'unit_id' => $unit->id,
            'sector_ids' => [$recepcion->id],
            'active' => true,
        ]);
        $tvExa = app(CreateDisplayPanel::class)->handle($admin, [
            'name' => 'TV Exames Teste',
            'code' => 'TV-ET',
            'unit_id' => $unit->id,
            'sector_ids' => [$exames->id],
            'active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(MediaItemsManager::class)
            ->call('startCreate')
            ->assertSee('Hospital Toledo')
            ->assertSee('TV Recepção Teste')
            ->assertSee('Setor: Recepção')
            ->assertSee('TV Exames Teste')
            ->assertSee('Setor: Exames')
            ->set('name', 'Campanha')
            ->set('type', MediaType::IMAGE->value)
            ->set('durationSeconds', 10)
            ->set('selectedPanelIds', [$tvRec->id])
            ->set('upload', UploadedFile::fake()->image('c.jpg'))
            ->call('save')
            ->assertHasNoErrors();

        $media = MediaItem::query()->where('name', 'Campanha')->firstOrFail();
        $this->assertSame(1, DisplayPanelMedia::query()->where('media_item_id', $media->id)->count());
        $this->assertTrue(
            DisplayPanelMedia::query()
                ->where('media_item_id', $media->id)
                ->where('display_panel_id', $tvRec->id)
                ->exists()
        );
        $this->assertFalse(
            DisplayPanelMedia::query()
                ->where('media_item_id', $media->id)
                ->where('display_panel_id', $tvExa->id)
                ->exists()
        );
        $this->assertFalse(Schema::hasTable('media_item_sector'));
        $this->assertFalse(Schema::hasTable('media_sectors'));

        app(SyncMediaItemDisplayPanels::class)->handle($admin, $media, [$tvRec->id, $tvExa->id], 10);
        $this->assertSame(2, DisplayPanelMedia::query()->where('media_item_id', $media->id)->count());
    }

    public function test_listing_shows_singular_sector_column(): void
    {
        [$admin, $unit, $recepcion] = $this->toledoWithRecepcionAndExames();
        app(CreateDisplayPanel::class)->handle($admin, [
            'name' => 'TV Recepção',
            'code' => 'TV-REC',
            'unit_id' => $unit->id,
            'sector_ids' => [$recepcion->id],
            'active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(DisplayPanelsManager::class)
            ->assertSee('Setor')
            ->assertDontSee('Setores atendidos')
            ->assertSee('TV Recepção')
            ->assertSee('Recepção')
            ->assertSee('Hospital Toledo');
    }

    /**
     * @return array{0: User, 1: Unit, 2: Sector, 3: Sector}
     */
    private function toledoWithRecepcionAndExames(): array
    {
        $clinic = Clinic::factory()->create();
        $admin = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);
        $unit = Unit::factory()->for($clinic)->create(['name' => 'Hospital Toledo', 'active' => true]);
        TicketType::factory()->create([
            'clinic_id' => $clinic->id,
            'active' => true,
            'prefix' => 'N',
            'priority' => 10,
        ]);

        $recepcion = app(CreateSector::class)->handle($admin, [
            'name' => 'Recepção',
            'code' => 'REC',
            'unit_id' => $unit->id,
            'active' => true,
        ]);
        $exames = app(CreateSector::class)->handle($admin, [
            'name' => 'Exames',
            'code' => 'EXA',
            'unit_id' => $unit->id,
            'active' => true,
        ]);

        return [$admin, $unit, $recepcion, $exames];
    }

    private function makeCall(
        User $admin,
        Unit $unit,
        Sector $sector,
        TicketType $type,
        Desk $desk,
        int $sequence,
    ): TicketCall {
        $ticket = Ticket::factory()->create([
            'clinic_id' => $admin->clinic_id,
            'unit_id' => $unit->id,
            'sector_id' => $sector->id,
            'ticket_type_id' => $type->id,
            'status' => TicketStatus::CALLED,
            'sequence_number' => $sequence,
        ]);

        $call = new TicketCall;
        $call->forceFill([
            'clinic_id' => $admin->clinic_id,
            'unit_id' => $unit->id,
            'sector_id' => $sector->id,
            'ticket_id' => $ticket->id,
            'desk_id' => $desk->id,
            'called_by_user_id' => $admin->id,
            'call_type' => TicketCallType::INITIAL,
            'called_at' => now()->addSeconds($sequence),
        ])->save();

        return $call;
    }
}
