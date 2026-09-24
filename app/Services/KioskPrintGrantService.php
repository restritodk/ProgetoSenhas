<?php

namespace App\Services;

use App\KioskPrintMethod;
use App\Models\Kiosk;
use App\Support\KioskPrintPayload;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Issues short-lived HMAC print grants for the local Humana Print Agent.
 * The long-lived pairing secret never leaves the server (or the agent config).
 */
class KioskPrintGrantService
{
    public const DEFAULT_PORT = 17321;

    public const GRANT_TTL_SECONDS = 90;

    public const PAPER_WIDTHS = ['58', '80'];

    public const LISTEN_LOCAL = 'local';

    public const LISTEN_LAN = 'lan';

    /**
     * Create or rotate the pairing secret. Returns the plaintext once for technician install.
     */
    public function pair(Kiosk $kiosk): string
    {
        $secret = Str::lower(Str::random(64));

        $kiosk->forceFill([
            'print_agent_secret_encrypted' => Crypt::encryptString($secret),
            'print_paired_at' => now(),
        ])->save();

        return $secret;
    }

    public function revokePairing(Kiosk $kiosk): void
    {
        $kiosk->forceFill([
            'print_agent_secret_encrypted' => null,
            'print_paired_at' => null,
            'print_printer_name' => null,
            'print_enabled' => false,
        ])->save();
    }

    public function hasPairing(Kiosk $kiosk): bool
    {
        return filled($kiosk->print_agent_secret_encrypted);
    }

    public function normalizeListenMode(mixed $value): string
    {
        $mode = strtolower(trim(is_string($value) ? $value : (string) $value));

        return $mode === self::LISTEN_LAN ? self::LISTEN_LAN : self::LISTEN_LOCAL;
    }

    /**
     * Sanitize host/IP for LAN agent URL (no scheme, path, or credentials).
     */
    public function normalizeAgentHost(mixed $value): ?string
    {
        $host = trim(is_string($value) ? $value : (string) $value);
        if ($host === '') {
            return null;
        }

        $host = preg_replace('#^https?://#i', '', $host) ?? $host;
        $host = explode('/', $host, 2)[0];
        $host = explode('?', $host, 2)[0];
        $host = trim($host);

        if ($host === '' || str_contains($host, '@') || str_contains($host, ' ')) {
            return null;
        }

        // Strip accidental port from host field (port is separate).
        if (preg_match('/^(\[[^\]]+\]|[^:]+):(\d+)$/', $host, $matches) === 1) {
            $host = $matches[1];
        }

        if (strlen($host) > 255) {
            return null;
        }

        return $host;
    }

    public function agentBaseUrl(Kiosk $kiosk): ?string
    {
        $port = (int) ($kiosk->print_agent_port ?: self::DEFAULT_PORT);
        $port = max(1024, min(65535, $port));
        $mode = $this->normalizeListenMode($kiosk->print_agent_listen_mode ?? self::LISTEN_LOCAL);

        if ($mode === self::LISTEN_LOCAL) {
            return 'http://127.0.0.1:'.$port;
        }

        $host = $this->normalizeAgentHost($kiosk->print_agent_host);
        if ($host === null) {
            return null;
        }

        return 'http://'.$host.':'.$port;
    }

    /**
     * @param  array<string, mixed>  $ticketPayload
     * @return array{grant: array<string, mixed>, signature: string, agentUrl: string}|null
     */
    public function grantPrintTicket(Kiosk $kiosk, string $jobId, array $ticketPayload): ?array
    {
        if (! $kiosk->print_enabled
            || KioskPrintMethod::normalize($kiosk->print_method) !== KioskPrintMethod::Agent
            || ! $this->hasPairing($kiosk)) {
            return null;
        }

        $printer = trim((string) $kiosk->print_printer_name);
        if ($printer === '') {
            return null;
        }

        $agentUrl = $this->agentBaseUrl($kiosk);
        if ($agentUrl === null) {
            return null;
        }

        $secret = $this->decryptSecret($kiosk);
        if ($secret === null) {
            return null;
        }

        $grant = [
            'v' => 1,
            'action' => 'print_ticket',
            'jobId' => $jobId,
            'printer' => $printer,
            'paperWidth' => $this->normalizePaperWidth($kiosk->print_paper_width),
            'autoCut' => (bool) $kiosk->print_auto_cut,
            'ticket' => $ticketPayload,
            'exp' => now()->addSeconds(self::GRANT_TTL_SECONDS)->getTimestamp(),
            'nonce' => Str::lower(Str::random(24)),
        ];

        return [
            'grant' => $grant,
            'signature' => $this->sign($grant, $secret),
            'agentUrl' => $agentUrl,
        ];
    }

    /**
     * @return array{grant: array<string, mixed>, signature: string, agentUrl: string}|null
     */
    public function grantPrintTest(Kiosk $kiosk, string $clinicName, string $unitName): ?array
    {
        if (! $this->hasPairing($kiosk)) {
            return null;
        }

        $printer = trim((string) $kiosk->print_printer_name);
        if ($printer === '') {
            return null;
        }

        $agentUrl = $this->agentBaseUrl($kiosk);
        if ($agentUrl === null) {
            return null;
        }

        $secret = $this->decryptSecret($kiosk);
        if ($secret === null) {
            return null;
        }

        $totemLabel = 'Totem: '.($kiosk->name !== '' ? $kiosk->name : ($unitName !== '' ? $unitName : 'Totem'));

        $grant = [
            'v' => 1,
            'action' => 'print_test',
            'jobId' => KioskPrintPayload::jobIdForTest((int) $kiosk->id),
            'printer' => $printer,
            'paperWidth' => $this->normalizePaperWidth($kiosk->print_paper_width),
            'autoCut' => (bool) $kiosk->print_auto_cut,
            'ticket' => KioskPrintPayload::forTicket(
                displayCode: '',
                typeLabel: 'TESTE DE IMPRESSÃO',
                clinicName: $clinicName,
                unitName: $totemLabel,
                issuedAtLabel: now()->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                message: 'Impressora: '.$printer."\nConfiguração de impressão funcionando.",
            ),
            'exp' => now()->addSeconds(self::GRANT_TTL_SECONDS)->getTimestamp(),
            'nonce' => Str::lower(Str::random(24)),
        ];

        return [
            'grant' => $grant,
            'signature' => $this->sign($grant, $secret),
            'agentUrl' => $agentUrl,
        ];
    }

    /**
     * @return array{grant: array<string, mixed>, signature: string, agentUrl: string}|null
     */
    public function grantListPrinters(Kiosk $kiosk): ?array
    {
        if (! $this->hasPairing($kiosk)) {
            return null;
        }

        $agentUrl = $this->agentBaseUrl($kiosk);
        if ($agentUrl === null) {
            return null;
        }

        $secret = $this->decryptSecret($kiosk);
        if ($secret === null) {
            return null;
        }

        $grant = [
            'v' => 1,
            'action' => 'list_printers',
            'jobId' => 'list-printers-'.$kiosk->id.'-'.Str::lower(Str::random(12)),
            'exp' => now()->addSeconds(self::GRANT_TTL_SECONDS)->getTimestamp(),
            'nonce' => Str::lower(Str::random(24)),
        ];

        return [
            'grant' => $grant,
            'signature' => $this->sign($grant, $secret),
            'agentUrl' => $agentUrl,
        ];
    }

    /**
     * @param  array<string, mixed>  $grant
     */
    public function sign(array $grant, string $secret): string
    {
        return hash_hmac('sha256', $this->canonicalJson($grant), $secret);
    }

    /**
     * @param  array<string, mixed>  $grant
     */
    public function verify(array $grant, string $signature, string $secret): bool
    {
        $expected = $this->sign($grant, $secret);

        return hash_equals($expected, $signature);
    }

    public function decryptSecret(Kiosk $kiosk): ?string
    {
        if (! filled($kiosk->print_agent_secret_encrypted)) {
            return null;
        }

        try {
            $secret = Crypt::decryptString((string) $kiosk->print_agent_secret_encrypted);
        } catch (DecryptException) {
            return null;
        }

        return is_string($secret) && strlen($secret) >= 32 ? $secret : null;
    }

    public function normalizePaperWidth(mixed $value): string
    {
        $width = is_string($value) ? $value : (string) $value;

        return in_array($width, self::PAPER_WIDTHS, true) ? $width : '80';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function canonicalJson(array $payload): string
    {
        return json_encode($this->sortRecursive($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sortRecursive(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $isList = array_is_list($value);
                $payload[$key] = $isList
                    ? array_map(fn ($item) => is_array($item) ? $this->sortRecursive($item) : $item, $value)
                    : $this->sortRecursive($value);
            }
        }

        ksort($payload);

        return $payload;
    }
}
