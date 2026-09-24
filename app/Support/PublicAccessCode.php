<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Short public access codes for TV panels and kiosks.
 *
 * Format examples: TV-A7K4P9M2XQ , TOT-8M3KQ2H7NW
 *
 * Entropy: 10 characters from a 32-symbol alphabet (no I/O/0/1)
 * → 32^10 ≈ 1.13×10^15 possibilities (~50 bits), suitable for rate-limited
 * public endpoints while keeping URLs short.
 *
 * The long public_token remains the internal Livewire/credential fallback.
 */
final class PublicAccessCode
{
    public const PANEL_PREFIX = 'TV-';

    public const KIOSK_PREFIX = 'TOT-';

    public const BODY_LENGTH = 10;

    /** Crockford-like alphabet without visually ambiguous characters. */
    public const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public const MAX_GENERATION_ATTEMPTS = 16;

    public static function generatePanelCode(): string
    {
        return self::generate(self::PANEL_PREFIX);
    }

    public static function generateKioskCode(): string
    {
        return self::generate(self::KIOSK_PREFIX);
    }

    public static function generate(string $prefix): string
    {
        $alphabet = self::ALPHABET;
        $maxIndex = strlen($alphabet) - 1;
        $body = '';

        for ($i = 0; $i < self::BODY_LENGTH; $i++) {
            $body .= $alphabet[random_int(0, $maxIndex)];
        }

        return $prefix.$body;
    }

    public static function isPanelCode(string $value): bool
    {
        return (bool) preg_match('/^TV-[A-HJ-NP-Z2-9]{'.self::BODY_LENGTH.'}$/', $value);
    }

    public static function isKioskCode(string $value): bool
    {
        return (bool) preg_match('/^TOT-[A-HJ-NP-Z2-9]{'.self::BODY_LENGTH.'}$/', $value);
    }

    public static function isLegacyToken(string $value): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9]{32,64}$/', $value);
    }

    /**
     * Route constraint: short code or legacy long token.
     */
    public static function panelRoutePattern(): string
    {
        return 'TV-[A-HJ-NP-Z2-9]{'.self::BODY_LENGTH.'}|[A-Za-z0-9]{32,64}';
    }

    public static function kioskRoutePattern(): string
    {
        return 'TOT-[A-HJ-NP-Z2-9]{'.self::BODY_LENGTH.'}|[A-Za-z0-9]{32,64}';
    }

    /**
     * @param  callable(string): void  $persist  Receives a candidate code and must insert/update (throws QueryException on unique collision).
     */
    public static function allocateUnique(string $prefix, callable $persist): string
    {
        $lastException = null;

        for ($attempt = 0; $attempt < self::MAX_GENERATION_ATTEMPTS; $attempt++) {
            $code = self::generate($prefix);

            try {
                $persist($code);

                return $code;
            } catch (QueryException $exception) {
                $lastException = $exception;

                if (! self::isUniqueConstraintViolation($exception)) {
                    throw $exception;
                }
            }
        }

        throw new RuntimeException(
            'Não foi possível gerar um código público único após várias tentativas.',
            previous: $lastException,
        );
    }

    /**
     * Generate a code not already present in the given table/column.
     */
    public static function generateUniqueForTable(string $prefix, string $table, string $column = 'public_code'): string
    {
        for ($attempt = 0; $attempt < self::MAX_GENERATION_ATTEMPTS; $attempt++) {
            $code = self::generate($prefix);

            $exists = DB::table($table)->where($column, $code)->exists();
            if (! $exists) {
                return $code;
            }
        }

        throw new RuntimeException('Não foi possível gerar um código público único após várias tentativas.');
    }

    private static function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $message = strtolower($exception->getMessage());

        return $sqlState === '23000'
            || $driverCode === 1062
            || str_contains($message, 'unique')
            || str_contains($message, 'duplicate');
    }
}
