<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\ClinicSetting;
use App\Support\ClinicSettingCatalog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClinicBranding
{
    public function __construct(private ClinicSettings $settings) {}

    /**
     * @return array{
     *     display_name: string,
     *     slogan: string,
     *     primary_color: string,
     *     accent_color: string,
     *     main_logo_url: ?string,
     *     tv_logo_url: ?string,
     *     kiosk_logo_url: ?string,
     *     has_main_logo: bool,
     *     has_tv_logo: bool,
     *     has_kiosk_logo: bool,
     *     on_primary_color: string
     * }
     */
    public function resolve(Clinic $clinic): array
    {
        $bag = $this->settings->all($clinic);
        $displayName = trim((string) ($bag['display_name'] ?? ''));
        if ($displayName === '') {
            $displayName = $clinic->name;
        }

        $mainPath = $bag['main_logo_path'] ?? null;
        $tvPath = $bag['tv_logo_path'] ?? null;
        $kioskPath = $bag['kiosk_logo_path'] ?? null;

        $mainUrl = $this->urlForPath(is_string($mainPath) ? $mainPath : null);
        $tvUrl = $this->urlForPath(is_string($tvPath) ? $tvPath : null) ?? $mainUrl;
        $kioskUrl = $this->urlForPath(is_string($kioskPath) ? $kioskPath : null) ?? $mainUrl;

        $primary = (string) ($bag['primary_color'] ?? ClinicSettingCatalog::DEFAULT_PRIMARY_COLOR);
        $accent = (string) ($bag['accent_color'] ?? ClinicSettingCatalog::DEFAULT_ACCENT_COLOR);

        return [
            'display_name' => $displayName,
            'slogan' => trim((string) ($bag['slogan'] ?? '')),
            'primary_color' => $primary,
            'accent_color' => $accent,
            'main_logo_url' => $mainUrl,
            'tv_logo_url' => $tvUrl,
            'kiosk_logo_url' => $kioskUrl,
            'has_main_logo' => $mainUrl !== null,
            'has_tv_logo' => $this->urlForPath(is_string($tvPath) ? $tvPath : null) !== null,
            'has_kiosk_logo' => $this->urlForPath(is_string($kioskPath) ? $kioskPath : null) !== null,
            'on_primary_color' => $this->contrastingTextColor($primary),
        ];
    }

    public function logoForTv(Clinic $clinic): ?string
    {
        return $this->resolve($clinic)['tv_logo_url'];
    }

    public function logoForKiosk(Clinic $clinic): ?string
    {
        return $this->resolve($clinic)['kiosk_logo_url'];
    }

    /**
     * Main clinic logo URL for browser thermal receipts (Identidade visual → Logo principal).
     */
    public function logoForPrintReceipt(Clinic $clinic): ?string
    {
        return $this->resolve($clinic)['main_logo_url'];
    }

    public function displayName(Clinic $clinic): string
    {
        return $this->resolve($clinic)['display_name'];
    }

    public function storeLogo(Clinic $clinic, string $key, UploadedFile $file, string $errorKey = 'logo'): string
    {
        if (! in_array($key, ['main_logo_path', 'tv_logo_path', 'kiosk_logo_path'], true)) {
            throw new \InvalidArgumentException('Chave de logo inválida.');
        }

        $this->assertValidLogo($file, $errorKey);

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'png');
        if (! in_array($extension, ClinicSetting::LOGO_EXTENSIONS, true)) {
            $extension = match ($file->getMimeType()) {
                'image/jpeg' => 'jpg',
                'image/webp' => 'webp',
                default => 'png',
            };
        }

        $directory = ClinicSetting::LOGO_DIRECTORY.'/'.$clinic->id;
        $filename = Str::lower(Str::random(40)).'.'.$extension;
        $previous = $this->settings->get($clinic, $key);
        $previous = is_string($previous) && $previous !== '' ? $previous : null;

        return DB::transaction(function () use ($clinic, $key, $file, $directory, $filename, $previous, $errorKey): string {
            $stored = $file->storeAs($directory, $filename, ClinicSetting::DISK);

            if (! is_string($stored) || $stored === '') {
                throw ValidationException::withMessages([
                    $errorKey => 'Não foi possível armazenar a logo.',
                ]);
            }

            $this->settings->putPath($clinic, $key, $stored);

            if ($previous !== null && $previous !== $stored && Storage::disk(ClinicSetting::DISK)->exists($previous)) {
                Storage::disk(ClinicSetting::DISK)->delete($previous);
            }

            return $stored;
        });
    }

    public function removeLogo(Clinic $clinic, string $key): void
    {
        if (! in_array($key, ['main_logo_path', 'tv_logo_path', 'kiosk_logo_path'], true)) {
            throw new \InvalidArgumentException('Chave de logo inválida.');
        }

        $previous = $this->settings->get($clinic, $key);
        $previous = is_string($previous) && $previous !== '' ? $previous : null;

        $this->settings->putPath($clinic, $key, null);

        if ($previous !== null && Storage::disk(ClinicSetting::DISK)->exists($previous)) {
            Storage::disk(ClinicSetting::DISK)->delete($previous);
        }
    }

    public function urlForPath(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (! Storage::disk(ClinicSetting::DISK)->exists($path)) {
            return null;
        }

        return Storage::disk(ClinicSetting::DISK)->url($path);
    }

    public function contrastingTextColor(string $hex): string
    {
        $hex = ltrim(strtoupper($hex), '#');
        if (strlen($hex) !== 6) {
            return '#ffffff';
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $luminance = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;

        return $luminance > 0.55 ? '#10233a' : '#ffffff';
    }

    private function assertValidLogo(UploadedFile $file, string $errorKey = 'logo'): void
    {
        $mime = (string) $file->getMimeType();
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension()));

        if ($extension === 'svg' || str_contains($mime, 'svg')) {
            throw ValidationException::withMessages([
                $errorKey => 'SVG não é permitido. Use PNG, JPEG ou WebP.',
            ]);
        }

        if (! in_array($mime, ClinicSetting::LOGO_MIME_TYPES, true)) {
            throw ValidationException::withMessages([
                $errorKey => 'O arquivo deve ser uma imagem PNG, JPEG ou WebP.',
            ]);
        }

        if (! in_array($extension, ClinicSetting::LOGO_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                $errorKey => 'O arquivo deve ser uma imagem PNG, JPEG ou WebP.',
            ]);
        }

        if ($file->getSize() > ClinicSetting::LOGO_MAX_KILOBYTES * 1024) {
            throw ValidationException::withMessages([
                $errorKey => 'A imagem deve possuir no máximo 2 MB.',
            ]);
        }
    }
}
