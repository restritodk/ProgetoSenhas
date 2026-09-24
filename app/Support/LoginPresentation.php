<?php

namespace App\Support;

use App\Models\Clinic;
use App\Services\ClinicBranding;
use App\Services\ClinicSettings;

/**
 * Guest-safe branding for the login screen.
 *
 * Clinic logos are tenant-scoped. Before authentication there is no clinic
 * context, so we only surface a configured main logo when exactly one active
 * clinic exists (single-tenant deployment). Multi-clinic installs keep the
 * global product identity without guessing a tenant.
 */
readonly class LoginPresentation
{
    public function __construct(
        public string $productName,
        public ?string $logoUrl,
        public string $logoBackground,
        public string $logoBackgroundColor,
        public bool $hasConfiguredLogo,
    ) {}

    public static function forGuest(ClinicBranding $branding, ClinicSettings $settings): self
    {
        $productName = (string) config('app.name');

        $clinics = Clinic::query()
            ->where('active', true)
            ->orderBy('id')
            ->limit(2)
            ->get();

        if ($clinics->count() !== 1) {
            return self::globalFallback($productName);
        }

        /** @var Clinic $clinic */
        $clinic = $clinics->first();
        $admin = AdminPresentation::forClinic($clinic, $settings, $branding);

        return new self(
            productName: $productName,
            logoUrl: $admin->logoUrl,
            logoBackground: $admin->logoBackground,
            logoBackgroundColor: $admin->logoBackgroundColor,
            hasConfiguredLogo: $admin->logoUrl !== null,
        );
    }

    public static function globalFallback(string $productName): self
    {
        return new self(
            productName: $productName,
            logoUrl: null,
            logoBackground: LogoSurface::NONE,
            logoBackgroundColor: LogoSurface::DEFAULT_CUSTOM_COLOR,
            hasConfiguredLogo: false,
        );
    }

    /**
     * @return array{
     *     product_name: string,
     *     logo_url: ?string,
     *     logo_background: string,
     *     logo_background_color: string,
     *     has_configured_logo: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'product_name' => $this->productName,
            'logo_url' => $this->logoUrl,
            'logo_background' => $this->logoBackground,
            'logo_background_color' => $this->logoBackgroundColor,
            'has_configured_logo' => $this->hasConfiguredLogo,
        ];
    }
}
