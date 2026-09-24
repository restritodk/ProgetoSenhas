<?php

namespace App\Livewire;

use App\Actions\IssueTicket;
use App\KioskPrintMethod;
use App\Models\Kiosk;
use App\Models\SectorTicketType;
use App\Models\UnitTicketType;
use App\Services\ClinicBranding;
use App\Services\ClinicSettings;
use App\Services\KioskPrintGrantService;
use App\Support\KioskPresentation;
use App\Support\KioskPrintPayload;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class PublicKiosk extends Component
{
    public string $publicToken = '';

    public string $screen = 'home';

    public ?int $issuedTicketId = null;

    public string $issuedDisplayCode = '';

    public string $issuedTypeLabel = '';

    public string $issuedAtLabel = '';

    public string $errorMessage = '';

    public string $requestToken = '';

    public bool $issuing = false;

    public ?int $issuingTicketTypeId = null;

    /** @var array<string, mixed> */
    public array $presentation = [];

    /**
     * Short-lived print grant for the local agent (never contains the pairing secret).
     *
     * @var array{grant: array<string, mixed>, signature: string, agentUrl: string}|null
     */
    public ?array $printDispatch = null;

    /**
     * Browser-native receipt payload (no secrets, no agent URL).
     *
     * @var array{
     *     clinicName: string,
     *     unitName: string,
     *     displayCode: string,
     *     typeLabel: string,
     *     issuedAtLabel: string,
     *     message: string,
     *     paperWidth: string
     * }|null
     */
    public ?array $browserPrintPayload = null;

    public function mount(string $publicToken, ClinicSettings $settings, ClinicBranding $branding): void
    {
        $this->publicToken = $publicToken;
        $this->refreshRequestToken();

        if ($this->kiosk === null) {
            abort(404);
        }

        $this->syncPresentation($settings, $branding);

        if (! $this->kiosk->isOperationallyAvailable()) {
            $this->screen = 'unavailable';
            $this->errorMessage = 'Totem temporariamente indisponível.';
        }
    }

    public function issue(
        int $ticketTypeId,
        IssueTicket $issueTicket,
        KioskPrintGrantService $printGrants,
        ClinicSettings $settings,
    ): void {
        if ($this->issuing || $this->screen === 'result') {
            return;
        }

        $this->issuing = true;
        $this->issuingTicketTypeId = $ticketTypeId;
        $this->errorMessage = '';
        $this->printDispatch = null;
        $this->browserPrintPayload = null;

        $kiosk = $this->kiosk;

        if ($kiosk === null || ! $kiosk->isOperationallyAvailable()) {
            $this->issuing = false;
            $this->issuingTicketTypeId = null;
            $this->errorMessage = 'Totem temporariamente indisponível.';
            $this->screen = 'unavailable';

            return;
        }

        $token = $this->requestToken;

        try {
            $ticket = $issueTicket->handleFromKiosk(
                $kiosk,
                $ticketTypeId,
                $token,
                request()->ip(),
            );

            $offer = null;
            if ($kiosk->sector_id !== null) {
                $offer = SectorTicketType::query()
                    ->where('clinic_id', $kiosk->clinic_id)
                    ->where('sector_id', $kiosk->sector_id)
                    ->where('ticket_type_id', $ticket->ticket_type_id)
                    ->first();
            }
            if ($offer === null) {
                $offer = UnitTicketType::query()
                    ->where('clinic_id', $kiosk->clinic_id)
                    ->where('unit_id', $kiosk->unit_id)
                    ->where('ticket_type_id', $ticket->ticket_type_id)
                    ->first();
            }

            $typeLabel = $offer?->publicLabel() ?? ($ticket->ticketType?->name ?? 'Atendimento');
            $issuedAtLabel = $ticket->issued_at
                ->timezone(config('app.timezone'))
                ->format('d/m/Y H:i');

            $this->issuedTicketId = $ticket->id;
            $this->issuedDisplayCode = $ticket->display_code;
            $this->issuedTypeLabel = $typeLabel;
            $this->issuedAtLabel = $issuedAtLabel;
            $this->screen = 'result';
            $this->refreshRequestToken();

            $bag = $settings->all($kiosk->clinic);
            $clinicName = trim((string) ($bag['display_name'] ?? ''));
            if ($clinicName === '') {
                $clinicName = (string) ($kiosk->clinic?->name ?? config('app.name'));
            }

            $unitName = (string) ($kiosk->unit?->name ?? '');
            $sectorName = (string) ($kiosk->sector?->name ?? '');
            if ($sectorName !== '') {
                $unitName = $unitName !== '' ? $unitName.' · '.$sectorName : $sectorName;
            }

            $kioskForPrint = $kiosk->fresh();
            $message = (string) ($bag['kiosk_issued_message'] ?? 'Aguarde sua senha ser chamada no painel.');
            $ticketPayload = KioskPrintPayload::forTicket(
                displayCode: $ticket->display_code,
                typeLabel: $typeLabel,
                clinicName: $clinicName,
                unitName: $unitName,
                issuedAtLabel: $issuedAtLabel,
                message: $message,
            );

            $printMethod = KioskPrintMethod::normalize($kioskForPrint->print_method);

            if (! $kioskForPrint->print_enabled) {
                Log::info('kiosk.print.skipped_disabled', [
                    'kiosk_id' => $kioskForPrint->id,
                    'ticket_id' => $ticket->id,
                    'display_code' => $ticket->display_code,
                    'print_method' => $printMethod->value,
                ]);
            } elseif ($printMethod === KioskPrintMethod::Browser) {
                $paperWidth = $printGrants->normalizePaperWidth($kioskForPrint->print_paper_width);
                $this->browserPrintPayload = [
                    ...$ticketPayload,
                    'paperWidth' => $paperWidth,
                ];

                Log::info('kiosk.print.browser_print_requested', [
                    'kiosk_id' => $kioskForPrint->id,
                    'ticket_id' => $ticket->id,
                    'display_code' => $ticket->display_code,
                    'paper_width' => $paperWidth,
                ]);

                $this->dispatch(
                    'kiosk-browser-print',
                    clinicName: $ticketPayload['clinicName'],
                    unitName: $ticketPayload['unitName'],
                    displayCode: $ticketPayload['displayCode'],
                    typeLabel: $ticketPayload['typeLabel'],
                    issuedAtLabel: $ticketPayload['issuedAtLabel'],
                    message: $ticketPayload['message'],
                    paperWidth: $paperWidth,
                );
            } else {
                $this->printDispatch = $printGrants->grantPrintTicket(
                    $kioskForPrint,
                    KioskPrintPayload::jobIdForTicket((int) $ticket->id),
                    $ticketPayload,
                );

                if ($this->printDispatch === null) {
                    Log::info('kiosk.print.grant_skipped', [
                        'kiosk_id' => $kioskForPrint->id,
                        'ticket_id' => $ticket->id,
                        'display_code' => $ticket->display_code,
                        'print_enabled' => (bool) $kioskForPrint->print_enabled,
                        'print_method' => $printMethod->value,
                        'paired' => filled($kioskForPrint->print_agent_secret_encrypted),
                        'has_printer' => filled($kioskForPrint->print_printer_name),
                        'listen_mode' => $kioskForPrint->print_agent_listen_mode,
                        'print_ready' => $kioskForPrint->isPrintReady(),
                    ]);
                } else {
                    Log::info('kiosk.print.grant_created', [
                        'kiosk_id' => $kioskForPrint->id,
                        'ticket_id' => $ticket->id,
                        'display_code' => $ticket->display_code,
                        'job_id' => $this->printDispatch['grant']['jobId'] ?? null,
                        'agent_url' => $this->printDispatch['agentUrl'] ?? null,
                        'printer' => $this->printDispatch['grant']['printer'] ?? null,
                    ]);

                    $this->dispatch(
                        'kiosk-print-ticket',
                        grant: $this->printDispatch['grant'],
                        signature: $this->printDispatch['signature'],
                        agentUrl: $this->printDispatch['agentUrl'],
                    );
                }
            }
        } catch (TooManyRequestsHttpException $exception) {
            $this->errorMessage = 'Não foi possível emitir sua senha. Aguarde um momento e tente novamente.';
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?? 'Não foi possível emitir a senha.';
            $friendly = mb_strtolower((string) $message);

            if (str_contains($friendly, 'totem') && str_contains($friendly, 'indispon')) {
                $this->screen = 'unavailable';
                $this->errorMessage = 'Totem temporariamente indisponível.';
            } elseif (str_contains($friendly, 'acabou de ficar indisponível') || str_contains($friendly, 'escolha outra')) {
                unset($this->offeredTypes);
                $this->screen = 'home';
                $this->errorMessage = 'Esta opção de atendimento acabou de ficar indisponível. Escolha outra opção.';
            } else {
                $this->errorMessage = 'Não foi possível emitir sua senha. Tente novamente ou procure a recepção.';
            }
        } finally {
            $this->issuing = false;
            $this->issuingTicketTypeId = null;
        }
    }

    public function clearError(): void
    {
        $this->errorMessage = '';
    }

    public function clearPrintDispatch(): void
    {
        $this->printDispatch = null;
    }

    public function clearBrowserPrintPayload(): void
    {
        $this->browserPrintPayload = null;
    }

    public function finish(): void
    {
        $this->resetResult();
        $this->screen = 'home';
        $this->errorMessage = '';
        $this->issuing = false;
        $this->issuingTicketTypeId = null;
        $this->printDispatch = null;
        $this->browserPrintPayload = null;
        $this->refreshRequestToken();
    }

    public function refreshAvailability(ClinicSettings $settings, ClinicBranding $branding): void
    {
        // Never interrupt an issued ticket screen or an in-flight issue (poll is also gated in the view).
        if ($this->screen === 'result' || $this->issuing) {
            return;
        }

        // Force fresh reads so Totem reflects type offer / activation changes without full reload.
        unset($this->kiosk, $this->offeredTypes);

        $kiosk = $this->kiosk;

        if ($kiosk === null) {
            abort(404);
        }

        $this->syncPresentation($settings, $branding);

        if (! $kiosk->isOperationallyAvailable()) {
            $this->screen = 'unavailable';
            $this->errorMessage = 'Totem temporariamente indisponível.';
        } elseif ($this->screen === 'unavailable') {
            $this->screen = 'home';
            $this->errorMessage = '';
        }
    }

    #[Computed]
    public function kiosk(): ?Kiosk
    {
        return Kiosk::query()
            ->with([
                'clinic:id,name,active',
                'unit:id,clinic_id,name,active',
                'sector:id,clinic_id,unit_id,name,active',
            ])
            ->where('public_token', $this->publicToken)
            ->first();
    }

    /**
     * @return Collection<int, UnitTicketType|SectorTicketType>
     */
    #[Computed]
    public function offeredTypes(): Collection
    {
        $kiosk = $this->kiosk;

        if ($kiosk === null || ! $kiosk->isOperationallyAvailable()) {
            return collect();
        }

        if ($kiosk->sector_id !== null) {
            $sectorOffers = SectorTicketType::query()
                ->with(['ticketType:id,clinic_id,name,prefix,priority,active'])
                ->where('clinic_id', $kiosk->clinic_id)
                ->where('sector_id', $kiosk->sector_id)
                ->where('active', true)
                ->whereHas('ticketType', fn ($query) => $query->where('active', true))
                ->orderBy('position')
                ->orderBy('id')
                ->get();

            if ($sectorOffers->isNotEmpty()) {
                return $sectorOffers;
            }
        }

        return UnitTicketType::query()
            ->with(['ticketType:id,clinic_id,name,prefix,priority,active'])
            ->where('clinic_id', $kiosk->clinic_id)
            ->where('unit_id', $kiosk->unit_id)
            ->where('active', true)
            ->whereHas('ticketType', fn ($query) => $query->where('active', true))
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.public-kiosk');
    }

    private function syncPresentation(ClinicSettings $settings, ClinicBranding $branding): void
    {
        $kiosk = $this->kiosk;
        if ($kiosk?->clinic === null) {
            $this->presentation = [];

            return;
        }

        $this->presentation = KioskPresentation::forClinic($kiosk->clinic, $settings, $branding)->toArray();
    }

    private function refreshRequestToken(): void
    {
        $this->requestToken = Str::lower(Str::random(40));
    }

    private function resetResult(): void
    {
        $this->issuedTicketId = null;
        $this->issuedDisplayCode = '';
        $this->issuedTypeLabel = '';
        $this->issuedAtLabel = '';
    }
}
