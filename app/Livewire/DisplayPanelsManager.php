<?php

namespace App\Livewire;

use App\Actions\CreateDisplayPanel;
use App\Actions\RegenerateDisplayPanelToken;
use App\Actions\UpdateDisplayPanel;
use App\Models\DisplayPanel;
use App\Models\Unit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class DisplayPanelsManager extends Component
{
    use WithPagination;

    public string $search = '';

    public string $unitFilter = '';

    public string $statusFilter = '';

    public bool $showForm = false;

    public ?int $editingPanelId = null;

    public string $name = '';

    public string $code = '';

    public ?int $unitId = null;

    public bool $active = true;

    public ?int $panelPendingDeactivationId = null;

    public ?int $panelPendingTokenRegenId = null;

    public string $statusMessage = '';

    public string $copiedUrlPanelId = '';

    public function mount(): void
    {
        $this->authorize('viewAny', DisplayPanel::class);
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

    public function startCreate(): void
    {
        $this->authorize('create', DisplayPanel::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $panelId): void
    {
        $panel = $this->panelForCurrentClinic($panelId);
        $this->authorize('update', $panel);

        $this->editingPanelId = $panel->id;
        $this->name = $panel->name;
        $this->code = $panel->code;
        $this->unitId = $panel->unit_id;
        $this->active = $panel->active;
        $this->showForm = true;
        $this->panelPendingDeactivationId = null;
        $this->panelPendingTokenRegenId = null;
        $this->statusMessage = '';
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(CreateDisplayPanel $createDisplayPanel, UpdateDisplayPanel $updateDisplayPanel): void
    {
        $actor = auth()->user();
        abort_if($actor?->clinic_id === null, 404);

        $this->code = Str::upper($this->code);
        $this->validate();

        $attributes = [
            'name' => $this->name,
            'code' => $this->code,
            'unit_id' => (int) $this->unitId,
            'active' => $this->active,
        ];

        if ($this->editingPanelId !== null) {
            $panel = $this->panelForCurrentClinic($this->editingPanelId);
            $updateDisplayPanel->handle($actor, $panel, $attributes);
            $message = 'Painel/TV atualizado com sucesso.';
        } else {
            $createDisplayPanel->handle($actor, $attributes);
            $message = 'Painel/TV criado com sucesso.';
        }

        $this->resetForm();
        $this->statusMessage = $message;
        $this->resetPage();
    }

    public function confirmDeactivation(int $panelId): void
    {
        $panel = $this->panelForCurrentClinic($panelId);
        $this->authorize('update', $panel);
        $this->panelPendingDeactivationId = $panel->id;
    }

    public function cancelDeactivation(): void
    {
        $this->panelPendingDeactivationId = null;
    }

    public function deactivate(UpdateDisplayPanel $updateDisplayPanel): void
    {
        abort_if($this->panelPendingDeactivationId === null, 404);

        $actor = auth()->user();
        $panel = $this->panelForCurrentClinic($this->panelPendingDeactivationId);
        $this->authorize('update', $panel);

        $updateDisplayPanel->handle($actor, $panel, [
            'name' => $panel->name,
            'code' => $panel->code,
            'unit_id' => $panel->unit_id,
            'active' => false,
        ]);

        $this->panelPendingDeactivationId = null;
        $this->statusMessage = 'Painel/TV desativado.';
        $this->resetPage();
    }

    public function activate(int $panelId, UpdateDisplayPanel $updateDisplayPanel): void
    {
        $actor = auth()->user();
        $panel = $this->panelForCurrentClinic($panelId);
        $this->authorize('update', $panel);

        $updateDisplayPanel->handle($actor, $panel, [
            'name' => $panel->name,
            'code' => $panel->code,
            'unit_id' => $panel->unit_id,
            'active' => true,
        ]);

        $this->statusMessage = 'Painel/TV ativado.';
        $this->resetPage();
    }

    public function confirmTokenRegen(int $panelId): void
    {
        $panel = $this->panelForCurrentClinic($panelId);
        $this->authorize('regenerateToken', $panel);
        $this->panelPendingTokenRegenId = $panel->id;
    }

    public function cancelTokenRegen(): void
    {
        $this->panelPendingTokenRegenId = null;
    }

    public function regenerateToken(RegenerateDisplayPanelToken $regenerateDisplayPanelToken): void
    {
        abort_if($this->panelPendingTokenRegenId === null, 404);

        $actor = auth()->user();
        $panel = $this->panelForCurrentClinic($this->panelPendingTokenRegenId);
        $this->authorize('regenerateToken', $panel);

        $regenerateDisplayPanelToken->handle($actor, $panel);

        $this->panelPendingTokenRegenId = null;
        $this->statusMessage = 'Token do painel regenerado. A URL anterior deixou de funcionar.';
        $this->resetPage();
    }

    public function markUrlCopied(int $panelId): void
    {
        $this->panelForCurrentClinic($panelId);
        $this->copiedUrlPanelId = (string) $panelId;
    }

    /**
     * @return LengthAwarePaginator<int, DisplayPanel>
     */
    public function panels(): LengthAwarePaginator
    {
        $clinicId = auth()->user()?->clinic_id;

        return DisplayPanel::query()
            ->with(['unit:id,name,clinic_id'])
            ->where('clinic_id', $clinicId)
            ->when($this->search !== '', function ($query): void {
                $term = '%'.Str::lower($this->search).'%';
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

    public function render(): View
    {
        return view('livewire.display-panels-manager', [
            'panels' => $this->panels(),
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
                Rule::unique('display_panels', 'code')
                    ->where(fn ($query) => $query->where('clinic_id', $clinicId))
                    ->ignore($this->editingPanelId),
            ],
            'unitId' => [
                'required',
                'integer',
                Rule::exists('units', 'id')->where(fn ($query) => $query->where('clinic_id', $clinicId)),
            ],
            'active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do painel.',
            'code.required' => 'Informe o código do painel.',
            'code.regex' => 'Use apenas letras, números, hífen ou sublinhado.',
            'code.unique' => 'Já existe um painel com este código nesta clínica.',
            'unitId.required' => 'Selecione a unidade.',
            'unitId.exists' => 'A unidade selecionada não pertence à sua clínica.',
        ];
    }

    private function panelForCurrentClinic(int $panelId): DisplayPanel
    {
        return DisplayPanel::query()
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->whereKey($panelId)
            ->firstOrFail();
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->editingPanelId = null;
        $this->showForm = false;
        $this->name = '';
        $this->code = '';
        $this->unitId = null;
        $this->active = true;
        $this->panelPendingDeactivationId = null;
        $this->panelPendingTokenRegenId = null;
    }
}
