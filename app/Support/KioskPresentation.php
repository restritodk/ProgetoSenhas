<?php

namespace App\Support;

use App\Models\Clinic;
use App\Services\ClinicBranding;
use App\Services\ClinicSettings;

readonly class KioskPresentation
{
    public function __construct(
        public string $displayName,
        public string $slogan,
        public ?string $logoUrl,
        public string $logoBackground,
        public string $logoBackgroundColor,
        public string $primaryColor,
        public string $accentColor,
        public string $onPrimaryColor,
        public string $title,
        public string $subtitle,
        public string $instructionText,
        public string $issuedMessage,
        public string $finishButtonText,
        public int $autoReturnSeconds,
    ) {}

    public static function forClinic(Clinic $clinic, ClinicSettings $settings, ClinicBranding $branding): self
    {
        $bag = $settings->all($clinic);
        $brand = $branding->resolve($clinic);
        $surface = LogoSurface::fromBag($bag, 'kiosk');

        return new self(
            displayName: $brand['display_name'],
            slogan: $brand['slogan'],
            logoUrl: $brand['kiosk_logo_url'],
            logoBackground: $surface['mode'],
            logoBackgroundColor: $surface['color'],
            primaryColor: $brand['primary_color'],
            accentColor: $brand['accent_color'],
            onPrimaryColor: $brand['on_primary_color'],
            title: (string) $bag['kiosk_title'],
            subtitle: (string) $bag['kiosk_subtitle'],
            instructionText: (string) $bag['kiosk_instruction_text'],
            issuedMessage: (string) $bag['kiosk_issued_message'],
            finishButtonText: (string) $bag['kiosk_finish_button_text'],
            autoReturnSeconds: (int) $bag['kiosk_auto_return_seconds'],
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
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'instruction_text' => $this->instructionText,
            'issued_message' => $this->issuedMessage,
            'finish_button_text' => $this->finishButtonText,
            'auto_return_seconds' => $this->autoReturnSeconds,
        ];
    }
}
