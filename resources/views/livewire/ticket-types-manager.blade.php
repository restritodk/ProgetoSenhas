<div>
    @if ($statusMessage !== '')
        <x-ui.alert type="success" class="mb-4">{{ $statusMessage }}</x-ui.alert>
    @endif

    <x-ui.card title="Tipos de Senha" description="Defina os tipos configuráveis de senha da clínica. Maior prioridade = número maior.">
        <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div class="grid flex-1 gap-3 md:grid-cols-2">
                <x-ui.input label="Buscar" name="ticket_type_search" id="ticket_type_search" wire:model.live.debounce.400ms="search" placeholder="Nome ou prefixo" />
                <x-ui.select label="Status" name="ticket_type_status_filter" id="ticket_type_status_filter" wire:model.live="statusFilter">
                    <option value="">Todos os status</option>
                    <option value="active">Ativos</option>
                    <option value="inactive">Inativos</option>
                </x-ui.select>
            </div>
            <x-ui.button wire:click="startCreate" wire:loading.attr="disabled">
                <x-admin.icon name="plus" class="size-4" />
                Novo tipo
            </x-ui.button>
        </div>

        @if ($showForm)
            <form wire:submit="save" class="mb-6 grid gap-4 rounded-xl border border-border bg-background p-4 md:grid-cols-2">
                <x-ui.input label="Nome" name="ticket_type_name" id="ticket_type_name" wire:model="name" required maxlength="255">
                    <x-input-error :messages="$errors->get('name')" />
                </x-ui.input>
                <x-ui.input label="Prefixo" name="ticket_type_prefix" id="ticket_type_prefix" wire:model="prefix" required maxlength="8" placeholder="Ex.: N">
                    <p class="mt-1 text-xs text-text-muted">Único na clínica. Será convertido para maiúsculas.</p>
                    <x-input-error :messages="$errors->get('prefix')" />
                </x-ui.input>
                <x-ui.input label="Prioridade" name="ticket_type_priority" id="ticket_type_priority" type="number" wire:model="priority" required min="1" max="9999">
                    <p class="mt-1 text-xs text-text-muted">Número maior = maior prioridade. Anti-starvation virá no motor de filas.</p>
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
                        <span wire:loading.remove wire:target="save">{{ $editingTicketTypeId ? 'Salvar tipo' : 'Criar tipo' }}</span>
                        <span wire:loading wire:target="save">Salvando...</span>
                    </x-ui.button>
                    <x-ui.button variant="secondary" wire:click="cancel">Cancelar</x-ui.button>
                </div>
            </form>
        @endif

        @if ($ticketTypes->isEmpty() && ! $showForm)
            <x-ui.empty-state
                title="{{ $search !== '' || $statusFilter !== '' ? 'Nenhum tipo encontrado.' : 'Nenhum tipo de senha cadastrado.' }}"
                description="{{ $search !== '' || $statusFilter !== '' ? 'Ajuste a busca ou o filtro para tentar novamente.' : 'Cadastre Normal, Preferencial, Emergencial ou outro tipo da clínica.' }}"
            >
                @if ($search === '' && $statusFilter === '')
                    <x-ui.button wire:click="startCreate">Novo tipo</x-ui.button>
                @endif
            </x-ui.empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <caption class="sr-only">Tipos de senha da clínica</caption>
                    <thead class="border-b border-border text-xs uppercase tracking-wide text-text-muted">
                        <tr>
                            <th scope="col" class="px-3 py-3 font-semibold">Nome</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Prefixo</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Prioridade</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Status</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ticketTypes as $ticketType)
                            <tr wire:key="ticket-type-{{ $ticketType->id }}" class="border-b border-border/70">
                                <td class="px-3 py-3 font-medium text-text">{{ $ticketType->name }}</td>
                                <td class="px-3 py-3">
                                    <x-ui.badge tone="accent">{{ $ticketType->prefix }}</x-ui.badge>
                                </td>
                                <td class="px-3 py-3 text-text-muted">{{ $ticketType->priority }}</td>
                                <td class="px-3 py-3">
                                    @if ($ticketType->active)
                                        <x-ui.badge tone="success">Ativo</x-ui.badge>
                                    @else
                                        <x-ui.badge tone="warning">Inativo</x-ui.badge>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <x-ui.button variant="secondary" wire:click="edit({{ $ticketType->id }})">Editar</x-ui.button>
                                        @if ($ticketType->active)
                                            <x-ui.button variant="danger" wire:click="confirmDeactivation({{ $ticketType->id }})">Desativar</x-ui.button>
                                        @else
                                            <x-ui.button variant="secondary" wire:click="activate({{ $ticketType->id }})">Ativar</x-ui.button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $ticketTypes->links() }}</div>
        @endif
    </x-ui.card>

    <x-ui.modal title="Desativar tipo de senha" :open="$ticketTypePendingDeactivationId !== null">
        <p>O tipo permanecerá no histórico e poderá ser reativado depois. Tipos inativos não serão elegíveis para emissão futura de novas senhas.</p>
        <x-slot:actions>
            <x-ui.button variant="secondary" wire:click="cancelDeactivation">Cancelar</x-ui.button>
            <x-ui.button variant="danger" wire:click="deactivate" wire:loading.attr="disabled">Desativar</x-ui.button>
        </x-slot:actions>
    </x-ui.modal>
</div>
