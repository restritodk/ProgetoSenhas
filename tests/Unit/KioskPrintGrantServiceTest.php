<?php

namespace Tests\Unit;

use App\Models\Kiosk;
use App\Services\KioskPrintGrantService;
use Tests\TestCase;

class KioskPrintGrantServiceTest extends TestCase
{
    public function test_canonical_json_sorts_keys_recursively(): void
    {
        $service = app(KioskPrintGrantService::class);

        $payload = [
            'z' => 1,
            'ticket' => [
                'unitName' => 'Recepcao',
                'displayCode' => 'N004',
                'clinicName' => 'Clinica',
            ],
            'a' => true,
        ];

        $this->assertSame(
            '{"a":true,"ticket":{"clinicName":"Clinica","displayCode":"N004","unitName":"Recepcao"},"z":1}',
            $service->canonicalJson($payload),
        );
    }

    public function test_hmac_signature_is_deterministic(): void
    {
        $service = app(KioskPrintGrantService::class);
        $secret = 'dev-only-pairing-secret-change-me-32chars-min';
        $grant = [
            'v' => 1,
            'action' => 'print_ticket',
            'jobId' => 'ticket-print-10',
            'printer' => 'MOCK Thermal 80mm',
            'paperWidth' => '80',
            'autoCut' => true,
            'ticket' => [
                'displayCode' => 'N004',
                'typeLabel' => 'Atendimento Normal',
                'clinicName' => 'Clinica',
                'unitName' => 'Recepcao',
                'issuedAtLabel' => '23/09/2026 10:14',
                'message' => 'Aguarde',
            ],
            'exp' => 1893456000,
            'nonce' => 'abc123',
        ];

        $signature = $service->sign($grant, $secret);
        $this->assertTrue($service->verify($grant, $signature, $secret));
        $this->assertFalse($service->verify($grant, $signature, $secret.'x'));
        $this->assertSame(64, strlen($signature));
    }

    public function test_paper_width_defaults_to_eighty_when_invalid(): void
    {
        $service = app(KioskPrintGrantService::class);
        $this->assertSame('80', $service->normalizePaperWidth('auto'));
        $this->assertSame('58', $service->normalizePaperWidth('58'));
        $this->assertSame('80', $service->normalizePaperWidth('80'));
    }

    public function test_local_agent_url_uses_loopback(): void
    {
        $service = app(KioskPrintGrantService::class);
        $kiosk = new Kiosk;
        $kiosk->forceFill([
            'print_agent_listen_mode' => 'local',
            'print_agent_port' => 17321,
            'print_agent_host' => '192.168.1.50',
        ]);

        $this->assertSame('http://127.0.0.1:17321', $service->agentBaseUrl($kiosk));
    }

    public function test_lan_agent_url_requires_host_and_is_not_default(): void
    {
        $service = app(KioskPrintGrantService::class);
        $this->assertSame('local', $service->normalizeListenMode(null));
        $this->assertSame('lan', $service->normalizeListenMode('LAN'));

        $kiosk = new Kiosk;
        $kiosk->forceFill([
            'print_agent_listen_mode' => 'lan',
            'print_agent_port' => 17321,
            'print_agent_host' => null,
        ]);
        $this->assertNull($service->agentBaseUrl($kiosk));

        $kiosk->print_agent_host = '192.168.1.50';
        $this->assertSame('http://192.168.1.50:17321', $service->agentBaseUrl($kiosk));
    }
}
