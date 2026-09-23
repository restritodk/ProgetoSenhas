<?php

namespace App\Support;

use App\Models\Clinic;
use App\Services\ClinicBranding;
use App\Services\ClinicSettings;

readonly class TvPresentation
{
    /**
     * @param  array{title: string, text: string}  $footer1
     * @param  array{title: string, text: string}  $footer2
     * @param  array{title: string, text: string}  $footer3
     */
    public function __construct(
        public string $displayName,
        public string $slogan,
        public ?string $logoUrl,
        public string $logoBackground,
        public string $logoBackgroundColor,
        public string $primaryColor,
        public string $accentColor,
        public string $onPrimaryColor,
        public bool $showDate,
        public bool $showTime,
        public bool $showConnectionStatus,
        public bool $showTicketType,
        public int $recentCallsCount,
        public bool $footerEnabled,
        public array $footer1,
        public array $footer2,
        public array $footer3,
        public bool $chimeEnabled,
        public bool $speechEnabled,
        public bool $speakTicketType,
        public bool $speakDesk,
        public int $chimeVolume,
    ) {}

    public static function forClinic(Clinic $clinic, ClinicSettings $settings, ClinicBranding $branding): self
    {
        $bag = $settings->all($clinic);
        $brand = $branding->resolve($clinic);
        $surface = LogoSurface::fromBag($bag, 'tv');

        return new self(
            displayName: $brand['display_name'],
            slogan: $brand['slogan'],
            logoUrl: $brand['tv_logo_url'],
            logoBackground: $surface['mode'],
            logoBackgroundColor: $surface['color'],
            primaryColor: $brand['primary_color'],
            accentColor: $brand['accent_color'],
            onPrimaryColor: $brand['on_primary_color'],
            showDate: (bool) $bag['tv_show_date'],
            showTime: (bool) $bag['tv_show_time'],
            showConnectionStatus: (bool) $bag['tv_show_connection_status'],
            showTicketType: (bool) $bag['tv_show_ticket_type'],
            recentCallsCount: (int) $bag['tv_recent_calls_count'],
            footerEnabled: (bool) $bag['tv_footer_enabled'],
            footer1: [
                'title' => (string) $bag['tv_footer_1_title'],
                'text' => (string) $bag['tv_footer_1_text'],
            ],
            footer2: [
                'title' => (string) $bag['tv_footer_2_title'],
                'text' => (string) $bag['tv_footer_2_text'],
            ],
            footer3: [
                'title' => (string) $bag['tv_footer_3_title'],
                'text' => (string) $bag['tv_footer_3_text'],
            ],
            chimeEnabled: (bool) $bag['tv_chime_enabled'],
            speechEnabled: (bool) $bag['tv_speech_enabled'],
            speakTicketType: (bool) $bag['tv_speak_ticket_type'],
            speakDesk: (bool) $bag['tv_speak_desk'],
            chimeVolume: (int) $bag['tv_chime_volume'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'display_name' => $this->displayName,
            'slogan' => $this->slogan,
            'logo_url' => $this->logoUrl,
            'logo_background' => $this->logoBackground,
            'logo_background_color' => $this->logoBackgroundColor,
            'primary_color' => $this->primaryColor,
            'accent_color' => $this->accentColor,
            'on_primary_color' => $this->onPrimaryColor,
            'show_date' => $this->showDate,
            'show_time' => $this->showTime,
            'show_connection_status' => $this->showConnectionStatus,
            'show_ticket_type' => $this->showTicketType,
            'recent_calls_count' => $this->recentCallsCount,
            'footer_enabled' => $this->footerEnabled,
            'footer_1' => $this->footer1,
            'footer_2' => $this->footer2,
            'footer_3' => $this->footer3,
            'chime_enabled' => $this->chimeEnabled,
            'speech_enabled' => $this->speechEnabled,
            'speak_ticket_type' => $this->speakTicketType,
            'speak_desk' => $this->speakDesk,
            'chime_volume' => $this->chimeVolume,
        ];
    }
}
