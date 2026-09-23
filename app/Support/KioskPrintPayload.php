<?php

namespace App\Support;

/**
 * Typed payload for kiosk thermal ticket printing (no PII, no secrets).
 */
final class KioskPrintPayload
{
    /**
     * @return array{
     *     displayCode: string,
     *     typeLabel: string,
     *     clinicName: string,
     *     unitName: string,
     *     issuedAtLabel: string,
     *     message: string
     * }
     */
    public static function forTicket(
        string $displayCode,
        string $typeLabel,
        string $clinicName,
        string $unitName,
        string $issuedAtLabel,
        string $message = 'Aguarde sua senha ser chamada no painel.',
    ): array {
        return [
            'displayCode' => mb_substr(trim($displayCode), 0, 32),
            'typeLabel' => mb_substr(trim($typeLabel), 0, 80),
            'clinicName' => mb_substr(trim($clinicName), 0, 120),
            'unitName' => mb_substr(trim($unitName), 0, 120),
            'issuedAtLabel' => mb_substr(trim($issuedAtLabel), 0, 40),
            'message' => mb_substr(trim($message), 0, 160),
        ];
    }

    /**
     * Stable job id for the first automatic print of a ticket.
     * Retries must reuse this value. Explicit reprint creates a new id.
     */
    public static function jobIdForTicket(int $ticketId): string
    {
        return 'ticket-print-'.$ticketId;
    }

    public static function jobIdForReprint(int $ticketId): string
    {
        return 'ticket-reprint-'.$ticketId.'-'.bin2hex(random_bytes(8));
    }

    public static function jobIdForTest(int $kioskId): string
    {
        return 'print-test-'.$kioskId.'-'.bin2hex(random_bytes(8));
    }
}
