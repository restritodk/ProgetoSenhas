<?php

namespace Tests\Feature;

use App\Livewire\KiosksManager;
use App\Livewire\PublicKiosk;
use App\Models\Clinic;
use App\Models\ClinicSetting;
use App\Models\Kiosk;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\Unit;
use App\Models\UnitTicketType;
use App\Models\User;
use App\Services\ClinicBranding;
use App\Services\ClinicSettings;
use App\Services\KioskPrintGrantService;
use App\Support\KioskPrintPayload;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class KioskPrintIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_print_grant_uses_issued_ticket_without_secret_or_pii(): void
    {
        [$admin, $kiosk, $type] = $this->ready();
        $grants = app(KioskPrintGrantService::class);
        $secret = $grants->pair($kiosk);

        $kiosk->forceFill([
            'print_enabled' => true,
            'print_method' => 'agent',
            'print_printer_name' => 'MOCK Thermal 80mm',
            'print_paper_width' => '80',
            'print_auto_cut' => true,
        ])->save();

        $component = Livewire::test(PublicKiosk::class, ['publicToken' => $kiosk->public_token])
            ->call('issue', $type->id)
            ->assertSet('screen', 'result')
            ->assertNotSet('printDispatch', null)
            ->assertDispatched('kiosk-print-ticket');

        $dispatch = $component->get('printDispatch');
        $this->assertIsArray($dispatch);
        $this->assertSame('ticket-print-'.$component->get('issuedTicketId'), $dispatch['grant']['jobId']);
        $this->assertSame($component->get('issuedDisplayCode'), $dispatch['grant']['ticket']['displayCode']);
        $this->assertArrayNotHasKey('print_agent_secret_encrypted', $dispatch);
        $this->assertStringNotContainsString($secret, json_encode($dispatch));
        $this->assertArrayNotHasKey('cpf', $dispatch['grant']['ticket']);
        $this->assertArrayNotHasKey('email', $dispatch['grant']['ticket']);
        $this->assertSame(1, Ticket::query()->count());

        $this->assertTrue($grants->verify($dispatch['grant'], $dispatch['signature'], $secret));
        $this->assertSame($dispatch['grant']['jobId'], KioskPrintPayload::jobIdForTicket((int) $component->get('issuedTicketId')));

        $exp = (int) ($dispatch['grant']['exp'] ?? 0);
        $this->assertTrue($exp > now()->getTimestamp());
        $this->assertTrue($exp <= now()->addSeconds(KioskPrintGrantService::GRANT_TTL_SECONDS + 5)->getTimestamp());
    }

    public function test_disabled_print_does_not_dispatch_and_agent_absence_keeps_ticket(): void
    {
        [$admin, $kiosk, $type] = $this->ready();

        Livewire::test(PublicKiosk::class, ['publicToken' => $kiosk->public_token])
            ->call('issue', $type->id)
            ->assertSet('screen', 'result')
            ->assertSet('printDispatch', null)
            ->assertNotDispatched('kiosk-print-ticket')
            ->assertNotSet('issuedDisplayCode', '');

        $this->assertSame(1, Ticket::query()->count());
    }

    public function test_double_issue_while_result_screen_does_not_create_second_ticket(): void
    {
        [$admin, $kiosk, $type] = $this->ready();

        $component = Livewire::test(PublicKiosk::class, ['publicToken' => $kiosk->public_token])
            ->call('issue', $type->id)
            ->assertSet('screen', 'result')
            ->call('issue', $type->id);

        $this->assertSame(1, Ticket::query()->count());
        $this->assertSame('result', $component->get('screen'));
    }

    public function test_print_dispatch_uses_same_ticket_and_failure_path_keeps_ticket(): void
    {
        [$admin, $kiosk, $type] = $this->ready();
        $grants = app(KioskPrintGrantService::class);
        $grants->pair($kiosk);
        $kiosk->forceFill([
            'print_enabled' => true,
            'print_method' => 'agent',
            'print_printer_name' => 'MOCK Thermal 80mm',
        ])->save();

        $component = Livewire::test(PublicKiosk::class, ['publicToken' => $kiosk->public_token])
            ->call('issue', $type->id)
            ->assertSet('screen', 'result')
            ->assertDispatched('kiosk-print-ticket');

        $ticketId = (int) $component->get('issuedTicketId');
        $this->assertSame(1, Ticket::query()->count());
        $this->assertDatabaseHas('tickets', ['id' => $ticketId]);

        // Simulates agent offline / print failure: clearing dispatch must not cancel the ticket.
        $component->call('clearPrintDispatch')
            ->assertSet('printDispatch', null)
            ->assertSet('screen', 'result')
            ->assertSet('issuedTicketId', $ticketId);

        $ticket = Ticket::query()->find($ticketId);
        $this->assertNotNull($ticket);
        $this->assertSame($component->get('issuedDisplayCode'), $ticket->display_code);
        $this->assertSame(1, Ticket::query()->count());
    }

    public function test_browser_method_requests_native_print_without_agent_grant(): void
    {
        [$admin, $kiosk, $type] = $this->ready();

        $kiosk->forceFill([
            'print_enabled' => true,
            'print_method' => 'browser',
            'print_paper_width' => '58',
            'print_printer_name' => 'SHOULD-NOT-MATTER',
        ])->save();

        $component = Livewire::test(PublicKiosk::class, ['publicToken' => $kiosk->public_token])
            ->call('issue', $type->id)
            ->assertSet('screen', 'result')
            ->assertSet('printDispatch', null)
            ->assertNotDispatched('kiosk-print-ticket')
            ->assertDispatched('kiosk-browser-print');

        $payload = $component->get('browserPrintPayload');
        $this->assertIsArray($payload);
        $this->assertSame($component->get('issuedDisplayCode'), $payload['displayCode']);
        $this->assertSame('58', $payload['paperWidth']);
        $this->assertArrayHasKey('logoUrl', $payload);
        $this->assertNull($payload['logoUrl']);
        $this->assertArrayNotHasKey('signature', $payload);
        $this->assertArrayNotHasKey('agentUrl', $payload);
        $this->assertSame(1, Ticket::query()->count());
    }

    public function test_browser_receipt_includes_main_logo_url_from_clinic_identity(): void
    {
        [$admin, $kiosk, $type] = $this->ready();
        Storage::fake(ClinicSetting::DISK);

        $path = app(ClinicBranding::class)->storeLogo(
            $kiosk->clinic,
            'main_logo_path',
            UploadedFile::fake()->image('main-logo.png', 200, 80),
        );
        $expectedUrl = app(ClinicBranding::class)->urlForPath($path);

        $kiosk->forceFill([
            'print_enabled' => true,
            'print_method' => 'browser',
            'print_paper_width' => '80',
        ])->save();

        $component = Livewire::test(PublicKiosk::class, ['publicToken' => $kiosk->public_token])
            ->call('issue', $type->id)
            ->assertSet('screen', 'result')
            ->assertDispatched('kiosk-browser-print')
            ->assertNotDispatched('kiosk-print-ticket');

        $payload = $component->get('browserPrintPayload');
        $this->assertIsArray($payload);
        $this->assertSame($expectedUrl, $payload['logoUrl']);
        $this->assertSame(1, Ticket::query()->count());
        $this->assertStringContainsString((string) $kiosk->clinic_id, $path);
    }

    public function test_browser_receipt_logo_is_isolated_between_clinics(): void
    {
        [$adminA, $kioskA, $typeA] = $this->ready();
        Storage::fake(ClinicSetting::DISK);

        app(ClinicBranding::class)->storeLogo(
            $kioskA->clinic,
            'main_logo_path',
            UploadedFile::fake()->image('clinic-a.png', 120, 60),
        );

        $kioskA->forceFill([
            'print_enabled' => true,
            'print_method' => 'browser',
        ])->save();

        $clinicB = Clinic::factory()->create(['name' => 'Outra Clinica']);
        $unitB = Unit::factory()->for($clinicB)->create();
        $typeB = new TicketType;
        $typeB->forceFill([
            'clinic_id' => $clinicB->id,
            'name' => 'Normal',
            'prefix' => 'N',
            'priority' => 10,
            'active' => true,
        ])->save();
        $offerB = new UnitTicketType;
        $offerB->forceFill([
            'clinic_id' => $clinicB->id,
            'unit_id' => $unitB->id,
            'ticket_type_id' => $typeB->id,
            'position' => 1,
            'display_name' => 'Normal',
            'active' => true,
        ])->save();
        $kioskB = Kiosk::factory()->create([
            'clinic_id' => $clinicB->id,
            'unit_id' => $unitB->id,
            'print_enabled' => true,
            'print_method' => 'browser',
        ]);

        $payloadA = Livewire::test(PublicKiosk::class, ['publicToken' => $kioskA->public_token])
            ->call('issue', $typeA->id)
            ->get('browserPrintPayload');
        $payloadB = Livewire::test(PublicKiosk::class, ['publicToken' => $kioskB->public_token])
            ->call('issue', $typeB->id)
            ->get('browserPrintPayload');

        $this->assertIsArray($payloadA);
        $this->assertIsArray($payloadB);
        $this->assertNotNull($payloadA['logoUrl']);
        $this->assertNull($payloadB['logoUrl']);
    }

    public function test_browser_print_without_logo_still_emits_ticket(): void
    {
        [$admin, $kiosk, $type] = $this->ready();
        $kiosk->forceFill([
            'print_enabled' => true,
            'print_method' => 'browser',
        ])->save();

        Livewire::test(PublicKiosk::class, ['publicToken' => $kiosk->public_token])
            ->call('issue', $type->id)
            ->assertSet('screen', 'result')
            ->assertSet('browserPrintPayload.logoUrl', null);

        $this->assertSame(1, Ticket::query()->count());
    }

    public function test_disabled_print_skips_browser_and_agent(): void
    {
        [$admin, $kiosk, $type] = $this->ready();
        $grants = app(KioskPrintGrantService::class);
        $grants->pair($kiosk);
        $kiosk->forceFill([
            'print_enabled' => false,
            'print_method' => 'browser',
            'print_printer_name' => 'MOCK Thermal 80mm',
        ])->save();

        Livewire::test(PublicKiosk::class, ['publicToken' => $kiosk->public_token])
            ->call('issue', $type->id)
            ->assertSet('screen', 'result')
            ->assertSet('printDispatch', null)
            ->assertSet('browserPrintPayload', null)
            ->assertNotDispatched('kiosk-print-ticket')
            ->assertNotDispatched('kiosk-browser-print');

        $this->assertSame(1, Ticket::query()->count());
    }

    public function test_switching_agent_to_browser_keeps_agent_settings(): void
    {
        [$admin, $kiosk] = $this->ready();
        $grants = app(KioskPrintGrantService::class);
        $secret = $grants->pair($kiosk);
        $kiosk->forceFill([
            'print_enabled' => true,
            'print_method' => 'agent',
            'print_printer_name' => 'EPSON TM-T20',
            'print_agent_port' => 17321,
            'print_agent_listen_mode' => 'local',
            'print_paper_width' => '80',
            'print_auto_cut' => true,
        ])->save();

        Livewire::actingAs($admin)
            ->test(KiosksManager::class)
            ->call('edit', $kiosk->id)
            ->set('printMethod', 'browser')
            ->set('printEnabled', true)
            ->set('printPaperWidth', '58')
            ->call('save')
            ->assertHasNoErrors();

        $kiosk->refresh();
        $this->assertSame('browser', $kiosk->print_method->value);
        $this->assertTrue($kiosk->print_enabled);
        $this->assertSame('58', $kiosk->print_paper_width);
        $this->assertSame('EPSON TM-T20', $kiosk->print_printer_name);
        $this->assertSame(17321, (int) $kiosk->print_agent_port);
        $this->assertSame($secret, $grants->decryptSecret($kiosk));
    }

    public function test_browser_test_print_does_not_create_ticket(): void
    {
        [$admin, $kiosk] = $this->ready();
        $kiosk->forceFill([
            'print_method' => 'browser',
            'print_paper_width' => '80',
        ])->save();

        Livewire::actingAs($admin)
            ->test(KiosksManager::class)
            ->call('edit', $kiosk->id)
            ->set('printMethod', 'browser')
            ->call('prepareTestPrint')
            ->assertDispatched('kiosk-browser-test-print')
            ->assertNotDispatched('kiosk-agent-test-print');

        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_browser_test_print_includes_main_logo_when_configured(): void
    {
        [$admin, $kiosk] = $this->ready();
        Storage::fake(ClinicSetting::DISK);

        $path = app(ClinicBranding::class)->storeLogo(
            $kiosk->clinic,
            'main_logo_path',
            UploadedFile::fake()->image('test-logo.png', 160, 60),
        );
        $expectedUrl = app(ClinicBranding::class)->urlForPath($path);

        $kiosk->forceFill([
            'print_method' => 'browser',
            'print_paper_width' => '80',
        ])->save();

        $component = Livewire::actingAs($admin)
            ->test(KiosksManager::class)
            ->call('edit', $kiosk->id)
            ->set('printMethod', 'browser')
            ->call('prepareTestPrint')
            ->assertDispatched('kiosk-browser-test-print');

        $payload = $component->get('browserTestPrintPayload');
        $this->assertIsArray($payload);
        $this->assertSame($expectedUrl, $payload['logoUrl']);
        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_agent_test_print_still_does_not_create_ticket(): void
    {
        [$admin, $kiosk] = $this->ready();
        $grants = app(KioskPrintGrantService::class);
        $grants->pair($kiosk);
        $kiosk->forceFill([
            'print_method' => 'agent',
            'print_printer_name' => 'MOCK Thermal 80mm',
        ])->save();

        Livewire::actingAs($admin)
            ->test(KiosksManager::class)
            ->call('edit', $kiosk->id)
            ->set('printMethod', 'agent')
            ->set('printPrinterName', 'MOCK Thermal 80mm')
            ->call('prepareTestPrint')
            ->assertDispatched('kiosk-agent-test-print');

        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_finish_clears_result_after_emission(): void
    {
        [$admin, $kiosk, $type] = $this->ready();

        Livewire::test(PublicKiosk::class, ['publicToken' => $kiosk->public_token])
            ->call('issue', $type->id)
            ->assertSet('screen', 'result')
            ->call('finish')
            ->assertSet('screen', 'home')
            ->assertSet('issuedDisplayCode', '')
            ->assertSet('printDispatch', null);

        $this->assertSame(1, Ticket::query()->count());
    }

    public function test_job_id_is_stable_for_same_ticket_and_reprint_differs(): void
    {
        $this->assertSame('ticket-print-42', KioskPrintPayload::jobIdForTicket(42));
        $first = KioskPrintPayload::jobIdForReprint(42);
        $second = KioskPrintPayload::jobIdForReprint(42);
        $this->assertStringStartsWith('ticket-reprint-42-', $first);
        $this->assertNotSame($first, $second);
    }

    public function test_test_print_grant_does_not_create_ticket(): void
    {
        [$admin, $kiosk] = $this->ready();
        $grants = app(KioskPrintGrantService::class);
        $grants->pair($kiosk);
        $kiosk->forceFill(['print_printer_name' => 'MOCK Thermal 80mm'])->save();

        $grant = $grants->grantPrintTest($kiosk->fresh(), 'Clinica', 'Recepcao');
        $this->assertNotNull($grant);
        $this->assertSame('print_test', $grant['grant']['action']);
        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_pairing_secret_is_hidden_from_model_array(): void
    {
        [$admin, $kiosk] = $this->ready();
        app(KioskPrintGrantService::class)->pair($kiosk);
        $kiosk->refresh();

        $this->assertArrayNotHasKey('print_agent_secret_encrypted', $kiosk->toArray());
        $this->assertNotNull($kiosk->print_agent_secret_encrypted);
    }

    public function test_kiosk_print_settings_are_per_kiosk(): void
    {
        [$admin, $kioskA] = $this->ready();
        $unit = $kioskA->unit;
        $kioskB = Kiosk::factory()->create([
            'clinic_id' => $admin->clinic_id,
            'unit_id' => $unit->id,
            'name' => 'Totem B',
            'code' => 'TB',
        ]);

        $grants = app(KioskPrintGrantService::class);
        $grants->pair($kioskA);
        $kioskA->forceFill([
            'print_enabled' => true,
            'print_printer_name' => 'Printer A',
        ])->save();

        $this->assertTrue($kioskA->fresh()->print_enabled);
        $this->assertFalse((bool) $kioskB->fresh()->print_enabled);
        $this->assertNull($kioskB->fresh()->print_printer_name);
    }

    public function test_admin_can_pair_and_revoke_without_exposing_secret_afterward(): void
    {
        [$admin, $kiosk] = $this->ready();

        $component = Livewire::actingAs($admin)
            ->test(KiosksManager::class)
            ->call('edit', $kiosk->id)
            ->call('pairPrintAgent')
            ->assertSet('printIsPaired', true);

        $once = $component->get('pairingSecretOnce');
        $this->assertNotSame('', $once);

        $component->call('revokePrintAgent')
            ->assertSet('printIsPaired', false)
            ->assertSet('pairingSecretOnce', '')
            ->assertSet('printEnabled', false);

        $this->assertNull($kiosk->fresh()->print_agent_secret_encrypted);
    }

    public function test_auto_return_default_is_three_seconds(): void
    {
        $clinic = Clinic::factory()->create();
        $bag = app(ClinicSettings::class)->all($clinic);
        $this->assertSame(3, $bag['kiosk_auto_return_seconds']);
    }

    public function test_auto_return_presentation_is_at_least_three_seconds(): void
    {
        [$admin, $kiosk, $type] = $this->ready();

        $component = Livewire::test(PublicKiosk::class, ['publicToken' => $kiosk->public_token]);
        $presentation = $component->get('presentation');
        $this->assertGreaterThanOrEqual(3, (int) ($presentation['auto_return_seconds'] ?? 0));
    }

    public function test_cross_clinic_kiosk_print_settings_are_isolated(): void
    {
        [$adminA, $kioskA] = $this->ready();
        $clinicB = Clinic::factory()->create();
        $unitB = Unit::factory()->for($clinicB)->create();
        $adminB = User::factory()->create([
            'clinic_id' => $clinicB->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);
        $kioskB = Kiosk::factory()->create([
            'clinic_id' => $clinicB->id,
            'unit_id' => $unitB->id,
        ]);

        app(KioskPrintGrantService::class)->pair($kioskA);
        $kioskA->forceFill(['print_enabled' => true, 'print_printer_name' => 'A'])->save();

        Livewire::actingAs($adminB)
            ->test(KiosksManager::class)
            ->call('edit', $kioskB->id)
            ->assertSet('printEnabled', false)
            ->assertSet('printIsPaired', false);

        $this->assertTrue($kioskA->fresh()->print_enabled);
        $this->assertSame('A', $kioskA->fresh()->print_printer_name);
        $this->assertFalse((bool) $kioskB->fresh()->print_enabled);
        $this->assertNull($kioskB->fresh()->print_agent_secret_encrypted);
    }

    public function test_local_mode_is_default_and_uses_loopback_agent_url(): void
    {
        [$admin, $kiosk, $type] = $this->ready();
        $grants = app(KioskPrintGrantService::class);
        $grants->pair($kiosk);

        $this->assertSame('local', $kiosk->fresh()->print_agent_listen_mode);

        $kiosk->forceFill([
            'print_enabled' => true,
            'print_method' => 'agent',
            'print_printer_name' => 'MOCK Thermal 80mm',
            'print_agent_listen_mode' => 'local',
        ])->save();

        $component = Livewire::test(PublicKiosk::class, ['publicToken' => $kiosk->public_token])
            ->call('issue', $type->id);

        $dispatch = $component->get('printDispatch');
        $this->assertIsArray($dispatch);
        $this->assertSame('http://127.0.0.1:17321', $dispatch['agentUrl']);
        $this->assertSame(1, Ticket::query()->count());
    }

    public function test_lan_mode_uses_configured_host_and_missing_host_skips_print_without_new_ticket(): void
    {
        [$admin, $kiosk, $type] = $this->ready();
        $grants = app(KioskPrintGrantService::class);
        $grants->pair($kiosk);

        $kiosk->forceFill([
            'print_enabled' => true,
            'print_method' => 'agent',
            'print_printer_name' => 'MOCK Thermal 80mm',
            'print_agent_listen_mode' => 'lan',
            'print_agent_host' => null,
        ])->save();

        Livewire::test(PublicKiosk::class, ['publicToken' => $kiosk->public_token])
            ->call('issue', $type->id)
            ->assertSet('screen', 'result')
            ->assertSet('printDispatch', null);

        $this->assertSame(1, Ticket::query()->count());

        $kiosk->forceFill(['print_agent_host' => '192.168.10.20'])->save();

        $component = Livewire::test(PublicKiosk::class, ['publicToken' => $kiosk->public_token])
            ->call('issue', $type->id);

        $dispatch = $component->get('printDispatch');
        $this->assertIsArray($dispatch);
        $this->assertSame('http://192.168.10.20:17321', $dispatch['agentUrl']);
        $this->assertSame(2, Ticket::query()->count());
    }

    /**
     * @return array{0: User, 1: Kiosk, 2: TicketType}
     */
    private function ready(): array
    {
        $clinic = Clinic::factory()->create(['name' => 'Clinica Print']);
        $unit = Unit::factory()->for($clinic)->create(['name' => 'Recepcao']);
        $admin = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);

        $type = new TicketType;
        $type->forceFill([
            'clinic_id' => $clinic->id,
            'name' => 'Normal',
            'prefix' => 'N',
            'priority' => 10,
            'active' => true,
        ])->save();

        $offer = new UnitTicketType;
        $offer->forceFill([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'ticket_type_id' => $type->id,
            'position' => 1,
            'display_name' => 'Atendimento Normal',
            'active' => true,
        ])->save();

        $kiosk = Kiosk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'name' => 'Totem Print',
            'code' => 'TP',
            'active' => true,
        ]);

        return [$admin, $kiosk, $type];
    }
}
