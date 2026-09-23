<?php

namespace App\Livewire;

use App\Models\Clinic;
use App\Models\ClinicSetting;
use App\Models\DisplayPanel;
use App\Models\Kiosk;
use App\Services\ClinicBranding;
use App\Services\ClinicSettings;
use App\Support\ClinicSettingCatalog;
use App\Support\LogoSurface;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

class ClinicSettingsManager extends Component
{
    use WithFileUploads;

    public string $activeTab = 'geral';

    public string $statusMessage = '';

    public string $display_name = '';

    public string $slogan = '';

    public string $primary_color = ClinicSettingCatalog::DEFAULT_PRIMARY_COLOR;

    public string $accent_color = ClinicSettingCatalog::DEFAULT_ACCENT_COLOR;

    public string $main_logo_background = LogoSurface::NONE;

    public string $main_logo_background_color = LogoSurface::DEFAULT_CUSTOM_COLOR;

    public string $tv_logo_background = LogoSurface::NONE;

    public string $tv_logo_background_color = LogoSurface::DEFAULT_CUSTOM_COLOR;

    public string $kiosk_logo_background = LogoSurface::NONE;

    public string $kiosk_logo_background_color = LogoSurface::DEFAULT_CUSTOM_COLOR;

    public $mainLogoUpload = null;

    public $tvLogoUpload = null;

    public $kioskLogoUpload = null;

    public bool $tv_show_date = true;

    public bool $tv_show_time = true;

    public bool $tv_show_connection_status = true;

    public bool $tv_show_ticket_type = true;

    public int $tv_recent_calls_count = 5;

    public bool $tv_footer_enabled = true;

    public string $tv_footer_1_title = '';

    public string $tv_footer_1_text = '';

    public string $tv_footer_2_title = '';

    public string $tv_footer_2_text = '';

    public string $tv_footer_3_title = '';

    public string $tv_footer_3_text = '';

    public bool $tv_chime_enabled = true;

    public bool $tv_speech_enabled = true;

    public bool $tv_speak_ticket_type = true;

    public bool $tv_speak_desk = true;

    public int $tv_chime_volume = 70;

    public string $kiosk_title = '';

    public string $kiosk_subtitle = '';

    public string $kiosk_instruction_text = '';

    public string $kiosk_issued_message = '';

    public string $kiosk_finish_button_text = '';

    public int $kiosk_auto_return_seconds = 3;

    /** @var array<string, mixed> */
    public array $brandingPreview = [];

    public ?string $previewTvUrl = null;

    public ?string $previewKioskUrl = null;

    public function mount(ClinicSettings $settings, ClinicBranding $branding): void
    {
        $this->authorize('viewAny', ClinicSetting::class);
        $this->hydrateFromSettings($settings, $branding);
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, ['geral', 'identidade', 'tv', 'totem', 'atendimento', 'audio'], true)) {
            return;
        }

        $this->activeTab = $tab;
        $this->statusMessage = '';
    }

    public function saveGeneral(ClinicSettings $settings, ClinicBranding $branding): void
    {
        $this->authorize('manage', ClinicSetting::class);
        $clinic = $this->currentClinic();

        $settings->putMany($clinic, [
            'display_name' => $this->display_name,
            'slogan' => $this->slogan,
        ]);

        $this->hydrateFromSettings($settings, $branding);
        $this->statusMessage = 'Configurações gerais salvas.';
    }

    public function saveBranding(ClinicSettings $settings, ClinicBranding $branding): void
    {
        $this->authorize('manage', ClinicSetting::class);
        $clinic = $this->currentClinic();

        $settings->putMany($clinic, [
            'primary_color' => $this->primary_color,
            'accent_color' => $this->accent_color,
            'main_logo_background' => $this->main_logo_background,
            'main_logo_background_color' => $this->main_logo_background_color,
            'tv_logo_background' => $this->tv_logo_background,
            'tv_logo_background_color' => $this->tv_logo_background_color,
            'kiosk_logo_background' => $this->kiosk_logo_background,
            'kiosk_logo_background_color' => $this->kiosk_logo_background_color,
        ]);

        $this->hydrateFromSettings($settings, $branding);
        $this->statusMessage = 'Identidade visual salva.';
    }

    public function saveColors(ClinicSettings $settings, ClinicBranding $branding): void
    {
        $this->authorize('manage', ClinicSetting::class);
        $clinic = $this->currentClinic();

        $settings->putMany($clinic, [
            'primary_color' => $this->primary_color,
            'accent_color' => $this->accent_color,
        ]);

        $this->hydrateFromSettings($settings, $branding);
        $this->statusMessage = 'Cores salvas.';
    }

    public function saveLogoAppearance(string $context, ClinicSettings $settings, ClinicBranding $branding): void
    {
        $this->authorize('manage', ClinicSetting::class);
        $clinic = $this->currentClinic();

        $payload = match ($context) {
            'main' => [
                'main_logo_background' => $this->main_logo_background,
                'main_logo_background_color' => $this->main_logo_background_color,
            ],
            'tv' => [
                'tv_logo_background' => $this->tv_logo_background,
                'tv_logo_background_color' => $this->tv_logo_background_color,
            ],
            'kiosk' => [
                'kiosk_logo_background' => $this->kiosk_logo_background,
                'kiosk_logo_background_color' => $this->kiosk_logo_background_color,
            ],
            default => throw ValidationException::withMessages([
                'logo' => 'Contexto de logo inválido.',
            ]),
        };

        $settings->putMany($clinic, $payload);
        $this->hydrateFromSettings($settings, $branding);
        $this->statusMessage = 'Aparência da logo salva.';
    }

    public function cancelLogoUpload(string $property): void
    {
        if (! in_array($property, ['mainLogoUpload', 'tvLogoUpload', 'kioskLogoUpload'], true)) {
            return;
        }

        $this->{$property} = null;
        $this->resetValidation($property);
    }

    public function saveTv(ClinicSettings $settings, ClinicBranding $branding): void
    {
        $this->authorize('manage', ClinicSetting::class);
        $clinic = $this->currentClinic();

        $settings->putMany($clinic, [
            'tv_show_date' => $this->tv_show_date,
            'tv_show_time' => $this->tv_show_time,
            'tv_show_connection_status' => $this->tv_show_connection_status,
            'tv_show_ticket_type' => $this->tv_show_ticket_type,
            'tv_recent_calls_count' => $this->tv_recent_calls_count,
            'tv_footer_enabled' => $this->tv_footer_enabled,
            'tv_footer_1_title' => $this->tv_footer_1_title,
            'tv_footer_1_text' => $this->tv_footer_1_text,
            'tv_footer_2_title' => $this->tv_footer_2_title,
            'tv_footer_2_text' => $this->tv_footer_2_text,
            'tv_footer_3_title' => $this->tv_footer_3_title,
            'tv_footer_3_text' => $this->tv_footer_3_text,
            'tv_chime_enabled' => $this->tv_chime_enabled,
            'tv_speech_enabled' => $this->tv_speech_enabled,
            'tv_speak_ticket_type' => $this->tv_speak_ticket_type,
            'tv_speak_desk' => $this->tv_speak_desk,
            'tv_chime_volume' => $this->tv_chime_volume,
        ]);

        $this->hydrateFromSettings($settings, $branding);
        $this->statusMessage = 'Configurações do Painel da TV salvas.';
    }

    public function saveKiosk(ClinicSettings $settings, ClinicBranding $branding): void
    {
        $this->authorize('manage', ClinicSetting::class);
        $clinic = $this->currentClinic();

        $settings->putMany($clinic, [
            'kiosk_title' => $this->kiosk_title,
            'kiosk_subtitle' => $this->kiosk_subtitle,
            'kiosk_instruction_text' => $this->kiosk_instruction_text,
            'kiosk_issued_message' => $this->kiosk_issued_message,
            'kiosk_finish_button_text' => $this->kiosk_finish_button_text,
            'kiosk_auto_return_seconds' => $this->kiosk_auto_return_seconds,
        ]);

        $this->hydrateFromSettings($settings, $branding);
        $this->statusMessage = 'Configurações do Totem salvas.';
    }

    public function saveMainLogo(ClinicBranding $branding, ClinicSettings $settings): void
    {
        $this->persistLogoUpload('mainLogoUpload', 'main_logo_path', 'logo principal', $branding, $settings);
    }

    public function saveTvLogo(ClinicBranding $branding, ClinicSettings $settings): void
    {
        $this->persistLogoUpload('tvLogoUpload', 'tv_logo_path', 'logo da TV', $branding, $settings);
    }

    public function saveKioskLogo(ClinicBranding $branding, ClinicSettings $settings): void
    {
        $this->persistLogoUpload('kioskLogoUpload', 'kiosk_logo_path', 'logo do Totem', $branding, $settings);
    }

    public function removeMainLogo(ClinicBranding $branding, ClinicSettings $settings): void
    {
        $this->removeLogo('main_logo_path', $branding, $settings);
    }

    public function removeTvLogo(ClinicBranding $branding, ClinicSettings $settings): void
    {
        $this->removeLogo('tv_logo_path', $branding, $settings);
    }

    public function removeKioskLogo(ClinicBranding $branding, ClinicSettings $settings): void
    {
        $this->removeLogo('kiosk_logo_path', $branding, $settings);
    }

    private function removeLogo(string $key, ClinicBranding $branding, ClinicSettings $settings): void
    {
        $this->authorize('manage', ClinicSetting::class);
        $clinic = $this->currentClinic();
        $branding->removeLogo($clinic, $key);
        $this->hydrateFromSettings($settings, $branding);
        $this->statusMessage = 'Logo removida. Fallback aplicado.';
    }

    private function persistLogoUpload(
        string $property,
        string $settingKey,
        string $label,
        ClinicBranding $branding,
        ClinicSettings $settings,
    ): void {
        $this->authorize('manage', ClinicSetting::class);
        $clinic = $this->currentClinic();

        $this->validate(
            [
                $property => [
                    'required',
                    'image',
                    'mimetypes:image/jpeg,image/png,image/webp',
                    'max:'.ClinicSetting::LOGO_MAX_KILOBYTES,
                ],
            ],
            [
                "{$property}.required" => "Selecione uma imagem para a {$label}.",
                "{$property}.image" => 'O arquivo deve ser uma imagem PNG, JPEG ou WebP.',
                "{$property}.mimetypes" => 'O arquivo deve ser uma imagem PNG, JPEG ou WebP.',
                "{$property}.max" => 'A imagem deve possuir no máximo 2 MB.',
            ],
            [
                $property => $label,
            ],
        );

        try {
            $branding->storeLogo($clinic, $settingKey, $this->{$property}, $property);
        } catch (ValidationException $exception) {
            $messages = $exception->errors();
            if (isset($messages['logo'])) {
                $messages[$property] = $messages['logo'];
                unset($messages['logo']);
            }

            throw ValidationException::withMessages($messages);
        }

        $this->{$property} = null;
        $this->resetValidation($property);
        $this->hydrateFromSettings($settings, $branding);
        $this->statusMessage = 'Logo atualizada.';
    }

    public function resetSection(string $section, ClinicSettings $settings, ClinicBranding $branding): void
    {
        $this->authorize('manage', ClinicSetting::class);
        $clinic = $this->currentClinic();

        $keys = match ($section) {
            'geral' => ['display_name', 'slogan'],
            'cores' => [
                'primary_color',
                'accent_color',
            ],
            'tv' => [
                'tv_show_date', 'tv_show_time', 'tv_show_connection_status', 'tv_show_ticket_type',
                'tv_recent_calls_count', 'tv_footer_enabled',
                'tv_footer_1_title', 'tv_footer_1_text', 'tv_footer_2_title', 'tv_footer_2_text',
                'tv_footer_3_title', 'tv_footer_3_text',
                'tv_chime_enabled', 'tv_speech_enabled', 'tv_speak_ticket_type', 'tv_speak_desk', 'tv_chime_volume',
            ],
            'totem' => [
                'kiosk_title', 'kiosk_subtitle', 'kiosk_instruction_text',
                'kiosk_issued_message', 'kiosk_finish_button_text', 'kiosk_auto_return_seconds',
            ],
            default => [],
        };

        $settings->resetKeys($clinic, $keys);
        $this->hydrateFromSettings($settings, $branding);
        $this->statusMessage = 'Padrões restaurados.';
    }

    public function render(): View
    {
        return view('livewire.clinic-settings-manager');
    }

    private function currentClinic(): Clinic
    {
        $user = auth()->user();
        abort_if($user === null || $user->clinic_id === null, 403);

        $clinic = $user->clinic;
        abort_if($clinic === null, 403);
        $this->authorize('manage', $clinic);

        return $clinic;
    }

    private function hydrateFromSettings(ClinicSettings $settings, ClinicBranding $branding): void
    {
        $clinic = $this->currentClinic();
        $bag = $settings->all($clinic);
        $this->brandingPreview = $branding->resolve($clinic);

        $this->display_name = (string) ($bag['display_name'] ?? '');
        $this->slogan = (string) ($bag['slogan'] ?? '');
        $this->primary_color = (string) $bag['primary_color'];
        $this->accent_color = (string) $bag['accent_color'];

        $mainSurface = LogoSurface::fromBag($bag, 'main');
        $tvSurface = LogoSurface::fromBag($bag, 'tv');
        $kioskSurface = LogoSurface::fromBag($bag, 'kiosk');
        $this->main_logo_background = $mainSurface['mode'];
        $this->main_logo_background_color = $mainSurface['color'];
        $this->tv_logo_background = $tvSurface['mode'];
        $this->tv_logo_background_color = $tvSurface['color'];
        $this->kiosk_logo_background = $kioskSurface['mode'];
        $this->kiosk_logo_background_color = $kioskSurface['color'];

        $this->tv_show_date = (bool) $bag['tv_show_date'];
        $this->tv_show_time = (bool) $bag['tv_show_time'];
        $this->tv_show_connection_status = (bool) $bag['tv_show_connection_status'];
        $this->tv_show_ticket_type = (bool) $bag['tv_show_ticket_type'];
        $this->tv_recent_calls_count = (int) $bag['tv_recent_calls_count'];
        $this->tv_footer_enabled = (bool) $bag['tv_footer_enabled'];
        $this->tv_footer_1_title = (string) $bag['tv_footer_1_title'];
        $this->tv_footer_1_text = (string) $bag['tv_footer_1_text'];
        $this->tv_footer_2_title = (string) $bag['tv_footer_2_title'];
        $this->tv_footer_2_text = (string) $bag['tv_footer_2_text'];
        $this->tv_footer_3_title = (string) $bag['tv_footer_3_title'];
        $this->tv_footer_3_text = (string) $bag['tv_footer_3_text'];
        $this->tv_chime_enabled = (bool) $bag['tv_chime_enabled'];
        $this->tv_speech_enabled = (bool) $bag['tv_speech_enabled'];
        $this->tv_speak_ticket_type = (bool) $bag['tv_speak_ticket_type'];
        $this->tv_speak_desk = (bool) $bag['tv_speak_desk'];
        $this->tv_chime_volume = (int) $bag['tv_chime_volume'];

        $this->kiosk_title = (string) $bag['kiosk_title'];
        $this->kiosk_subtitle = (string) $bag['kiosk_subtitle'];
        $this->kiosk_instruction_text = (string) $bag['kiosk_instruction_text'];
        $this->kiosk_issued_message = (string) $bag['kiosk_issued_message'];
        $this->kiosk_finish_button_text = (string) $bag['kiosk_finish_button_text'];
        $this->kiosk_auto_return_seconds = (int) $bag['kiosk_auto_return_seconds'];

        $panel = DisplayPanel::query()
            ->where('clinic_id', $clinic->id)
            ->where('active', true)
            ->orderBy('id')
            ->first();
        $this->previewTvUrl = $panel ? route('tv.panel', $panel->public_token) : null;

        $kiosk = Kiosk::query()
            ->where('clinic_id', $clinic->id)
            ->where('active', true)
            ->orderBy('id')
            ->first();
        $this->previewKioskUrl = $kiosk ? route('kiosk.panel', $kiosk->public_token) : null;
    }
}
