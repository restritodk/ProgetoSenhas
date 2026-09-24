<?php

namespace App\Services;

use App\Models\DisplayPanel;
use App\Models\TicketCall;
use App\Support\ClinicSettingCatalog;
use App\Support\LogoSurface;
use App\Support\TvPresentation;
use Illuminate\Support\Collection;

class DisplayPanelFeed
{
    public function __construct(
        private TicketCallVoiceFormatter $voiceFormatter,
        private ClinicSettings $clinicSettings,
        private ClinicBranding $clinicBranding,
    ) {}

    public function findByPublicToken(string $publicToken): ?DisplayPanel
    {
        return $this->findByPublicIdentifier($publicToken);
    }

    /**
     * Resolve by short public_code or legacy public_token.
     * Does not accept numeric IDs.
     */
    public function findByPublicIdentifier(string $identifier): ?DisplayPanel
    {
        if ($identifier === '' || strlen($identifier) < 8) {
            return null;
        }

        return DisplayPanel::query()
            ->with([
                'clinic:id,name,active',
                'unit:id,clinic_id,name,active',
                'sectors:id,clinic_id,unit_id,name,active',
            ])
            ->where(function ($query) use ($identifier): void {
                $query->where('public_code', $identifier)
                    ->orWhere('public_token', $identifier);
            })
            ->first();
    }

    /**
     * Lean operational payload for the TV screen. Never includes personal user data.
     *
     * @return array<string, mixed>
     */
    public function build(DisplayPanel $panel): array
    {
        $panel->loadMissing([
            'clinic:id,name,active',
            'unit:id,clinic_id,name,active',
            'sectors:id,clinic_id,unit_id,name,active',
        ]);

        $presentation = $panel->clinic !== null
            ? TvPresentation::forClinic($panel->clinic, $this->clinicSettings, $this->clinicBranding)->toArray()
            : $this->defaultPresentation();

        $displayName = (string) ($presentation['display_name'] ?? '');
        if ($displayName === '') {
            $displayName = $panel->clinic?->name ?? '';
        }

        $base = [
            'available' => false,
            'panel_name' => $panel->name,
            'clinic_name' => $displayName,
            'unit_name' => $panel->unit?->name ?? '',
            'presentation' => $presentation,
            'current_call' => null,
            'recent_calls' => [],
        ];

        if (! $panel->isOperationallyAvailable() || $panel->clinic === null || $panel->unit === null) {
            return $base;
        }

        $limit = max(3, min(8, (int) ($presentation['recent_calls_count'] ?? 5)));
        $recent = $this->recentCalls($panel, $limit);
        $current = $recent->first();

        $speakType = (bool) ($presentation['speak_ticket_type'] ?? true);
        $speakDesk = (bool) ($presentation['speak_desk'] ?? true);

        return [
            'available' => true,
            'panel_name' => $panel->name,
            'clinic_name' => $displayName,
            'unit_name' => $panel->unit->name,
            'presentation' => $presentation,
            'current_call' => $current !== null
                ? $this->mapCall($current, true, $speakType, $speakDesk)
                : null,
            'recent_calls' => $recent
                ->map(fn (TicketCall $call): array => $this->mapCall($call, false, $speakType, $speakDesk))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultPresentation(): array
    {
        return [
            'display_name' => '',
            'slogan' => '',
            'logo_url' => null,
            'logo_background' => LogoSurface::NONE,
            'logo_background_color' => LogoSurface::DEFAULT_CUSTOM_COLOR,
            'primary_color' => ClinicSettingCatalog::DEFAULT_PRIMARY_COLOR,
            'accent_color' => ClinicSettingCatalog::DEFAULT_ACCENT_COLOR,
            'on_primary_color' => '#ffffff',
            'show_date' => true,
            'show_time' => true,
            'show_connection_status' => true,
            'show_ticket_type' => true,
            'recent_calls_count' => 5,
            'footer_enabled' => true,
            'footer_1' => ['title' => 'Acompanhe sua senha', 'text' => 'Fique atento ao painel.'],
            'footer_2' => ['title' => 'Dirija-se à mesa', 'text' => 'Quando sua senha for chamada.'],
            'footer_3' => ['title' => 'Aguarde sua vez', 'text' => 'Obrigado pela compreensão.'],
            'chime_enabled' => true,
            'speech_enabled' => true,
            'speak_ticket_type' => true,
            'speak_desk' => true,
            'chime_volume' => 70,
        ];
    }

    /**
     * @return Collection<int, TicketCall>
     */
    private function recentCalls(DisplayPanel $panel, int $limit): Collection
    {
        $sectorIds = $panel->sectorIds();

        return TicketCall::query()
            ->with([
                'ticket.ticketType:id,name,prefix',
                'desk:id,name,code',
            ])
            ->where('clinic_id', $panel->clinic_id)
            ->where('unit_id', $panel->unit_id)
            ->when(
                $sectorIds !== [],
                fn ($query) => $query->whereIn('sector_id', $sectorIds),
                // Sem setores vinculados (legado): mantém escopo da unidade.
            )
            ->orderByDesc('called_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapCall(TicketCall $call, bool $includeAnnouncement, bool $speakType, bool $speakDesk): array
    {
        $payload = [
            'id' => $call->id,
            'display_code' => $call->ticket?->display_code ?? '—',
            'ticket_type_name' => $call->ticket?->ticketType?->name ?? 'Senha',
            'ticket_type_prefix' => $call->ticket?->ticketType?->prefix ?? '—',
            'desk_name' => $call->desk?->name ?? 'Mesa',
            'called_at' => $call->called_at?->timezone(config('app.timezone'))->format('H:i') ?? '',
            'call_type' => $call->call_type->value,
            'call_type_label' => $call->call_type->label(),
        ];

        if ($includeAnnouncement) {
            $payload['announcement'] = $this->voiceFormatter->announce($call, $speakType, $speakDesk);
        }

        return $payload;
    }
}
