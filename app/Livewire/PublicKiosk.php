<?php

namespace App\Livewire;

use App\Actions\IssueTicket;
use App\Models\Kiosk;
use App\Models\UnitTicketType;
use App\Services\ClinicBranding;
use App\Services\ClinicSettings;
use App\Services\KioskPrintGrantService;
use App\Support\KioskPresentation;
use App\Support\KioskPrintPayload;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
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

            $offer = UnitTicketType::query()
                ->where('clinic_id', $kiosk->clinic_id)
                ->where('unit_id', $kiosk->unit_id)
                ->where('ticket_type_id', $ticket->ticket_type_id)
                ->first();

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

            $this->printDispatch = $printGrants->grantPrintTicket(
                $kiosk->fresh(),
                KioskPrintPayload::jobIdForTicket((int) $ticket->id),
                KioskPrintPayload::forTicket(
                    displayCode: $ticket->display_code,
                    typeLabel: $typeLabel,
                    clinicName: $clinicName,
                    unitName: (string) ($kiosk->unit?->name ?? ''),
                    issuedAtLabel: $issuedAtLabel,
                    message: (string) ($bag['kiosk_issued_message'] ?? 'Aguarde sua senha ser chamada no painel.'),
                ),
            );
        } catch (TooManyRequestsHttpException $exception) {
            $this->errorMessage = 'Não foi possível emitir sua senha. Aguarde um momento e tente novamente.';
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?? 'Não foi possível emitir a senha.';
            $friendly = mb_strtolower((string) $message);

            if (str_contains($friendly, 'indispon')) {
                $this->screen = 'unavailable';
                $this->errorMessage = 'Totem temporariamente indisponível.';
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

    public function finish(): void
    {
        $this->resetResult();
        $this->screen = 'home';
        $this->errorMessage = '';
        $this->issuing = false;
        $this->issuingTicketTypeId = null;
        $this->printDispatch = null;
        $this->refreshRequestToken();
    }

    public function refreshAvailability(ClinicSettings $settings, ClinicBranding $branding): void
    {
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
            ->with(['clinic:id,name,active', 'unit:id,clinic_id,name,active'])
            ->where('public_token', $this->publicToken)
            ->first();
    }

    /**
     * @return Collection<int, UnitTicketType>
     */
    #[Computed]
    public function offeredTypes(): Collection
    {
        $kiosk = $this->kiosk;

        if ($kiosk === null || ! $kiosk->isOperationallyAvailable()) {
            return collect();
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
