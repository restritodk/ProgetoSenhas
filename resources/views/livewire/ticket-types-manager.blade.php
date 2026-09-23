<div>
    @if ($statusMessage !== '')
        <div
            x-data="{ show: true }"
            x-init="setTimeout(() => { show = false; $wire.clearStatusMessage() }, 4500)"
            x-show="show"
            x-transition.opacity
            role="status"
            aria-live="polite"
            class="mb-4"
        >
            <x-ui.alert type="success">✓ {{ $statusMessage }}</x-ui.alert>
        </div>
    @endif

    @if ($errorMessage !== '')
        <div class="mb-4" role="alert" aria-live="assertive">
            <x-ui.alert type="danger">{{ $errorMessage }}</x-ui.alert>
        </div>
    @endif

    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="max-w-2xl">
            <h2 class="text-xl font-semibold tracking-tight text-text">Tipos de Senha</h2>
            <p class="mt-1 text-sm text-text-muted">
                Configure os tipos de atendimento, prefixos e prioridades utilizados nas filas da clínica.
                Maior número = maior prioridade.
            </p>
            <p class="mt-2 text-sm text-text-muted">
                Quais tipos aparecem no Totem são definidos em
                <a href="{{ $unitTicketTypesUrl }}" class="font-medium text-accent underline-offset-2 hover:underline">Tipos por Unidade</a>.
            </p>
        </div>
        <x-ui.button wire:click="startCreate" wire:loading.attr="disabled" class="shrink-0">
            <x-admin.icon name="plus" class="size-4" />
            Novo tipo de senha
        </x-ui.button>
    </div>

    <div class="mb-6 grid gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-border bg-surface px-4 py-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Tipos cadastrados</p>
            <p class="mt-2 text-2xl font-semibold tabular-nums text-text">{{ $summary['total'] }}</p>
        </div>
        <div class="rounded-2xl border border-border bg-surface px-4 py-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Ativos</p>
            <p class="mt-2 text-2xl font-semibold tabular-nums text-success">{{ $summary['active'] }}</p>
        </div>
        <div class="rounded-2xl border border-border bg-surface px-4 py-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Inativos</p>
            <p class="mt-2 text-2xl font-semibold tabular-nums text-text-muted">{{ $summary['inactive'] }}</p>
        </div>
    </div>

    <x-ui.card>
        <div class="mb-4 grid gap-3 md:grid-cols-2">
            <x-ui.input label="Buscar" name="ticket_type_search" id="ticket_type_search" wire:model.live.debounce.400ms="search" placeholder="Nome ou prefixo" />
            <x-ui.select label="Status" name="ticket_type_status_filter" id="ticket_type_status_filter" wire:model.live="statusFilter">
                <option value="">Todos</option>
                <option value="active">Ativos</option>
                <option value="inactive">Inativos</option>
            </x-ui.select>
        </div>

        <div wire:loading.flex wire:target="search,statusFilter" class="mb-4 items-center gap-2 text-sm text-text-muted">
            <span class="inline-block size-4 animate-spin rounded-full border-2 border-border border-t-accent" aria-hidden="true"></span>
            Atualizando lista…
        </div>

        @if ($showForm)
            <form wire:submit="save" class="mb-6 grid gap-4 rounded-2xl border border-border bg-background p-5 md:grid-cols-2">
                <div class="md:col-span-2">
                    <h3 class="text-sm font-semibold text-text">
                        {{ $editingTicketTypeId ? 'Editar tipo de senha' : 'Novo tipo de senha' }}
                    </h3>
                </div>
                <x-ui.input label="Nome" name="ticket_type_name" id="ticket_type_name" wire:model="name" required maxlength="255">
                    <x-input-error :messages="$errors->get('name')" />
                </x-ui.input>
                <x-ui.input label="Prefixo" name="ticket_type_prefix" id="ticket_type_prefix" wire:model="prefix" required maxlength="8" placeholder="Ex.: N">
                    <p class="mt-1 text-xs text-text-muted">Único na clínica. Será convertido para maiúsculas.</p>
                    <x-input-error :messages="$errors->get('prefix')" />
                </x-ui.input>
                <x-ui.input label="Prioridade" name="ticket_type_priority" id="ticket_type_priority" type="number" wire:model="priority" required min="1" max="9999">
                    <p class="mt-1 text-xs text-text-muted">Número maior = maior prioridade na fila.</p>
                    <x-input-error :messages="$errors->get('priority')" />
                </x-ui.input>
                <div class="flex items-end">
                    <label class="flex min-h-11 items-center gap-3 text-sm text-text">
                        <input type="checkbox" wire:model="active" class="size-4 rounded border-border text-accent">
                        Ativo
                    </label>
                </div>
                <div class="flex flex-wrap gap-3 md:col-span-2">
                    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">{{ $editingTicketTypeId ? 'Salvar alterações' : 'Criar tipo' }}</span>
                        <span wire:loading wire:target="save">Salvando...</span>
                    </x-ui.button>
                    <x-ui.button variant="secondary" wire:click="cancel" wire:loading.attr="disabled" wire:target="save">Cancelar</x-ui.button>
                </div>
            </form>
        @endif

        @if ($ticketTypes->isEmpty() && ! $showForm)
            <x-ui.empty-state
                title="{{ $search !== '' || $statusFilter !== '' ? 'Nenhum resultado encontrado' : 'Nenhum tipo de senha cadastrado' }}"
                description="{{ $search !== '' || $statusFilter !== '' ? 'Ajuste a busca ou o filtro de status e tente novamente.' : 'Cadastre o primeiro tipo de atendimento da clínica.' }}"
            >
                @if ($search === '' && $statusFilter === '')
                    <x-ui.button wire:click="startCreate">Novo tipo de senha</x-ui.button>
                @endif
            </x-ui.empty-state>
        @else
            {{-- Desktop / tablet table --}}
            <div class="hidden overflow-visible md:block">
                <table class="min-w-full text-left text-sm">
                    <caption class="sr-only">Tipos de senha da clínica</caption>
                    <thead class="border-b border-border text-xs uppercase tracking-wide text-text-muted">
                        <tr>
                            <th scope="col" class="px-3 py-3 font-semibold">Tipo</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Prefixo</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Prioridade</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Status</th>
                            <th scope="col" class="px-3 py-3 text-right font-semibold">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ticketTypes as $ticketType)
                            <tr wire:key="ticket-type-row-{{ $ticketType->id }}" class="border-b border-border/70">
                                <td class="px-3 py-3">
                                    <p class="font-medium text-text">{{ $ticketType->name }}</p>
                                </td>
                                <td class="px-3 py-3">
                                    <x-ui.badge tone="accent">{{ $ticketType->prefix }}</x-ui.badge>
                                </td>
                                <td class="px-3 py-3">
                                    <span class="font-medium tabular-nums text-text">{{ $ticketType->priority }}</span>
                                    <span class="ml-1 text-xs text-text-muted">na fila</span>
                                </td>
                                <td class="px-3 py-3">
                                    @if ($ticketType->active)
                                        <span class="inline-flex items-center gap-1.5 text-sm font-medium text-success">
                                            <span class="size-2 rounded-full bg-success" aria-hidden="true"></span>
                                            Ativo
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 text-sm font-medium text-text-muted">
                                            <span class="size-2 rounded-full bg-text-muted/50" aria-hidden="true"></span>
                                            Inativo
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-right">
                                    @include('livewire.partials.ticket-type-actions-menu', ['ticketType' => $ticketType])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile cards --}}
            <div class="space-y-3 md:hidden">
                @foreach ($ticketTypes as $ticketType)
                    <article wire:key="ticket-type-card-{{ $ticketType->id }}" class="rounded-2xl border border-border bg-background p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-text">{{ $ticketType->name }}</p>
                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    <x-ui.badge tone="accent">{{ $ticketType->prefix }}</x-ui.badge>
                                    <span class="text-xs text-text-muted">Prioridade {{ $ticketType->priority }}</span>
                                    @if ($ticketType->active)
                                        <span class="inline-flex items-center gap-1 text-xs font-medium text-success">
                                            <span class="size-1.5 rounded-full bg-success" aria-hidden="true"></span>
                                            Ativo
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-xs font-medium text-text-muted">
                                            <span class="size-1.5 rounded-full bg-text-muted/50" aria-hidden="true"></span>
                                            Inativo
                                        </span>
                                    @endif
                                </div>
                            </div>
                            @include('livewire.partials.ticket-type-actions-menu', ['ticketType' => $ticketType])
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="mt-4">{{ $ticketTypes->links() }}</div>
        @endif
    </x-ui.card>

    <x-ui.modal
        title="Desativar tipo de senha?"
        :open="$ticketTypePendingDeactivationId !== null"
        close-method="cancelDeactivation"
        id="ticket-type-deactivate-title"
    >
        @if ($pendingDeactivation)
            <p>
                “{{ $pendingDeactivation->name }}” deixará de estar disponível para novas emissões onde essa configuração for respeitada.
            </p>
            <p class="mt-3">Senhas já emitidas e o histórico de atendimentos não serão apagados.</p>
        @endif
        <x-slot:actions>
            <x-ui.button variant="secondary" wire:click="cancelDeactivation" wire:loading.attr="disabled" wire:target="deactivate">Cancelar</x-ui.button>
            <x-ui.button variant="danger" wire:click="deactivate" wire:loading.attr="disabled" wire:target="deactivate">
                <span wire:loading.remove wire:target="deactivate">Sim, desativar</span>
                <span wire:loading wire:target="deactivate">Desativando...</span>
            </x-ui.button>
        </x-slot:actions>
    </x-ui.modal>

    <x-ui.modal
        title="Ativar tipo de senha?"
        :open="$ticketTypePendingActivationId !== null"
        close-method="cancelActivation"
        id="ticket-type-activate-title"
    >
        @if ($pendingActivation)
            <p>
                O tipo “{{ $pendingActivation->name }}” voltará a ficar disponível nas configurações e fluxos em que estiver habilitado.
            </p>
        @endif
        <x-slot:actions>
            <x-ui.button variant="secondary" wire:click="cancelActivation" wire:loading.attr="disabled" wire:target="activate">Cancelar</x-ui.button>
            <x-ui.button wire:click="activate" wire:loading.attr="disabled" wire:target="activate">
                <span wire:loading.remove wire:target="activate">Sim, ativar</span>
                <span wire:loading wire:target="activate">Ativando...</span>
            </x-ui.button>
        </x-slot:actions>
    </x-ui.modal>

    <x-ui.modal
        title="Excluir tipo de senha?"
        :open="$ticketTypePendingDeletionId !== null"
        close-method="cancelDeletion"
        id="ticket-type-delete-title"
    >
        @if ($pendingDeletion)
            <p>Você está prestes a excluir “{{ $pendingDeletion->name }}”.</p>
            <p class="mt-3 font-medium text-danger">Essa ação não poderá ser desfeita.</p>
        @endif
        <x-slot:actions>
            <x-ui.button variant="secondary" wire:click="cancelDeletion" wire:loading.attr="disabled" wire:target="delete">Cancelar</x-ui.button>
            <x-ui.button variant="danger" wire:click="delete" wire:loading.attr="disabled" wire:target="delete">
                <span wire:loading.remove wire:target="delete">Sim, excluir</span>
                <span wire:loading wire:target="delete">Excluindo...</span>
            </x-ui.button>
        </x-slot:actions>
    </x-ui.modal>

    <x-ui.modal
        title="Não é possível excluir este tipo de senha"
        :open="$showDeleteBlockedModal"
        close-method="dismissDeleteBlocked"
        id="ticket-type-delete-blocked-title"
    >
        <p>
            @if ($deleteBlockedName !== '')
                “{{ $deleteBlockedName }}” já possui registros vinculados e precisa ser preservado para manter o histórico de atendimentos.
            @else
                Este tipo já possui registros vinculados e precisa ser preservado para manter o histórico de atendimentos.
            @endif
        </p>
        <p class="mt-3">Você pode desativá-lo para impedir novas emissões.</p>
        <x-slot:actions>
            <x-ui.button wire:click="dismissDeleteBlocked">Entendi</x-ui.button>
        </x-slot:actions>
    </x-ui.modal>
</div>
