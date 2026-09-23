<?php

namespace App\Livewire;

use App\Actions\CreateKiosk;
use App\Actions\EnsureDefaultSectorForUnit;
use App\Actions\RegenerateKioskToken;
use App\Actions\UpdateKiosk;
use App\Models\Kiosk;
use App\Models\Sector;
use App\Models\Unit;
use App\Services\ClinicBranding;
use App\Services\KioskPrintGrantService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class KiosksManager extends Component
{
    use WithPagination;

    public string $search = '';

    public string $unitFilter = '';

    public string $statusFilter = '';

    public bool $showForm = false;

    public ?int $editingKioskId = null;

    public string $name = '';

    public string $code = '';

    public ?int $unitId = null;

    public ?int $sectorId = null;

    public bool $active = true;

    public bool $printEnabled = false;

    public string $printAgentListenMode = 'local';

    public string $printAgentHost = '';

    public int $printAgentPort = 17321;

    public string $printPrinterName = '';

    public string $printPaperWidth = '80';

    public bool $printAutoCut = true;

    public bool $printLogo = false;

    public bool $printIsPaired = false;

    public string $pairingSecretOnce = '';

    public string $printStatusMessage = '';

    /** unknown|online|offline */
    public string $printAgentStatus = 'unknown';

    /** @var list<array{name: string, isDefault?: bool, status?: string}> */
    public array $availablePrinters = [];

    /** @var array{grant: array<string, mixed>, signature: string, agentUrl: string}|null */
    public ?array $printerListGrant = null;

    /** @var array{grant: array<string, mixed>, signature: string, agentUrl: string}|null */
    public ?array $testPrintGrant = null;

    public ?int $kioskPendingDeactivationId = null;

    public ?int $kioskPendingTokenRegenId = null;

    public string $statusMessage = '';

    public string $copiedUrlKioskId = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Kiosk::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedUnitFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedUnitId(): void
    {
        $this->sectorId = null;

        if ($this->unitId === null) {
            return;
        }

        $unit = Unit::query()
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->whereKey($this->unitId)
            ->first();

        if ($unit === null) {
            return;
        }

        $defaultSectorId = Sector::query()
            ->where('clinic_id', $unit->clinic_id)
            ->where('unit_id', $unit->id)
            ->where('code', EnsureDefaultSectorForUnit::DEFAULT_CODE)
            ->value('id');

        if ($defaultSectorId === null) {
            $defaultSectorId = app(EnsureDefaultSectorForUnit::class)->handle($unit)->id;
        }

        $this->sectorId = (int) $defaultSectorId;
    }

    public function startCreate(): void
    {
        $this->authorize('create', Kiosk::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $kioskId): void
    {
        $kiosk = $this->kioskForCurrentClinic($kioskId);
        $this->authorize('update', $kiosk);

        $this->editingKioskId = $kiosk->id;
        $this->name = $kiosk->name;
        $this->code = $kiosk->code;
        $this->unitId = $kiosk->unit_id;
        $this->sectorId = $kiosk->sector_id;
        $this->active = $kiosk->active;
        $this->printEnabled = (bool) $kiosk->print_enabled;
        $this->printAgentListenMode = app(KioskPrintGrantService::class)->normalizeListenMode($kiosk->print_agent_listen_mode ?? 'local');
        $this->printAgentHost = (string) ($kiosk->print_agent_host ?? '');
        $this->printAgentPort = (int) ($kiosk->print_agent_port ?: KioskPrintGrantService::DEFAULT_PORT);
        $this->printPrinterName = (string) ($kiosk->print_printer_name ?? '');
        $this->printPaperWidth = app(KioskPrintGrantService::class)->normalizePaperWidth($kiosk->print_paper_width);
        $this->printAutoCut = (bool) $kiosk->print_auto_cut;
        $this->printLogo = (bool) $kiosk->print_logo;
        $this->printIsPaired = filled($kiosk->print_agent_secret_encrypted);
        $this->pairingSecretOnce = '';
        $this->printStatusMessage = '';
        $this->printAgentStatus = 'unknown';
        $this->availablePrinters = [];
        $this->printerListGrant = null;
        $this->testPrintGrant = null;
        $this->showForm = true;
        $this->kioskPendingDeactivationId = null;
        $this->kioskPendingTokenRegenId = null;
        $this->statusMessage = '';
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(CreateKiosk $createKiosk, UpdateKiosk $updateKiosk): void
    {
        $actor = auth()->user();
        abort_if($actor?->clinic_id === null, 404);

        $this->code = Str::upper($this->code);
        $this->validate();

        $attributes = [
            'name' => $this->name,
            'code' => $this->code,
            'unit_id' => (int) $this->unitId,
            'sector_id' => (int) $this->sectorId,
            'active' => $this->active,
        ];

        if ($this->editingKioskId !== null) {
            $kiosk = $this->kioskForCurrentClinic($this->editingKioskId);
            $updateKiosk->handle($actor, $kiosk, $attributes);
            $this->persistPrintSettings($kiosk->fresh());
            $message = 'Totem atualizado com sucesso.';
        } else {
            $kiosk = $createKiosk->handle($actor, $attributes);
            $this->persistPrintSettings($kiosk->fresh());
            $message = 'Totem criado com sucesso.';
        }

        $this->resetForm();
        $this->statusMessage = $message;
        $this->resetPage();
    }

    public function pairPrintAgent(KioskPrintGrantService $printGrants): void
    {
        abort_if($this->editingKioskId === null, 404);
        $kiosk = $this->kioskForCurrentClinic($this->editingKioskId);
        $this->authorize('update', $kiosk);

        $this->pairingSecretOnce = $printGrants->pair($kiosk);
        $this->printIsPaired = true;
        $this->printStatusMessage = 'Pareamento gerado. Copie o segredo para o Humana Print Agent e não compartilhe.';
    }

    public function revokePrintAgent(KioskPrintGrantService $printGrants): void
    {
        abort_if($this->editingKioskId === null, 404);
        $kiosk = $this->kioskForCurrentClinic($this->editingKioskId);
        $this->authorize('update', $kiosk);

        $printGrants->revokePairing($kiosk);
        $this->printIsPaired = false;
        $this->printEnabled = false;
        $this->printPrinterName = '';
        $this->pairingSecretOnce = '';
        $this->availablePrinters = [];
        $this->printAgentStatus = 'unknown';
        $this->printStatusMessage = 'Pareamento revogado. O agente precisa ser pareado novamente.';
    }

    public function prepareLoadPrinters(KioskPrintGrantService $printGrants): void
    {
        abort_if($this->editingKioskId === null, 404);
        $kiosk = $this->kioskForCurrentClinic($this->editingKioskId);
        $this->authorize('update', $kiosk);

        $listenMode = $printGrants->normalizeListenMode($this->printAgentListenMode);
        $agentHost = $printGrants->normalizeAgentHost($this->printAgentHost);

        if ($listenMode === KioskPrintGrantService::LISTEN_LAN && $agentHost === null) {
            $this->printStatusMessage = 'No modo LAN, informe o IP/hostname da CPU Windows antes de conectar.';

            return;
        }

        $kiosk->forceFill([
            'print_agent_listen_mode' => $listenMode,
            'print_agent_host' => $listenMode === KioskPrintGrantService::LISTEN_LAN ? $agentHost : null,
            'print_agent_port' => max(1024, min(65535, (int) $this->printAgentPort)),
        ])->save();

        $grant = $printGrants->grantListPrinters($kiosk->fresh());
        $this->printerListGrant = null;

        if ($grant === null) {
            $this->printStatusMessage = 'Pareie o agente antes de listar impressoras.';

            return;
        }

        $this->dispatch(
            'kiosk-agent-list-printers',
            grant: $grant['grant'],
            signature: $grant['signature'],
            agentUrl: $grant['agentUrl'],
        );
    }

    /**
     * @param  list<array{name?: string, isDefault?: bool, status?: string}>  $printers
     */
    public function receivePrinters(array $printers): void
    {
        abort_if($this->editingKioskId === null, 404);
        $this->kioskForCurrentClinic($this->editingKioskId);

        $normalized = [];
        foreach ($printers as $printer) {
            $name = trim((string) ($printer['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $normalized[] = [
                'name' => mb_substr($name, 0, 255),
                'isDefault' => (bool) ($printer['isDefault'] ?? false),
                'status' => (string) ($printer['status'] ?? 'available'),
            ];
        }

        $this->availablePrinters = $normalized;
        $this->printAgentStatus = 'online';
        $this->printStatusMessage = $normalized === []
            ? 'Nenhuma impressora retornada pelo agente.'
            : 'Lista de impressoras atualizada. Selecione explicitamente a impressora deste Totem.';
    }

    public function markAgentUnavailable(string $reason = ''): void
    {
        $this->printerListGrant = null;
        $this->testPrintGrant = null;
        $this->printAgentStatus = 'offline';
        $this->printStatusMessage = $reason !== ''
            ? $reason
            : 'Agente não encontrado em 127.0.0.1. Verifique se o Humana Print Agent está em execução neste computador.';
    }

    public function prepareTestPrint(KioskPrintGrantService $printGrants, ClinicBranding $branding): void
    {
        abort_if($this->editingKioskId === null, 404);
        $kiosk = $this->kioskForCurrentClinic($this->editingKioskId);
        $this->authorize('update', $kiosk);

        if (trim($this->printPrinterName) === '') {
            $this->printStatusMessage = 'Selecione uma impressora antes de testar.';

            return;
        }

        $kiosk->forceFill([
            'print_printer_name' => trim($this->printPrinterName),
            'print_paper_width' => $printGrants->normalizePaperWidth($this->printPaperWidth),
            'print_auto_cut' => $this->printAutoCut,
            'print_agent_listen_mode' => $printGrants->normalizeListenMode($this->printAgentListenMode),
            'print_agent_host' => $printGrants->normalizeAgentHost($this->printAgentHost),
            'print_agent_port' => max(1024, min(65535, (int) $this->printAgentPort)),
        ])->save();

        $clinicName = $branding->displayName($kiosk->clinic);
        $grant = $printGrants->grantPrintTest(
            $kiosk->fresh(),
            $clinicName,
            (string) ($kiosk->unit?->name ?? $kiosk->name),
        );

        $this->testPrintGrant = null;

        if ($grant === null) {
            $this->printStatusMessage = 'Não foi possível gerar o teste. Verifique o pareamento e a impressora.';

            return;
        }

        $this->dispatch(
            'kiosk-agent-test-print',
            grant: $grant['grant'],
            signature: $grant['signature'],
            agentUrl: $grant['agentUrl'],
        );
    }

    public function setPrintStatusMessage(string $message): void
    {
        $this->printStatusMessage = mb_substr($message, 0, 240);
    }

    public function clearTestPrintGrant(): void
    {
        $this->testPrintGrant = null;
    }

    public function confirmDeactivation(int $kioskId): void
    {
        $kiosk = $this->kioskForCurrentClinic($kioskId);
        $this->authorize('update', $kiosk);
        $this->kioskPendingDeactivationId = $kiosk->id;
    }

    public function cancelDeactivation(): void
    {
        $this->kioskPendingDeactivationId = null;
    }

    public function deactivate(UpdateKiosk $updateKiosk): void
    {
        abort_if($this->kioskPendingDeactivationId === null, 404);

        $actor = auth()->user();
        $kiosk = $this->kioskForCurrentClinic($this->kioskPendingDeactivationId);
        $this->authorize('update', $kiosk);

        $updateKiosk->handle($actor, $kiosk, [
            'name' => $kiosk->name,
            'code' => $kiosk->code,
            'unit_id' => $kiosk->unit_id,
            'sector_id' => $kiosk->sector_id,
            'active' => false,
        ]);

        $this->kioskPendingDeactivationId = null;
        $this->statusMessage = 'Totem desativado.';
        $this->resetPage();
    }

    public function activate(int $kioskId, UpdateKiosk $updateKiosk): void
    {
        $actor = auth()->user();
        $kiosk = $this->kioskForCurrentClinic($kioskId);
        $this->authorize('update', $kiosk);

        $updateKiosk->handle($actor, $kiosk, [
            'name' => $kiosk->name,
            'code' => $kiosk->code,
            'unit_id' => $kiosk->unit_id,
            'sector_id' => $kiosk->sector_id,
            'active' => true,
        ]);

        $this->statusMessage = 'Totem ativado.';
        $this->resetPage();
    }

    public function confirmTokenRegen(int $kioskId): void
    {
        $kiosk = $this->kioskForCurrentClinic($kioskId);
        $this->authorize('regenerateToken', $kiosk);
        $this->kioskPendingTokenRegenId = $kiosk->id;
    }

    public function cancelTokenRegen(): void
    {
        $this->kioskPendingTokenRegenId = null;
    }

    public function regenerateToken(RegenerateKioskToken $regenerateKioskToken): void
    {
        abort_if($this->kioskPendingTokenRegenId === null, 404);

        $actor = auth()->user();
        $kiosk = $this->kioskForCurrentClinic($this->kioskPendingTokenRegenId);
        $this->authorize('regenerateToken', $kiosk);

        $regenerateKioskToken->handle($actor, $kiosk);

        $this->kioskPendingTokenRegenId = null;
        $this->statusMessage = 'Token do totem regenerado. A URL anterior deixou de funcionar.';
        $this->resetPage();
    }

    public function markUrlCopied(int $kioskId): void
    {
        $this->kioskForCurrentClinic($kioskId);
        $this->copiedUrlKioskId = (string) $kioskId;
    }

    /**
     * @return LengthAwarePaginator<int, Kiosk>
     */
    public function kiosks(): LengthAwarePaginator
    {
        $clinicId = auth()->user()?->clinic_id;

        return Kiosk::query()
            ->with(['unit:id,name,clinic_id', 'sector:id,name,unit_id,clinic_id'])
            ->where('clinic_id', $clinicId)
            ->when($this->search !== '', function ($query): void {
                $term = '%'.mb_strtolower($this->search).'%';
                $query->where(function ($search) use ($term): void {
                    $search->whereRaw('LOWER(name) like ?', [$term])
                        ->orWhereRaw('LOWER(code) like ?', [$term]);
                });
            })
            ->when($this->unitFilter !== '', fn ($query) => $query->where('unit_id', (int) $this->unitFilter))
            ->when($this->statusFilter === 'active', fn ($query) => $query->where('active', true))
            ->when($this->statusFilter === 'inactive', fn ($query) => $query->where('active', false))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(10);
    }

    /**
     * @return Collection<int, Unit>
     */
    #[Computed]
    public function availableUnits(): Collection
    {
        return Unit::query()
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'clinic_id', 'active']);
    }

    /**
     * @return Collection<int, Sector>
     */
    #[Computed]
    public function availableSectors(): Collection
    {
        if ($this->unitId === null) {
            return new Collection;
        }

        return Sector::query()
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->where('unit_id', $this->unitId)
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'unit_id', 'clinic_id', 'active']);
    }

    public function render(): View
    {
        return view('livewire.kiosks-manager', [
            'kiosks' => $this->kiosks(),
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        $clinicId = auth()->user()?->clinic_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:64',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('kiosks', 'code')
                    ->where(fn ($query) => $query->where('clinic_id', $clinicId))
                    ->ignore($this->editingKioskId),
            ],
            'unitId' => [
                'required',
                'integer',
                Rule::exists('units', 'id')->where(fn ($query) => $query->where('clinic_id', $clinicId)),
            ],
            'sectorId' => [
                'required',
                'integer',
                Rule::exists('sectors', 'id')->where(fn ($query) => $query
                    ->where('clinic_id', $clinicId)
                    ->where('unit_id', $this->unitId)),
            ],
            'active' => ['boolean'],
            'printEnabled' => ['boolean'],
            'printAgentListenMode' => ['required', Rule::in(['local', 'lan'])],
            'printAgentHost' => ['nullable', 'string', 'max:255'],
            'printAgentPort' => ['integer', 'min:1024', 'max:65535'],
            'printPrinterName' => ['nullable', 'string', 'max:255'],
            'printPaperWidth' => ['required', Rule::in(['58', '80'])],
            'printAutoCut' => ['boolean'],
            'printLogo' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do totem.',
            'code.required' => 'Informe o código do totem.',
            'code.regex' => 'Use apenas letras, números, hífen ou sublinhado.',
            'code.unique' => 'Já existe um totem com este código nesta clínica.',
            'unitId.required' => 'Selecione a unidade.',
            'unitId.exists' => 'A unidade selecionada não pertence à sua clínica.',
            'sectorId.required' => 'Selecione o setor.',
            'sectorId.exists' => 'O setor selecionado não pertence à unidade informada.',
            'printAgentListenMode.in' => 'Selecione o modo Local ou LAN.',
        ];
    }

    private function persistPrintSettings(Kiosk $kiosk): void
    {
        $this->authorize('update', $kiosk);

        $printGrants = app(KioskPrintGrantService::class);
        $listenMode = $printGrants->normalizeListenMode($this->printAgentListenMode);
        $agentHost = $printGrants->normalizeAgentHost($this->printAgentHost);

        if ($listenMode === KioskPrintGrantService::LISTEN_LAN && $agentHost === null) {
            $this->printEnabled = false;
            $this->addError('printAgentHost', 'No modo LAN, informe o IP ou hostname da CPU Windows do Totem.');
        }

        if ($this->printEnabled && (! filled($kiosk->print_agent_secret_encrypted) || trim($this->printPrinterName) === '')) {
            $this->printEnabled = false;
        }

        if ($this->printEnabled && $listenMode === KioskPrintGrantService::LISTEN_LAN && $agentHost === null) {
            $this->printEnabled = false;
        }

        $kiosk->forceFill([
            'print_enabled' => $this->printEnabled,
            'print_agent_listen_mode' => $listenMode,
            'print_agent_host' => $listenMode === KioskPrintGrantService::LISTEN_LAN ? $agentHost : null,
            'print_agent_port' => max(1024, min(65535, (int) $this->printAgentPort)),
            'print_printer_name' => trim($this->printPrinterName) !== '' ? trim($this->printPrinterName) : null,
            'print_paper_width' => $printGrants->normalizePaperWidth($this->printPaperWidth),
            'print_auto_cut' => $this->printAutoCut,
            'print_logo' => $this->printLogo,
        ])->save();
    }

    private function kioskForCurrentClinic(int $kioskId): Kiosk
    {
        return Kiosk::query()
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->whereKey($kioskId)
            ->firstOrFail();
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->editingKioskId = null;
        $this->showForm = false;
        $this->name = '';
        $this->code = '';
        $this->unitId = null;
        $this->sectorId = null;
        $this->active = true;
        $this->printEnabled = false;
        $this->printAgentListenMode = 'local';
        $this->printAgentHost = '';
        $this->printAgentPort = KioskPrintGrantService::DEFAULT_PORT;
        $this->printPrinterName = '';
        $this->printPaperWidth = '80';
        $this->printAutoCut = true;
        $this->printLogo = false;
        $this->printIsPaired = false;
        $this->pairingSecretOnce = '';
        $this->printStatusMessage = '';
        $this->printAgentStatus = 'unknown';
        $this->availablePrinters = [];
        $this->printerListGrant = null;
        $this->testPrintGrant = null;
        $this->kioskPendingDeactivationId = null;
        $this->kioskPendingTokenRegenId = null;
    }
}
