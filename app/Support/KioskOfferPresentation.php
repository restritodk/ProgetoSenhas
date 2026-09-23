<?php

namespace App\Support;

use App\Models\SectorTicketType;
use App\Models\UnitTicketType;

/**
 * Presentation helpers for public kiosk ticket-type cards (icons, copy, visual tone).
 * Does not alter issuance domain rules.
 */
final class KioskOfferPresentation
{
    public const VARIANT_STANDARD = 'standard';

    public const VARIANT_PRIORITY = 'priority';

    public const VARIANT_URGENT = 'urgent';

    public const VARIANT_EXAM = 'exam';

    public const VARIANT_RETURN = 'return';

    public static function variant(UnitTicketType|SectorTicketType $offer): string
    {
        $haystack = mb_strtolower(trim(implode(' ', array_filter([
            $offer->publicLabel(),
            $offer->ticketType?->name,
            $offer->ticketType?->prefix,
        ]))));

        if (self::matches($haystack, ['prefer', 'priorit', 'pcd', 'gestante', 'idoso', 'especial'])) {
            return self::VARIANT_PRIORITY;
        }

        if (self::matches($haystack, ['emerg', 'urgen', 'urgên'])) {
            return self::VARIANT_URGENT;
        }

        if (self::matches($haystack, ['exame', 'lab', 'raio', 'imagem'])) {
            return self::VARIANT_EXAM;
        }

        if (self::matches($haystack, ['retor'])) {
            return self::VARIANT_RETURN;
        }

        return self::VARIANT_STANDARD;
    }

    public static function description(UnitTicketType|SectorTicketType $offer): string
    {
        return match (self::variant($offer)) {
            self::VARIANT_PRIORITY => 'Idosos, gestantes, pessoas com deficiência e demais prioridades',
            self::VARIANT_URGENT => 'Casos urgentes que precisam de atendimento imediato',
            self::VARIANT_EXAM => 'Exames, coletas e procedimentos agendados',
            self::VARIANT_RETURN => 'Retornos e acompanhamentos já iniciados',
            default => 'Toque para retirar sua senha de atendimento',
        };
    }

    public static function icon(UnitTicketType|SectorTicketType $offer): string
    {
        return match (self::variant($offer)) {
            self::VARIANT_PRIORITY => 'priority',
            self::VARIANT_URGENT => 'urgent',
            self::VARIANT_EXAM => 'exam',
            self::VARIANT_RETURN => 'return',
            default => 'person',
        };
    }

    public static function isEmphasized(UnitTicketType|SectorTicketType $offer): bool
    {
        $variant = self::variant($offer);

        return $variant === self::VARIANT_PRIORITY || $variant === self::VARIANT_URGENT;
    }

    /**
     * @param  list<string>  $needles
     */
    private static function matches(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
