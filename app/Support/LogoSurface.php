<?php

namespace App\Support;

/**
 * Configurable logo surface/background modes for TV, Totem and principal contexts.
 */
final class LogoSurface
{
    public const NONE = 'transparent';

    public const LIGHT = 'light';

    public const DARK = 'dark';

    public const CUSTOM = 'custom';

    public const DEFAULT_CUSTOM_COLOR = '#FFFFFF';

    /**
     * @return list<string>
     */
    public static function modes(): array
    {
        return [
            self::NONE,
            self::LIGHT,
            self::DARK,
            self::CUSTOM,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::NONE => 'Sem fundo',
            self::LIGHT => 'Fundo claro',
            self::DARK => 'Fundo escuro',
            self::CUSTOM => 'Cor personalizada',
        ];
    }

    public static function normalizeMode(mixed $mode): string
    {
        $value = is_string($mode) ? strtolower(trim($mode)) : '';

        return in_array($value, self::modes(), true) ? $value : self::NONE;
    }

    public static function normalizeColor(mixed $color): string
    {
        if (! is_string($color)) {
            return self::DEFAULT_CUSTOM_COLOR;
        }

        $hex = strtoupper(trim($color));

        return preg_match('/^#[0-9A-F]{6}$/', $hex) === 1
            ? $hex
            : self::DEFAULT_CUSTOM_COLOR;
    }

    /**
     * @param  array<string, mixed>  $bag
     * @return array{mode: string, color: string}
     */
    public static function fromBag(array $bag, string $context): array
    {
        $prefix = match ($context) {
            'tv' => 'tv_logo_background',
            'kiosk' => 'kiosk_logo_background',
            'main' => 'main_logo_background',
            default => throw new \InvalidArgumentException("Unknown logo surface context [{$context}]."),
        };

        return [
            'mode' => self::normalizeMode($bag[$prefix] ?? self::NONE),
            'color' => self::normalizeColor($bag[$prefix.'_color'] ?? self::DEFAULT_CUSTOM_COLOR),
        ];
    }

    /**
     * Background CSS color for the wrap, or null when transparent/none.
     */
    public static function cssBackground(string $mode, string $customColor): ?string
    {
        return match (self::normalizeMode($mode)) {
            self::LIGHT => 'rgba(255, 255, 255, 0.94)',
            self::DARK => 'rgba(8, 18, 32, 0.72)',
            self::CUSTOM => self::normalizeColor($customColor),
            default => null,
        };
    }
}
