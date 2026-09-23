<?php

namespace App\Livewire;

use App\Actions\CreateTicketType;
use App\Actions\DeleteTicketType;
use App\Actions\UpdateTicketType;
use App\Models\TicketType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

    public ?int $ticketTypePendingActivationId = null;

    public ?int $ticketTypePendingDeletionId = null;

    public bool $showDeleteBlockedModal = false;

    public string $deleteBlockedName = '';

    public string $statusMessage = '';

    public string $errorMessage = '';

    public bool $actionProcessing = false;

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

    public function clearStatusMessage(): void
    {
        $this->statusMessage = '';
        $this->errorMessage = '';
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
        $this->clearPendingDialogs();
        $this->statusMessage = '';
        $this->errorMessage = '';
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
            $message = 'Alterações salvas com sucesso.';
        } else {
            $createTicketType->handle($actor, $attributes);
            $message = 'Tipo de senha criado com sucesso.';
        }

        $this->resetForm();
        $this->statusMessage = $message;
        $this->errorMessage = '';
        $this->resetPage();
    }

    public function confirmDeactivation(int $ticketTypeId): void
    {
        $ticketType = $this->ticketTypeForCurrentClinic($ticketTypeId);
        $this->authorize('update', $ticketType);
        $this->clearPendingDialogs();
        $this->ticketTypePendingDeactivationId = $ticketType->id;
    }

    public function cancelDeactivation(): void
    {
        $this->ticketTypePendingDeactivationId = null;
        $this->actionProcessing = false;
    }

    public function deactivate(UpdateTicketType $updateTicketType): void
    {
        abort_if($this->ticketTypePendingDeactivationId === null, 404);
        if ($this->actionProcessing) {
            return;
        }

        $this->actionProcessing = true;

        try {
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
            $this->statusMessage = 'Tipo de senha desativado com sucesso.';
            $this->errorMessage = '';
            $this->resetPage();
        } finally {
            $this->actionProcessing = false;
        }
    }

    public function confirmActivation(int $ticketTypeId): void
    {
        $ticketType = $this->ticketTypeForCurrentClinic($ticketTypeId);
        $this->authorize('update', $ticketType);
        $this->clearPendingDialogs();
        $this->ticketTypePendingActivationId = $ticketType->id;
    }

    public function cancelActivation(): void
    {
        $this->ticketTypePendingActivationId = null;
        $this->actionProcessing = false;
    }

    public function activate(UpdateTicketType $updateTicketType): void
    {
        abort_if($this->ticketTypePendingActivationId === null, 404);
        if ($this->actionProcessing) {
            return;
        }

        $this->actionProcessing = true;

        try {
            $actor = auth()->user();
            $ticketType = $this->ticketTypeForCurrentClinic($this->ticketTypePendingActivationId);
            $this->authorize('update', $ticketType);

            $updateTicketType->handle($actor, $ticketType, [
                'name' => $ticketType->name,
                'prefix' => $ticketType->prefix,
                'priority' => $ticketType->priority,
                'active' => true,
            ]);

            $this->ticketTypePendingActivationId = null;
            $this->statusMessage = 'Tipo de senha ativado com sucesso.';
            $this->errorMessage = '';
            $this->resetPage();
        } finally {
            $this->actionProcessing = false;
        }
    }

    public function confirmDeletion(int $ticketTypeId, DeleteTicketType $deleteTicketType): void
    {
        $ticketType = $this->ticketTypeForCurrentClinic($ticketTypeId);
        $this->authorize('delete', $ticketType);
        $this->clearPendingDialogs();

        if ($deleteTicketType->hasOperationalHistory($ticketType)) {
            $this->deleteBlockedName = $ticketType->name;
            $this->showDeleteBlockedModal = true;

            return;
        }

        $this->ticketTypePendingDeletionId = $ticketType->id;
    }

    public function cancelDeletion(): void
    {
        $this->ticketTypePendingDeletionId = null;
        $this->actionProcessing = false;
    }

    public function dismissDeleteBlocked(): void
    {
        $this->showDeleteBlockedModal = false;
        $this->deleteBlockedName = '';
    }

    public function delete(DeleteTicketType $deleteTicketType): void
    {
        abort_if($this->ticketTypePendingDeletionId === null, 404);
        if ($this->actionProcessing) {
            return;
        }

        $this->actionProcessing = true;
        $ticketType = $this->ticketTypeForCurrentClinic($this->ticketTypePendingDeletionId);

        try {
            $actor = auth()->user();
            $deleteTicketType->handle($actor, $ticketType);

            $this->ticketTypePendingDeletionId = null;
            $this->statusMessage = 'Tipo de senha excluído com sucesso.';
            $this->errorMessage = '';
            $this->resetPage();
        } catch (ValidationException $exception) {
            $this->ticketTypePendingDeletionId = null;
            $this->deleteBlockedName = $ticketType->name;
            $this->showDeleteBlockedModal = true;
            $this->errorMessage = collect($exception->errors())->flatten()->first()
                ?? 'Não foi possível excluir este tipo.';
        } finally {
            $this->actionProcessing = false;
        }
    }

    /**
     * @return array{total: int, active: int, inactive: int}
     */
    public function summaryCounts(): array
    {
        $clinicId = auth()->user()?->clinic_id;
        $base = TicketType::query()->where('clinic_id', $clinicId);

        $total = (clone $base)->count();
        $active = (clone $base)->where('active', true)->count();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => max(0, $total - $active),
        ];
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
        $pendingDeactivation = $this->ticketTypePendingDeactivationId !== null
            ? $this->ticketTypeForCurrentClinic($this->ticketTypePendingDeactivationId)
            : null;
        $pendingActivation = $this->ticketTypePendingActivationId !== null
            ? $this->ticketTypeForCurrentClinic($this->ticketTypePendingActivationId)
            : null;
        $pendingDeletion = $this->ticketTypePendingDeletionId !== null
            ? $this->ticketTypeForCurrentClinic($this->ticketTypePendingDeletionId)
            : null;

        return view('livewire.ticket-types-manager', [
            'ticketTypes' => $this->ticketTypes(),
            'summary' => $this->summaryCounts(),
            'pendingDeactivation' => $pendingDeactivation,
            'pendingActivation' => $pendingActivation,
            'pendingDeletion' => $pendingDeletion,
            'unitTicketTypesUrl' => route('unit-ticket-types.index'),
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

    private function clearPendingDialogs(): void
    {
        $this->ticketTypePendingDeactivationId = null;
        $this->ticketTypePendingActivationId = null;
        $this->ticketTypePendingDeletionId = null;
        $this->showDeleteBlockedModal = false;
        $this->deleteBlockedName = '';
        $this->actionProcessing = false;
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
        $this->clearPendingDialogs();
    }
}
