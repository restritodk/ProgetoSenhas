<?php

namespace Tests\Feature;

use App\Actions\CallNextTicket;
use App\Actions\ClaimDesk;
use App\Actions\CreateDesk;
use App\Actions\CreateDisplayPanel;
use App\Actions\CreateSector;
use App\Actions\DeleteSector;
use App\Actions\EnsureClinicRolePermissions;
use App\Actions\EnsureDefaultSectorForUnit;
use App\Actions\IssueTicket;
use App\Actions\RecallTicket;
use App\Actions\SyncUnitTicketTypes;
use App\Livewire\AttendantPanel;
use App\Livewire\SectorsManager;
use App\Models\Clinic;
use App\Models\ClinicRolePermission;
use App\Models\Desk;
use App\Models\DisplayPanel;
use App\Models\Kiosk;
use App\Models\Permission;
use App\Models\Sector;
use App\Models\SectorTicketType;
use App\Models\Ticket;
use App\Models\TicketCall;
use App\Models\TicketType;
use App\Models\Unit;
use App\Models\User;
use App\Services\ClinicPermissionResolver;
use App\Services\DisplayPanelFeed;
use App\Services\NextTicketSelector;
use App\Services\OperationalContext;
use App\TicketCallType;
use App\TicketStatus;
use App\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class SectorStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_clinic_a_cannot_attach_sector_to_clinic_b_unit_via_create_desk(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $unitA = Unit::factory()->for($clinicA)->create();
        $unitB = Unit::factory()->for($clinicB)->create();
        $sectorB = app(EnsureDefaultSectorForUnit::class)->handle($unitB);
        $adminA = User::factory()->create(['clinic_id' => $clinicA->id, 'role' => UserRole::ADMINISTRATOR]);

        $this->expectException(ValidationException::class);

        app(CreateDesk::class)->handle($adminA, [
            'name' => 'Mesa X',
            'code' => 'MX',
            'unit_id' => $unitA->id,
            'sector_id' => $sectorB->id,
            'active' => true,
        ]);
    }

    public function test_unit_a_rejects_sector_belonging_to_unit_b(): void
    {
        $clinic = Clinic::factory()->create();
        $unitA = Unit::factory()->for($clinic)->create(['name' => 'Hospital Toledo']);
        $unitB = Unit::factory()->for($clinic)->create(['name' => 'Unidade Centro']);
        $sectorB = app(CreateSector::class)->handle(
            User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]),
            [
                'name' => 'Recepção',
                'code' => 'REC',
                'unit_id' => $unitB->id,
                'active' => true,
            ],
        );
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);

        try {
            app(CreateDesk::class)->handle($admin, [
                'name' => 'Mesa 01',
                'code' => 'M01',
                'unit_id' => $unitA->id,
                'sector_id' => $sectorB->id,
                'active' => true,
            ]);
            $this->fail('Inconsistent unit/sector must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('sectorId', $exception->errors());
        }
    }

    public function test_desk_belongs_to_correct_sector_and_ticket_inherits_kiosk_sector(): void
    {
        [$clinic, $unit, $sectorRecepcion, $desk, $kiosk, $admin, $type] = $this->hospitalToledoRecepcion();

        $this->assertSame($sectorRecepcion->id, $desk->sector_id);
        $this->assertSame($unit->id, $desk->unit_id);
        $this->assertSame($clinic->id, $desk->clinic_id);

        $ticket = app(IssueTicket::class)->handleFromKiosk(
            $kiosk,
            $type->id,
            'token-sector-issue-00000001',
            '127.0.0.1',
        );

        $this->assertSame($clinic->id, $ticket->clinic_id);
        $this->assertSame($unit->id, $ticket->unit_id);
        $this->assertSame($sectorRecepcion->id, $ticket->sector_id);
        $this->assertSame($type->id, $ticket->ticket_type_id);
    }

    public function test_kiosk_rejects_browser_manipulated_sector_type_outside_offer(): void
    {
        [$clinic, $unit, $sectorRecepcion, , $kiosk, $admin, $type] = $this->hospitalToledoRecepcion();
        $otherType = TicketType::factory()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Emergencial',
            'prefix' => 'E',
            'priority' => 100,
            'active' => true,
        ]);

        // Only Normal is offered on the sector.
        SectorTicketType::query()->where('sector_id', $sectorRecepcion->id)->delete();
        $offer = new SectorTicketType;
        $offer->forceFill([
            'clinic_id' => $clinic->id,
            'sector_id' => $sectorRecepcion->id,
            'ticket_type_id' => $type->id,
            'active' => true,
            'position' => 0,
        ])->save();

        // Also remove unit-level fallback for the emergency type.
        app(SyncUnitTicketTypes::class)->handle($admin, $unit, [
            ['ticket_type_id' => $type->id, 'active' => true, 'display_name' => null, 'position' => 0],
        ]);

        try {
            app(IssueTicket::class)->handleFromKiosk(
                $kiosk,
                $otherType->id,
                'token-manipulated-type-00001',
                '127.0.0.1',
            );
            $this->fail('Manipulated type must be rejected.');
        } catch (ValidationException) {
            $this->assertSame(0, Ticket::query()->count());
        }
    }

    public function test_tv_filters_calls_by_configured_sectors(): void
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create(['name' => 'Hospital Toledo']);
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);
        $recepcion = app(CreateSector::class)->handle($admin, [
            'name' => 'Recepção',
            'code' => 'REC',
            'unit_id' => $unit->id,
            'active' => true,
        ]);
        $guias = app(CreateSector::class)->handle($admin, [
            'name' => 'Liberação de Guias',
            'code' => 'GUI',
            'unit_id' => $unit->id,
            'active' => true,
        ]);
        $type = TicketType::factory()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Normal',
            'prefix' => 'N',
            'priority' => 10,
        ]);

        $deskRec = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'sector_id' => $recepcion->id,
            'name' => 'Mesa 01',
        ]);
        $deskGuias = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'sector_id' => $guias->id,
            'name' => 'Guichê 01',
        ]);

        $ticketRec = Ticket::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'sector_id' => $recepcion->id,
            'ticket_type_id' => $type->id,
            'status' => TicketStatus::CALLED,
            'sequence_number' => 1,
        ]);
        $ticketGuias = Ticket::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'sector_id' => $guias->id,
            'ticket_type_id' => $type->id,
            'status' => TicketStatus::CALLED,
            'sequence_number' => 2,
        ]);

        $callRec = new TicketCall;
        $callRec->forceFill([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'sector_id' => $recepcion->id,
            'ticket_id' => $ticketRec->id,
            'desk_id' => $deskRec->id,
            'called_by_user_id' => $admin->id,
            'call_type' => TicketCallType::INITIAL,
            'called_at' => now(),
        ])->save();
        $callGuias = new TicketCall;
        $callGuias->forceFill([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'sector_id' => $guias->id,
            'ticket_id' => $ticketGuias->id,
            'desk_id' => $deskGuias->id,
            'called_by_user_id' => $admin->id,
            'call_type' => TicketCallType::INITIAL,
            'called_at' => now()->addSecond(),
        ])->save();

        $tvRecepcion = DisplayPanel::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'name' => 'TV Recepção',
            'active' => true,
        ]);
        $tvRecepcion->sectors()->sync([
            $recepcion->id => ['clinic_id' => $clinic->id],
        ]);

        $tvGeral = DisplayPanel::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'name' => 'TV Geral',
            'active' => true,
        ]);
        $tvGeral->sectors()->sync([
            $recepcion->id => ['clinic_id' => $clinic->id],
            $guias->id => ['clinic_id' => $clinic->id],
        ]);

        $feedRec = app(DisplayPanelFeed::class)->build($tvRecepcion->fresh(['clinic', 'unit', 'sectors']));
        $idsRec = collect($feedRec['recent_calls'])->pluck('id')->all();
        $this->assertContains($callRec->id, $idsRec);
        $this->assertNotContains($callGuias->id, $idsRec);

        $feedGeral = app(DisplayPanelFeed::class)->build($tvGeral->fresh(['clinic', 'unit', 'sectors']));
        $idsGeral = collect($feedGeral['recent_calls'])->pluck('id')->all();
        $this->assertContains($callRec->id, $idsGeral);
        $this->assertContains($callGuias->id, $idsGeral);
    }

    public function test_attendant_on_reception_desk_sees_only_reception_tickets(): void
    {
        [$clinic, $unit, $sectorRecepcion, $desk, , $admin, $type] = $this->hospitalToledoRecepcion();
        $guias = app(CreateSector::class)->handle($admin, [
            'name' => 'Liberação de Guias',
            'code' => 'GUI',
            'unit_id' => $unit->id,
            'active' => true,
        ]);

        $ticketRec = Ticket::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'sector_id' => $sectorRecepcion->id,
            'ticket_type_id' => $type->id,
            'status' => TicketStatus::WAITING,
            'sequence_number' => 1,
        ]);
        $ticketGuias = Ticket::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'sector_id' => $guias->id,
            'ticket_type_id' => $type->id,
            'status' => TicketStatus::WAITING,
            'sequence_number' => 2,
        ]);

        $attendant = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ATTENDANT]);
        $attendant->units()->attach([$unit->id => ['clinic_id' => $clinic->id]]);

        $this->actingAs($attendant);
        app(OperationalContext::class)->setActiveUnit($attendant, $unit, session());
        app(ClaimDesk::class)->handle($attendant, $desk);

        $upcoming = Livewire::actingAs($attendant)->test(AttendantPanel::class)->instance()->upcomingQueue;
        $this->assertTrue($upcoming->contains(fn (Ticket $row): bool => $row->id === $ticketRec->id));
        $this->assertFalse($upcoming->contains(fn (Ticket $row): bool => $row->id === $ticketGuias->id));

        $called = app(CallNextTicket::class)->handle($attendant);
        $this->assertTrue($called->is($ticketRec));
        $this->assertSame($sectorRecepcion->id, $called->sector_id);
        $this->assertDatabaseHas('ticket_calls', [
            'ticket_id' => $ticketRec->id,
            'sector_id' => $sectorRecepcion->id,
            'call_type' => TicketCallType::INITIAL->value,
        ]);
    }

    public function test_administrator_operating_desk_also_respects_sector_context(): void
    {
        [$clinic, $unit, $sectorRecepcion, $desk, , $admin, $type] = $this->hospitalToledoRecepcion();
        $guias = app(CreateSector::class)->handle($admin, [
            'name' => 'Liberação de Guias',
            'code' => 'GUI',
            'unit_id' => $unit->id,
            'active' => true,
        ]);

        Ticket::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'sector_id' => $guias->id,
            'ticket_type_id' => $type->id,
            'status' => TicketStatus::WAITING,
            'sequence_number' => 1,
        ]);
        $ticketRec = Ticket::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'sector_id' => $sectorRecepcion->id,
            'ticket_type_id' => $type->id,
            'status' => TicketStatus::WAITING,
            'sequence_number' => 2,
        ]);

        $this->actingAs($admin);
        app(OperationalContext::class)->setActiveUnit($admin, $unit, session());
        app(ClaimDesk::class)->handle($admin, $desk);

        $selected = app(NextTicketSelector::class)
            ->rankedWaitingQueue($unit, CarbonImmutable::now(config('app.timezone')), $desk)
            ->first();

        $this->assertNotNull($selected);
        $this->assertTrue($selected->is($ticketRec));

        $called = app(CallNextTicket::class)->handle($admin);
        $this->assertTrue($called->is($ticketRec));
    }

    public function test_recall_keeps_sector_and_creates_distinct_ticket_call(): void
    {
        [$clinic, $unit, $sectorRecepcion, $desk, , $admin, $type] = $this->hospitalToledoRecepcion();
        $ticket = Ticket::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'sector_id' => $sectorRecepcion->id,
            'ticket_type_id' => $type->id,
            'status' => TicketStatus::WAITING,
        ]);

        $this->actingAs($admin);
        app(OperationalContext::class)->setActiveUnit($admin, $unit, session());
        app(ClaimDesk::class)->handle($admin, $desk);

        $called = app(CallNextTicket::class)->handle($admin);
        $this->assertTrue($called->is($ticket));

        $recalled = app(RecallTicket::class)->handle($admin, $called);
        $this->assertTrue($recalled->is($ticket));

        $this->assertSame(2, TicketCall::query()->where('ticket_id', $ticket->id)->count());
        $this->assertDatabaseHas('ticket_calls', [
            'ticket_id' => $ticket->id,
            'sector_id' => $sectorRecepcion->id,
            'call_type' => TicketCallType::RECALL->value,
        ]);
    }

    public function test_sector_with_history_cannot_be_hard_deleted(): void
    {
        [$clinic, $unit, $sectorRecepcion, $desk, , $admin] = $this->hospitalToledoRecepcion();

        $this->expectException(ValidationException::class);
        app(DeleteSector::class)->handle($admin, $sectorRecepcion);
    }

    public function test_unused_sector_can_be_deleted(): void
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create();
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);
        $sector = app(CreateSector::class)->handle($admin, [
            'name' => 'Exames',
            'code' => 'EXA',
            'unit_id' => $unit->id,
            'active' => true,
        ]);

        app(DeleteSector::class)->handle($admin, $sector);

        $this->assertDatabaseMissing('sectors', ['id' => $sector->id]);
    }

    public function test_sectors_index_requires_permission_and_renders(): void
    {
        $clinic = Clinic::factory()->create();
        app(EnsureClinicRolePermissions::class)->handle($clinic);
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);

        $this->actingAs($admin)
            ->get(route('sectors.index'))
            ->assertOk()
            ->assertSee('Setores')
            ->assertSee('Organize as áreas de atendimento de cada unidade.')
            ->assertSee('Novo setor');
    }

    public function test_sectors_menu_visible_for_administrator_and_hidden_for_attendant(): void
    {
        $clinic = Clinic::factory()->create();
        app(EnsureClinicRolePermissions::class)->handle($clinic);
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);
        $attendant = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ATTENDANT]);
        $supervisor = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::SUPERVISOR]);

        $this->assertTrue($admin->can('viewAny', Sector::class));
        $this->assertFalse($attendant->can('viewAny', Sector::class));
        $this->assertFalse($supervisor->can('viewAny', Sector::class));

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Setores')
            ->assertSee(route('sectors.index'), false);

        $this->actingAs($attendant)
            ->get(route('sectors.index'))
            ->assertRedirect(route('attendant.panel'));

        $this->actingAs($supervisor)
            ->get(route('sectors.index'))
            ->assertForbidden();
    }

    public function test_administrator_can_create_and_list_only_own_clinic_sectors(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        app(EnsureClinicRolePermissions::class)->handle($clinicA);
        app(EnsureClinicRolePermissions::class)->handle($clinicB);

        $unitA = Unit::factory()->for($clinicA)->create(['name' => 'Hospital Toledo']);
        $unitB = Unit::factory()->for($clinicB)->create(['name' => 'Outra Clínica Unit']);
        $adminA = User::factory()->create(['clinic_id' => $clinicA->id, 'role' => UserRole::ADMINISTRATOR]);
        $adminB = User::factory()->create(['clinic_id' => $clinicB->id, 'role' => UserRole::ADMINISTRATOR]);

        app(CreateSector::class)->handle($adminB, [
            'name' => 'Setor B',
            'code' => 'SB',
            'unit_id' => $unitB->id,
            'active' => true,
        ]);

        Livewire::actingAs($adminA)
            ->test(SectorsManager::class)
            ->call('startCreate')
            ->set('unitId', $unitA->id)
            ->set('name', 'Recepção')
            ->set('code', 'REC')
            ->set('description', 'Atendimento da recepção')
            ->set('active', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Recepção')
            ->assertSee('REC')
            ->assertSee('Hospital Toledo')
            ->assertDontSee('Setor B');

        $this->assertDatabaseHas('sectors', [
            'clinic_id' => $clinicA->id,
            'unit_id' => $unitA->id,
            'code' => 'REC',
            'name' => 'Recepção',
        ]);
    }

    public function test_ensure_grants_missing_sectors_permissions_to_existing_administrator(): void
    {
        $clinic = Clinic::factory()->create();
        app(EnsureClinicRolePermissions::class)->handle($clinic);

        $sectorsViewId = Permission::query()->where('key', 'sectors.view')->value('id');
        $this->assertNotNull($sectorsViewId);

        ClinicRolePermission::query()
            ->where('clinic_id', $clinic->id)
            ->where('role', UserRole::ADMINISTRATOR)
            ->where('permission_id', $sectorsViewId)
            ->delete();

        app(ClinicPermissionResolver::class)->forget($clinic->id, UserRole::ADMINISTRATOR);

        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);
        $this->assertFalse($admin->hasPermission('sectors.view'));

        app(EnsureClinicRolePermissions::class)->handle($clinic);
        $admin->refresh();

        $this->assertTrue($admin->hasPermission('sectors.view'));
        $this->assertTrue($admin->can('viewAny', Sector::class));
    }

    public function test_sector_model_relations_and_unique_code_per_unit(): void
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create();
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);
        TicketType::factory()->create(['clinic_id' => $clinic->id, 'active' => true, 'prefix' => 'N', 'priority' => 10]);
        $sector = app(CreateSector::class)->handle($admin, [
            'name' => 'Exames',
            'code' => 'EXA',
            'unit_id' => $unit->id,
            'active' => true,
        ]);

        $this->assertTrue($clinic->units()->whereKey($unit->id)->exists());
        $this->assertTrue($unit->sectors()->whereKey($sector->id)->exists());
        $this->assertTrue($sector->clinic->is($clinic));
        $this->assertTrue($sector->unit->is($unit));
        $this->assertTrue($sector->sectorTicketTypes()->exists());

        $this->expectException(ValidationException::class);
        app(CreateSector::class)->handle($admin, [
            'name' => 'Exames 2',
            'code' => 'EXA',
            'unit_id' => $unit->id,
            'active' => true,
        ]);
    }

    public function test_display_panel_rejects_sector_from_another_unit(): void
    {
        $clinic = Clinic::factory()->create();
        $unitA = Unit::factory()->for($clinic)->create(['name' => 'Hospital Toledo']);
        $unitB = Unit::factory()->for($clinic)->create(['name' => 'Unidade Centro']);
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);
        $sectorB = app(CreateSector::class)->handle($admin, [
            'name' => 'Recepção',
            'code' => 'REC',
            'unit_id' => $unitB->id,
            'active' => true,
        ]);

        $this->expectException(ValidationException::class);
        app(CreateDisplayPanel::class)->handle($admin, [
            'name' => 'TV Toledo',
            'code' => 'TVT',
            'unit_id' => $unitA->id,
            'sector_ids' => [$sectorB->id],
            'active' => true,
        ]);
    }

    public function test_two_kiosks_on_different_sectors_emit_into_their_own_sector(): void
    {
        [$clinic, $unit, $recepcion, , , $admin, $type] = $this->hospitalToledoRecepcion();
        $exames = app(CreateSector::class)->handle($admin, [
            'name' => 'Exames',
            'code' => 'EXA',
            'unit_id' => $unit->id,
            'active' => true,
        ]);
        SectorTicketType::query()->where('sector_id', $exames->id)->delete();
        $offer = new SectorTicketType;
        $offer->forceFill([
            'clinic_id' => $clinic->id,
            'sector_id' => $exames->id,
            'ticket_type_id' => $type->id,
            'active' => true,
            'position' => 0,
        ])->save();

        $kioskRec = Kiosk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'sector_id' => $recepcion->id,
            'active' => true,
        ]);
        $kioskExa = Kiosk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'sector_id' => $exames->id,
            'active' => true,
        ]);

        $ticketRec = app(IssueTicket::class)->handleFromKiosk($kioskRec, $type->id, 'tok-rec-sector-00000001', '127.0.0.1');
        $ticketExa = app(IssueTicket::class)->handleFromKiosk($kioskExa, $type->id, 'tok-exa-sector-00000001', '127.0.0.1');

        $this->assertSame($recepcion->id, $ticketRec->sector_id);
        $this->assertSame($exames->id, $ticketExa->sector_id);
        $this->assertSame($unit->id, $ticketRec->unit_id);
        $this->assertSame($unit->id, $ticketExa->unit_id);
    }

    public function test_call_next_never_selects_ticket_from_other_unit_same_sector_name(): void
    {
        $clinic = Clinic::factory()->create();
        $toledo = Unit::factory()->for($clinic)->create(['name' => 'Hospital Toledo']);
        $centro = Unit::factory()->for($clinic)->create(['name' => 'Unidade Centro']);
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);
        $recToledo = app(CreateSector::class)->handle($admin, [
            'name' => 'Recepção',
            'code' => 'REC',
            'unit_id' => $toledo->id,
            'active' => true,
        ]);
        $recCentro = app(CreateSector::class)->handle($admin, [
            'name' => 'Recepção',
            'code' => 'REC',
            'unit_id' => $centro->id,
            'active' => true,
        ]);
        $type = TicketType::factory()->create(['clinic_id' => $clinic->id, 'prefix' => 'N', 'priority' => 10]);
        $desk = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $toledo->id,
            'sector_id' => $recToledo->id,
        ]);
        $local = Ticket::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $toledo->id,
            'sector_id' => $recToledo->id,
            'ticket_type_id' => $type->id,
            'status' => TicketStatus::WAITING,
            'sequence_number' => 1,
        ]);
        Ticket::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $centro->id,
            'sector_id' => $recCentro->id,
            'ticket_type_id' => $type->id,
            'status' => TicketStatus::WAITING,
            'sequence_number' => 2,
        ]);

        $attendant = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ATTENDANT]);
        $attendant->units()->attach([$toledo->id => ['clinic_id' => $clinic->id]]);
        $this->actingAs($attendant);
        app(OperationalContext::class)->setActiveUnit($attendant, $toledo, session());
        app(ClaimDesk::class)->handle($attendant, $desk);

        $called = app(CallNextTicket::class)->handle($attendant);
        $this->assertTrue($called->is($local));
    }

    public function test_distribution_does_not_select_tickets_from_other_sector(): void
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create();
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);
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
        $normal = TicketType::factory()->create(['clinic_id' => $clinic->id, 'name' => 'Normal', 'prefix' => 'N', 'priority' => 10]);
        $preferential = TicketType::factory()->create(['clinic_id' => $clinic->id, 'name' => 'Preferencial', 'prefix' => 'P', 'priority' => 20]);
        $desk = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'sector_id' => $recepcion->id,
        ]);

        Ticket::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'sector_id' => $recepcion->id,
            'ticket_type_id' => $normal->id,
            'status' => TicketStatus::WAITING,
            'sequence_number' => 1,
        ]);
        $foreign = Ticket::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'sector_id' => $exames->id,
            'ticket_type_id' => $preferential->id,
            'status' => TicketStatus::WAITING,
            'sequence_number' => 1,
        ]);

        $ranked = app(NextTicketSelector::class)
            ->rankedWaitingQueue($unit, CarbonImmutable::now(config('app.timezone')), $desk);

        $this->assertFalse($ranked->contains(fn (Ticket $ticket): bool => $ticket->id === $foreign->id));
        $this->assertTrue($ranked->every(fn (Ticket $ticket): bool => (int) $ticket->sector_id === (int) $recepcion->id));
    }

    /**
     * @return array{0: Clinic, 1: Unit, 2: Sector, 3: Desk, 4: Kiosk, 5: User, 6: TicketType}
     */
    private function hospitalToledoRecepcion(): array
    {
        $clinic = Clinic::factory()->create(['name' => 'Humana Saúde']);
        $unit = Unit::factory()->for($clinic)->create(['name' => 'Hospital Toledo']);
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);
        $sector = app(CreateSector::class)->handle($admin, [
            'name' => 'Recepção',
            'code' => 'REC',
            'unit_id' => $unit->id,
            'active' => true,
        ]);
        $type = TicketType::factory()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Preferencial',
            'prefix' => 'P',
            'priority' => 50,
            'active' => true,
        ]);
        app(SyncUnitTicketTypes::class)->handle($admin, $unit, [
            ['ticket_type_id' => $type->id, 'active' => true, 'display_name' => null, 'position' => 0],
        ]);
        SectorTicketType::query()->where('sector_id', $sector->id)->where('ticket_type_id', $type->id)->delete();
        $sectorOffer = new SectorTicketType;
        $sectorOffer->forceFill([
            'clinic_id' => $clinic->id,
            'sector_id' => $sector->id,
            'ticket_type_id' => $type->id,
            'active' => true,
            'position' => 0,
        ])->save();

        $desk = app(CreateDesk::class)->handle($admin, [
            'name' => 'Mesa 01',
            'code' => 'M01',
            'unit_id' => $unit->id,
            'sector_id' => $sector->id,
            'active' => true,
        ]);

        $kiosk = Kiosk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'sector_id' => $sector->id,
            'name' => 'Totem Recepção 01',
            'active' => true,
        ]);

        return [$clinic, $unit, $sector, $desk, $kiosk, $admin, $type];
    }
}
