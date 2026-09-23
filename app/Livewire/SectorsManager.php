<?php

namespace App\Livewire;

use App\Actions\CreateSector;
use App\Actions\DeleteSector;
use App\Actions\UpdateSector;
use App\Models\Sector;
use App\Models\Unit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class SectorsManager extends Component
{
    use WithPagination;

    public string $search = '';

    public string $unitFilter = '';

    public string $statusFilter = '';

    public bool $showForm = false;

    public ?int $editingSectorId = null;

    public string $name = '';

    public string $code = '';

    public ?int $unitId = null;

    public string $description = '';

    public bool $active = true;

    public ?int $sectorPendingDeactivationId = null;

    public ?int $sectorPendingDeletionId = null;

    public string $statusMessage = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Sector::class);
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
        if ($this->editingSectorId === null) {
            return;
        }

        $sector = Sector::query()
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->whereKey($this->editingSectorId)
            ->first();

        if ($sector !== null && (int) $this->unitId !== (int) $sector->unit_id) {
            $this->resetValidation(['code']);
        }
    }

    public function startCreate(): void
    {
        $this->authorize('create', Sector::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $sectorId): void
    {
        $sector = $this->sectorForCurrentClinic($sectorId);
        $this->authorize('update', $sector);

        $this->editingSectorId = $sector->id;
        $this->name = $sector->name;
        $this->code = $sector->code;
        $this->unitId = $sector->unit_id;
        $this->description = (string) ($sector->description ?? '');
        $this->active = $sector->active;
        $this->showForm = true;
        $this->sectorPendingDeactivationId = null;
        $this->sectorPendingDeletionId = null;
        $this->statusMessage = '';
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(CreateSector $createSector, UpdateSector $updateSector): void
    {
        $actor = auth()->user();
        abort_if($actor?->clinic_id === null, 404);

        $this->code = Str::upper($this->code);
        $this->validate();

        $attributes = [
            'name' => $this->name,
            'code' => $this->code,
            'unit_id' => (int) $this->unitId,
            'description' => trim($this->description) !== '' ? trim($this->description) : null,
            'active' => $this->active,
        ];

        if ($this->editingSectorId !== null) {
            $sector = $this->sectorForCurrentClinic($this->editingSectorId);
            $updateSector->handle($actor, $sector, $attributes);
            $message = 'Setor atualizado com sucesso.';
        } else {
            $createSector->handle($actor, $attributes);
            $message = 'Setor criado com sucesso.';
        }

        $this->resetForm();
        $this->statusMessage = $message;
        $this->resetPage();
    }

    public function confirmDeactivation(int $sectorId): void
    {
        $sector = $this->sectorForCurrentClinic($sectorId);
        $this->authorize('update', $sector);
        $this->sectorPendingDeactivationId = $sector->id;
    }

    public function cancelDeactivation(): void
    {
        $this->sectorPendingDeactivationId = null;
    }

    public function deactivate(UpdateSector $updateSector): void
    {
        abort_if($this->sectorPendingDeactivationId === null, 404);

        $actor = auth()->user();
        $sector = $this->sectorForCurrentClinic($this->sectorPendingDeactivationId);
        $this->authorize('update', $sector);

        $updateSector->handle($actor, $sector, [
            'name' => $sector->name,
            'code' => $sector->code,
            'unit_id' => $sector->unit_id,
            'description' => $sector->description,
            'active' => false,
        ]);

        $this->sectorPendingDeactivationId = null;
        $this->statusMessage = 'Setor desativado.';
        $this->resetPage();
    }

    public function activate(int $sectorId, UpdateSector $updateSector): void
    {
        $actor = auth()->user();
        $sector = $this->sectorForCurrentClinic($sectorId);
        $this->authorize('update', $sector);

        $updateSector->handle($actor, $sector, [
            'name' => $sector->name,
            'code' => $sector->code,
            'unit_id' => $sector->unit_id,
            'description' => $sector->description,
            'active' => true,
        ]);

        $this->statusMessage = 'Setor ativado.';
        $this->resetPage();
    }

    public function confirmDeletion(int $sectorId): void
    {
        $sector = $this->sectorForCurrentClinic($sectorId);
        $this->authorize('delete', $sector);
        $this->sectorPendingDeletionId = $sector->id;
    }

    public function cancelDeletion(): void
    {
        $this->sectorPendingDeletionId = null;
    }

    public function delete(DeleteSector $deleteSector): void
    {
        abort_if($this->sectorPendingDeletionId === null, 404);

        $actor = auth()->user();
        $sector = $this->sectorForCurrentClinic($this->sectorPendingDeletionId);
        $this->authorize('delete', $sector);

        try {
            $deleteSector->handle($actor, $sector);
            $this->statusMessage = 'Setor excluído.';
        } catch (ValidationException $exception) {
            $this->statusMessage = '';
            $this->addError('sector', collect($exception->errors())->flatten()->first() ?? 'Não foi possível excluir o setor.');
        }

        $this->sectorPendingDeletionId = null;
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Sector>
     */
    public function sectors(): LengthAwarePaginator
    {
        $clinicId = auth()->user()?->clinic_id;

        return Sector::query()
            ->with(['unit:id,name,clinic_id'])
            ->withCount('desks')
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
            ->orderBy('unit_id')
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
        return view('livewire.sectors-manager', [
            'sectors' => $this->sectors(),
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
                'max:32',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('sectors', 'code')
                    ->where(fn ($query) => $query->where('clinic_id', $clinicId)->where('unit_id', $this->unitId))
                    ->ignore($this->editingSectorId),
            ],
            'unitId' => [
                'required',
                'integer',
                Rule::exists('units', 'id')->where(fn ($query) => $query->where('clinic_id', $clinicId)),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do setor.',
            'code.required' => 'Informe o código do setor.',
            'code.regex' => 'Use apenas letras, números, hífen ou sublinhado.',
            'code.unique' => 'Já existe um setor com este código nesta unidade.',
            'unitId.required' => 'Selecione a unidade.',
            'unitId.exists' => 'A unidade selecionada não pertence à sua clínica.',
        ];
    }

    private function sectorForCurrentClinic(int $sectorId): Sector
    {
        return Sector::query()
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->whereKey($sectorId)
            ->firstOrFail();
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->editingSectorId = null;
        $this->showForm = false;
        $this->name = '';
        $this->code = '';
        $this->unitId = null;
        $this->description = '';
        $this->active = true;
        $this->sectorPendingDeactivationId = null;
        $this->sectorPendingDeletionId = null;
    }
}
