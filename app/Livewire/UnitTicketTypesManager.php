<?php

namespace App\Livewire;

use App\Actions\SyncUnitTicketTypes;
use App\Models\TicketType;
use App\Models\Unit;
use App\Models\UnitTicketType;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class UnitTicketTypesManager extends Component
{
    public ?int $unitId = null;

    /** @var array<int, array{ticket_type_id: int, active: bool, display_name: string, position: int}> */
    public array $rows = [];

    public string $statusMessage = '';

    public function mount(): void
    {
        $this->authorize('manageAny', Unit::class);

        $units = $this->availableUnits;
        if ($units->count() === 1) {
            $this->unitId = $units->first()->id;
            $this->loadRows();
        }
    }

    public function updatedUnitId(): void
    {
        $this->statusMessage = '';
        $this->loadRows();
    }

    public function save(SyncUnitTicketTypes $syncUnitTicketTypes): void
    {
        $unit = $this->selectedUnit();
        abort_if($unit === null, 404);
        $this->authorize('manageTicketTypes', $unit);

        $this->validate([
            'rows' => ['array'],
            'rows.*.ticket_type_id' => ['required', 'integer'],
            'rows.*.active' => ['boolean'],
            'rows.*.display_name' => ['nullable', 'string', 'max:255'],
            'rows.*.position' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);

        $payload = collect($this->rows)
            ->map(fn (array $row): array => [
                'ticket_type_id' => (int) $row['ticket_type_id'],
                'active' => (bool) ($row['active'] ?? false),
                'display_name' => $row['display_name'] !== '' ? $row['display_name'] : null,
                'position' => (int) $row['position'],
            ])
            ->values()
            ->all();

        $syncUnitTicketTypes->handle(auth()->user(), $unit, $payload);

        $this->statusMessage = 'Tipos de senha da unidade atualizados.';
        $this->loadRows();
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
        return view('livewire.unit-ticket-types-manager');
    }

    private function selectedUnit(): ?Unit
    {
        if ($this->unitId === null) {
            return null;
        }

        return Unit::query()
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->whereKey($this->unitId)
            ->first();
    }

    private function loadRows(): void
    {
        $unit = $this->selectedUnit();

        if ($unit === null) {
            $this->rows = [];

            return;
        }

        $existing = UnitTicketType::query()
            ->where('clinic_id', $unit->clinic_id)
            ->where('unit_id', $unit->id)
            ->get()
            ->keyBy('ticket_type_id');

        $types = TicketType::query()
            ->where('clinic_id', $unit->clinic_id)
            ->orderByDesc('priority')
            ->orderBy('name')
            ->get(['id', 'name', 'prefix', 'priority', 'active']);

        $this->rows = $types->values()->map(function (TicketType $type, int $index) use ($existing): array {
            $row = $existing->get($type->id);

            return [
                'ticket_type_id' => $type->id,
                'type_name' => $type->name,
                'type_prefix' => $type->prefix,
                'type_active' => $type->active,
                'active' => $row?->active ?? false,
                'display_name' => $row?->display_name ?? '',
                'position' => $row?->position ?? (($index + 1) * 10),
            ];
        })->all();
    }
}
