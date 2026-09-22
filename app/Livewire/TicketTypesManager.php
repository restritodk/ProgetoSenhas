<?php

namespace App\Livewire;

use App\Actions\CreateTicketType;
use App\Actions\UpdateTicketType;
use App\Models\TicketType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class TicketTypesManager extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public bool $showForm = false;

    public ?int $editingTicketTypeId = null;

    public string $name = '';

    public string $prefix = '';

    public string $priority = '10';

    public bool $active = true;

    public ?int $ticketTypePendingDeactivationId = null;

    public string $statusMessage = '';

    public function mount(): void
    {
        $this->authorize('viewAny', TicketType::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function startCreate(): void
    {
        $this->authorize('create', TicketType::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $ticketTypeId): void
    {
        $ticketType = $this->ticketTypeForCurrentClinic($ticketTypeId);
        $this->authorize('update', $ticketType);

        $this->editingTicketTypeId = $ticketType->id;
        $this->name = $ticketType->name;
        $this->prefix = $ticketType->prefix;
        $this->priority = (string) $ticketType->priority;
        $this->active = $ticketType->active;
        $this->showForm = true;
        $this->ticketTypePendingDeactivationId = null;
        $this->statusMessage = '';
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(CreateTicketType $createTicketType, UpdateTicketType $updateTicketType): void
    {
        $actor = auth()->user();
        abort_if($actor?->clinic_id === null, 404);

        $this->prefix = Str::upper($this->prefix);
        $this->validate();

        $attributes = [
            'name' => $this->name,
            'prefix' => $this->prefix,
            'priority' => (int) $this->priority,
            'active' => $this->active,
        ];

        if ($this->editingTicketTypeId !== null) {
            $ticketType = $this->ticketTypeForCurrentClinic($this->editingTicketTypeId);
            $updateTicketType->handle($actor, $ticketType, $attributes);
            $message = 'Tipo de senha atualizado com sucesso.';
        } else {
            $createTicketType->handle($actor, $attributes);
            $message = 'Tipo de senha criado com sucesso.';
        }

        $this->resetForm();
        $this->statusMessage = $message;
        $this->resetPage();
    }

    public function confirmDeactivation(int $ticketTypeId): void
    {
        $ticketType = $this->ticketTypeForCurrentClinic($ticketTypeId);
        $this->authorize('update', $ticketType);
        $this->ticketTypePendingDeactivationId = $ticketType->id;
    }

    public function cancelDeactivation(): void
    {
        $this->ticketTypePendingDeactivationId = null;
    }

    public function deactivate(UpdateTicketType $updateTicketType): void
    {
        abort_if($this->ticketTypePendingDeactivationId === null, 404);

        $actor = auth()->user();
        $ticketType = $this->ticketTypeForCurrentClinic($this->ticketTypePendingDeactivationId);
        $this->authorize('update', $ticketType);

        $updateTicketType->handle($actor, $ticketType, [
            'name' => $ticketType->name,
            'prefix' => $ticketType->prefix,
            'priority' => $ticketType->priority,
            'active' => false,
        ]);

        $this->ticketTypePendingDeactivationId = null;
        $this->statusMessage = 'Tipo de senha desativado.';
        $this->resetPage();
    }

    public function activate(int $ticketTypeId, UpdateTicketType $updateTicketType): void
    {
        $actor = auth()->user();
        $ticketType = $this->ticketTypeForCurrentClinic($ticketTypeId);
        $this->authorize('update', $ticketType);

        $updateTicketType->handle($actor, $ticketType, [
            'name' => $ticketType->name,
            'prefix' => $ticketType->prefix,
            'priority' => $ticketType->priority,
            'active' => true,
        ]);

        $this->statusMessage = 'Tipo de senha ativado.';
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, TicketType>
     */
    public function ticketTypes(): LengthAwarePaginator
    {
        $clinicId = auth()->user()?->clinic_id;

        return TicketType::query()
            ->where('clinic_id', $clinicId)
            ->when($this->search !== '', function ($query): void {
                $term = '%'.Str::lower($this->search).'%';
                $query->where(function ($search) use ($term): void {
                    $search->whereRaw('LOWER(name) like ?', [$term])
                        ->orWhereRaw('LOWER(prefix) like ?', [$term]);
                });
            })
            ->when($this->statusFilter === 'active', fn ($query) => $query->where('active', true))
            ->when($this->statusFilter === 'inactive', fn ($query) => $query->where('active', false))
            ->orderByDesc('priority')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(10);
    }

    public function render(): View
    {
        return view('livewire.ticket-types-manager', [
            'ticketTypes' => $this->ticketTypes(),
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
            'prefix' => [
                'required',
                'string',
                'min:1',
                'max:8',
                'regex:/^[A-Z0-9]+$/',
                Rule::unique('ticket_types', 'prefix')
                    ->where(fn ($query) => $query->where('clinic_id', $clinicId))
                    ->ignore($this->editingTicketTypeId),
            ],
            'priority' => ['required', 'integer', 'min:1', 'max:9999'],
            'active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do tipo de senha.',
            'prefix.required' => 'Informe o prefixo.',
            'prefix.regex' => 'O prefixo deve conter apenas letras e números.',
            'prefix.unique' => 'Já existe um tipo com este prefixo nesta clínica.',
            'priority.required' => 'Informe a prioridade.',
            'priority.integer' => 'A prioridade deve ser um número inteiro.',
            'priority.min' => 'A prioridade mínima é 1.',
            'priority.max' => 'A prioridade máxima é 9999.',
        ];
    }

    private function ticketTypeForCurrentClinic(int $ticketTypeId): TicketType
    {
        return TicketType::query()
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->whereKey($ticketTypeId)
            ->firstOrFail();
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->editingTicketTypeId = null;
        $this->showForm = false;
        $this->name = '';
        $this->prefix = '';
        $this->priority = '10';
        $this->active = true;
        $this->ticketTypePendingDeactivationId = null;
    }
}
