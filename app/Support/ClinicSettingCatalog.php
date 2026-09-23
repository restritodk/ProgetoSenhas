<?php

namespace App\Support;

/**
 * Known clinic settings contract: keys, types, defaults and validation bounds.
 *
 * @phpstan-type SettingDefinition array{
 *     type: 'string'|'bool'|'int'|'color'|'path'|'enum',
 *     default: mixed,
 *     max?: int,
 *     min?: int,
 *     nullable?: bool,
 *     options?: list<string>
 * }
 */
class ClinicSettingCatalog
{
    public const DEFAULT_PRIMARY_COLOR = '#1e3a5f';

    public const DEFAULT_ACCENT_COLOR = '#2563eb';

    /** @deprecated Use LogoSurface::NONE */
    public const TV_LOGO_BACKGROUND_TRANSPARENT = LogoSurface::NONE;

    /** @deprecated Use LogoSurface::LIGHT */
    public const TV_LOGO_BACKGROUND_LIGHT = LogoSurface::LIGHT;

    /** @deprecated Use LogoSurface::DARK */
    public const TV_LOGO_BACKGROUND_DARK = LogoSurface::DARK;

    /**
     * @return list<string>
     */
    public static function tvLogoBackgroundOptions(): array
    {
        return LogoSurface::modes();
    }

    /**
     * @return array<string, SettingDefinition>
     */
    public static function definitions(): array
    {
        return [
            'display_name' => ['type' => 'string', 'default' => '', 'max' => 120, 'nullable' => true],
            'slogan' => ['type' => 'string', 'default' => '', 'max' => 160, 'nullable' => true],

            'main_logo_path' => ['type' => 'path', 'default' => null, 'nullable' => true],
            'tv_logo_path' => ['type' => 'path', 'default' => null, 'nullable' => true],
            'kiosk_logo_path' => ['type' => 'path', 'default' => null, 'nullable' => true],
            'primary_color' => ['type' => 'color', 'default' => self::DEFAULT_PRIMARY_COLOR],
            'accent_color' => ['type' => 'color', 'default' => self::DEFAULT_ACCENT_COLOR],

            'main_logo_background' => [
                'type' => 'enum',
                'default' => LogoSurface::NONE,
                'options' => LogoSurface::modes(),
            ],
            'main_logo_background_color' => [
                'type' => 'color',
                'default' => LogoSurface::DEFAULT_CUSTOM_COLOR,
            ],
            'tv_logo_background' => [
                'type' => 'enum',
                'default' => LogoSurface::NONE,
                'options' => LogoSurface::modes(),
            ],
            'tv_logo_background_color' => [
                'type' => 'color',
                'default' => LogoSurface::DEFAULT_CUSTOM_COLOR,
            ],
            'kiosk_logo_background' => [
                'type' => 'enum',
                'default' => LogoSurface::NONE,
                'options' => LogoSurface::modes(),
            ],
            'kiosk_logo_background_color' => [
                'type' => 'color',
                'default' => LogoSurface::DEFAULT_CUSTOM_COLOR,
            ],

            'tv_show_date' => ['type' => 'bool', 'default' => true],
            'tv_show_time' => ['type' => 'bool', 'default' => true],
            'tv_show_connection_status' => ['type' => 'bool', 'default' => true],
            'tv_show_ticket_type' => ['type' => 'bool', 'default' => true],
            'tv_recent_calls_count' => ['type' => 'int', 'default' => 5, 'min' => 3, 'max' => 8],
            'tv_footer_enabled' => ['type' => 'bool', 'default' => true],
            'tv_footer_1_title' => ['type' => 'string', 'default' => 'Acompanhe sua senha', 'max' => 60],
            'tv_footer_1_text' => ['type' => 'string', 'default' => 'Fique atento ao painel.', 'max' => 120],
            'tv_footer_2_title' => ['type' => 'string', 'default' => 'Dirija-se à mesa', 'max' => 60],
            'tv_footer_2_text' => ['type' => 'string', 'default' => 'Quando sua senha for chamada.', 'max' => 120],
            'tv_footer_3_title' => ['type' => 'string', 'default' => 'Aguarde sua vez', 'max' => 60],
            'tv_footer_3_text' => ['type' => 'string', 'default' => 'Obrigado pela compreensão.', 'max' => 120],
            'tv_chime_enabled' => ['type' => 'bool', 'default' => true],
            'tv_speech_enabled' => ['type' => 'bool', 'default' => true],
            'tv_speak_ticket_type' => ['type' => 'bool', 'default' => true],
            'tv_speak_desk' => ['type' => 'bool', 'default' => true],
            'tv_chime_volume' => ['type' => 'int', 'default' => 70, 'min' => 0, 'max' => 100],

            'kiosk_title' => ['type' => 'string', 'default' => 'Retire sua senha', 'max' => 80],
            'kiosk_subtitle' => ['type' => 'string', 'default' => 'Selecione o tipo de atendimento', 'max' => 120],
            'kiosk_instruction_text' => ['type' => 'string', 'default' => 'Toque para retirar', 'max' => 80],
            'kiosk_issued_message' => ['type' => 'string', 'default' => 'Aguarde sua chamada no painel.', 'max' => 160],
            'kiosk_finish_button_text' => ['type' => 'string', 'default' => 'Finalizar', 'max' => 40],
            'kiosk_auto_return_seconds' => ['type' => 'int', 'default' => 3, 'min' => 3, 'max' => 60],
        ];
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::definitions());
    }

    /**
     * @return SettingDefinition
     */
    public static function definition(string $key): array
    {
        if (! self::has($key)) {
            throw new \InvalidArgumentException("Unknown clinic setting key [{$key}].");
        }

        return self::definitions()[$key];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        $defaults = [];

        foreach (self::definitions() as $key => $definition) {
            $defaults[$key] = $definition['default'];
        }

        return $defaults;
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::definitions());
    }

    public static function isLogoKey(string $key): bool
    {
        return in_array($key, ['main_logo_path', 'tv_logo_path', 'kiosk_logo_path'], true);
    }
}
