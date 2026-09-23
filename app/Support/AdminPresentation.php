<?php

namespace App\Support;

use App\Models\Clinic;
use App\Services\ClinicBranding;
use App\Services\ClinicSettings;

readonly class AdminPresentation
{
    public function __construct(
        public ?string $logoUrl,
        public string $logoBackground,
        public string $logoBackgroundColor,
    ) {}

    public static function forClinic(Clinic $clinic, ClinicSettings $settings, ClinicBranding $branding): self
    {
        $bag = $settings->all($clinic);
        $brand = $branding->resolve($clinic);
        $surface = LogoSurface::fromBag($bag, 'main');

        return new self(
            logoUrl: $brand['main_logo_url'],
            logoBackground: $surface['mode'],
            logoBackgroundColor: $surface['color'],
        );
    }

    /**
     * @return array{logo_url: ?string, logo_background: string, logo_background_color: string}
     */
    public function toArray(): array
    {
        return [
            'logo_url' => $this->logoUrl,
            'logo_background' => $this->logoBackground,
            'logo_background_color' => $this->logoBackgroundColor,
        ];
    }
}
