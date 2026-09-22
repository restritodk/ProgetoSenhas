<?php

namespace App\Livewire;

use App\Models\Unit;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class UnitsManager extends Component
{
    public string $name = '';

    public string $slug = '';

    public bool $showForm = false;

    public ?int $editingUnitId = null;

    public ?int $unitPendingDeactivationId = null;

    public string $statusMessage = '';

    public function mount(): void
    {
        $this->authorize('manageAny', Unit::class);
    }

    public function startCreate(): void
    {
        $this->authorize('create', Unit::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $unitId): void
    {
        $unit = $this->unitForCurrentClinic($unitId);
        $this->authorize('update', $unit);

        $this->editingUnitId = $unit->id;
        $this->name = $unit->name;
        $this->slug = $unit->slug;
        $this->showForm = true;
        $this->unitPendingDeactivationId = null;
        $this->statusMessage = '';
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        $user = auth()->user();
        abort_if($user?->clinic_id === null, 404);

        $this->slug = $this->slug !== '' ? Str::slug($this->slug) : Str::slug($this->name);

        $this->validate();

        if ($this->editingUnitId !== null) {
            $unit = $this->unitForCurrentClinic($this->editingUnitId);
            $this->authorize('update', $unit);
            $unit->update([
                'name' => $this->name,
                'slug' => $this->slug,
            ]);
            $message = 'Unidade atualizada com sucesso.';
        } else {
            $this->authorize('create', Unit::class);
            Unit::query()->create([
                'clinic_id' => $user->clinic_id,
                'name' => $this->name,
                'slug' => $this->slug,
                'active' => true,
            ]);
            $message = 'Unidade criada com sucesso.';
        }

        $this->resetForm();
        $this->statusMessage = $message;
        unset($this->units);
    }

    public function confirmDeactivation(int $unitId): void
    {
        $unit = $this->unitForCurrentClinic($unitId);
        $this->authorize('update', $unit);
        $this->unitPendingDeactivationId = $unit->id;
    }

    public function cancelDeactivation(): void
    {
        $this->unitPendingDeactivationId = null;
    }

    public function deactivate(): void
    {
        abort_if($this->unitPendingDeactivationId === null, 404);

        $unit = $this->unitForCurrentClinic($this->unitPendingDeactivationId);
        $this->authorize('update', $unit);
        $unit->update(['active' => false]);

        $this->unitPendingDeactivationId = null;
        $this->statusMessage = 'Unidade desativada.';
        unset($this->units);
    }

    public function activate(int $unitId): void
    {
        $unit = $this->unitForCurrentClinic($unitId);
        $this->authorize('update', $unit);
        $unit->update(['active' => true]);

        $this->statusMessage = 'Unidade ativada.';
        unset($this->units);
    }

    /**
     * @return Collection<int, Unit>
     */
    #[Computed]
    public function units(): Collection
    {
        return Unit::query()
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.units-manager');
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('units', 'slug')
                    ->where(fn ($query) => $query->where('clinic_id', auth()->user()?->clinic_id))
                    ->ignore($this->editingUnitId),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.required' => 'Informe o nome da unidade.',
            'name.max' => 'O nome da unidade deve ter no máximo 255 caracteres.',
            'slug.required' => 'Informe o identificador da unidade.',
            'slug.regex' => 'Use apenas letras minúsculas, números e hífens.',
            'slug.unique' => 'Já existe uma unidade com este identificador nesta clínica.',
        ];
    }

    private function unitForCurrentClinic(int $unitId): Unit
    {
        return Unit::query()
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->whereKey($unitId)
            ->firstOrFail();
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->name = '';
        $this->slug = '';
        $this->editingUnitId = null;
        $this->showForm = false;
        $this->unitPendingDeactivationId = null;
    }
}
