<?php

namespace App\Livewire;

use App\Actions\CreateDesk;
use App\Actions\UpdateDesk;
use App\Models\Desk;
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

class DesksManager extends Component
{
    use WithPagination;

    public string $search = '';

    public string $unitFilter = '';

    public string $statusFilter = '';

    public bool $showForm = false;

    public ?int $editingDeskId = null;

    public string $name = '';

    public string $code = '';

    public ?int $unitId = null;

    public bool $active = true;

    public ?int $deskPendingDeactivationId = null;

    public string $statusMessage = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Desk::class);
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
        $this->authorize('create', Desk::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $deskId): void
    {
        $desk = $this->deskForCurrentClinic($deskId);
        $this->authorize('update', $desk);

        $this->editingDeskId = $desk->id;
        $this->name = $desk->name;
        $this->code = $desk->code;
        $this->unitId = $desk->unit_id;
        $this->active = $desk->active;
        $this->showForm = true;
        $this->deskPendingDeactivationId = null;
        $this->statusMessage = '';
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(CreateDesk $createDesk, UpdateDesk $updateDesk): void
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

        if ($this->editingDeskId !== null) {
            $desk = $this->deskForCurrentClinic($this->editingDeskId);
            $updateDesk->handle($actor, $desk, $attributes);
            $message = 'Mesa/guichê atualizado com sucesso.';
        } else {
            $createDesk->handle($actor, $attributes);
            $message = 'Mesa/guichê criado com sucesso.';
        }

        $this->resetForm();
        $this->statusMessage = $message;
        $this->resetPage();
    }

    public function confirmDeactivation(int $deskId): void
    {
        $desk = $this->deskForCurrentClinic($deskId);
        $this->authorize('update', $desk);
        $this->deskPendingDeactivationId = $desk->id;
    }

    public function cancelDeactivation(): void
    {
        $this->deskPendingDeactivationId = null;
    }

    public function deactivate(UpdateDesk $updateDesk): void
    {
        abort_if($this->deskPendingDeactivationId === null, 404);

        $actor = auth()->user();
        $desk = $this->deskForCurrentClinic($this->deskPendingDeactivationId);
        $this->authorize('update', $desk);

        try {
            $updateDesk->handle($actor, $desk, [
                'name' => $desk->name,
                'code' => $desk->code,
                'unit_id' => $desk->unit_id,
                'active' => false,
            ]);
        } catch (ValidationException $exception) {
            $this->deskPendingDeactivationId = null;
            $this->statusMessage = '';
            $this->addError('active', collect($exception->errors())->flatten()->first() ?? 'Não foi possível desativar a mesa.');

            return;
        }

        $this->deskPendingDeactivationId = null;
        $this->statusMessage = 'Mesa/guichê desativado.';
        $this->resetPage();
    }

    public function activate(int $deskId, UpdateDesk $updateDesk): void
    {
        $actor = auth()->user();
        $desk = $this->deskForCurrentClinic($deskId);
        $this->authorize('update', $desk);

        $updateDesk->handle($actor, $desk, [
            'name' => $desk->name,
            'code' => $desk->code,
            'unit_id' => $desk->unit_id,
            'active' => true,
        ]);

        $this->statusMessage = 'Mesa/guichê ativado.';
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Desk>
     */
    public function desks(): LengthAwarePaginator
    {
        $clinicId = auth()->user()?->clinic_id;

        return Desk::query()
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
        return view('livewire.desks-manager', [
            'desks' => $this->desks(),
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
                Rule::unique('desks', 'code')
                    ->where(fn ($query) => $query->where('unit_id', $this->unitId))
                    ->ignore($this->editingDeskId),
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
            'name.required' => 'Informe o nome da mesa/guichê.',
            'code.required' => 'Informe o código da mesa/guichê.',
            'code.regex' => 'Use apenas letras, números, hífen ou sublinhado.',
            'code.unique' => 'Já existe uma mesa/guichê com este código nesta unidade.',
            'unitId.required' => 'Selecione a unidade.',
            'unitId.exists' => 'A unidade selecionada não pertence à sua clínica.',
        ];
    }

    private function deskForCurrentClinic(int $deskId): Desk
    {
        return Desk::query()
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->whereKey($deskId)
            ->firstOrFail();
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->editingDeskId = null;
        $this->showForm = false;
        $this->name = '';
        $this->code = '';
        $this->unitId = null;
        $this->active = true;
        $this->deskPendingDeactivationId = null;
    }
}
