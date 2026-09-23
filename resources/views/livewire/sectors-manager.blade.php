<div>
    @if ($statusMessage !== '')
        <x-ui.alert type="success" class="mb-4">{{ $statusMessage }}</x-ui.alert>
    @endif
    @error('sector')
        <x-ui.alert type="danger" class="mb-4">{{ $message }}</x-ui.alert>
    @enderror

    <x-ui.card title="Setores" description="Organize as áreas de atendimento de cada unidade.">
        <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div class="grid flex-1 gap-3 md:grid-cols-3">
                <x-ui.input label="Buscar" name="sector_search" id="sector_search" wire:model.live.debounce.400ms="search" placeholder="Buscar setor..." />
                <x-ui.select label="Unidade" name="sector_unit_filter" id="sector_unit_filter" wire:model.live="unitFilter">
                    <option value="">Todas as unidades</option>
                    @foreach ($this->availableUnits as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.select label="Status" name="sector_status_filter" id="sector_status_filter" wire:model.live="statusFilter">
                    <option value="">Todos os status</option>
                    <option value="active">Ativos</option>
                    <option value="inactive">Inativos</option>
                </x-ui.select>
            </div>
            <x-ui.button wire:click="startCreate" wire:loading.attr="disabled">
                <x-admin.icon name="plus" class="size-4" />
                Novo setor
            </x-ui.button>
        </div>

        @if ($showForm)
            <form wire:submit="save" class="mb-6 grid gap-4 rounded-xl border border-border bg-background p-4 md:grid-cols-2">
                <div>
                    <x-ui.select label="Unidade" name="sector_unit_id" id="sector_unit_id" wire:model.live="unitId" required>
                        <option value="">Selecione</option>
                        @foreach ($this->availableUnits as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }}@unless ($unit->active) (desativada)@endunless</option>
                        @endforeach
                    </x-ui.select>
                    <x-input-error :messages="$errors->get('unitId')" />
                </div>
                <x-ui.input label="Nome do setor" name="sector_name" id="sector_name" wire:model="name" required maxlength="255">
                    <x-input-error :messages="$errors->get('name')" />
                </x-ui.input>
                <x-ui.input label="Código" name="sector_code" id="sector_code" wire:model="code" required maxlength="32" placeholder="Ex.: REC">
                    <p class="mt-1 text-xs text-text-muted">Único por unidade. Será convertido para maiúsculas.</p>
                    <x-input-error :messages="$errors->get('code')" />
                </x-ui.input>
                <div class="flex items-end">
                    <label class="flex min-h-11 items-center gap-3 text-sm text-text">
                        <input type="checkbox" wire:model="active" class="size-4 rounded border-border text-accent">
                        Ativo
                    </label>
                </div>
                <div class="md:col-span-2">
                    <label for="sector_description" class="mb-1 block text-sm font-medium text-text">Descrição <span class="font-normal text-text-muted">(opcional)</span></label>
                    <textarea
                        id="sector_description"
                        name="sector_description"
                        wire:model="description"
                        rows="2"
                        maxlength="255"
                        class="w-full rounded-xl border border-border bg-surface px-3 py-2 text-sm text-text shadow-sm focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20"
                        placeholder="Observações sobre o setor"
                    ></textarea>
                    <x-input-error :messages="$errors->get('description')" />
                </div>
                <div class="flex flex-wrap gap-3 md:col-span-2">
                    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">{{ $editingSectorId ? 'Salvar setor' : 'Criar setor' }}</span>
                        <span wire:loading wire:target="save">Salvando...</span>
                    </x-ui.button>
                    <x-ui.button variant="secondary" wire:click="cancel">Cancelar</x-ui.button>
                </div>
            </form>
        @endif

        @if ($sectors->isEmpty() && ! $showForm)
            <x-ui.empty-state
                title="{{ $search !== '' || $unitFilter !== '' || $statusFilter !== '' ? 'Nenhum setor encontrado.' : 'Nenhum setor cadastrado.' }}"
                description="{{ $search !== '' || $unitFilter !== '' || $statusFilter !== '' ? 'Ajuste a busca ou os filtros para tentar novamente.' : 'Cadastre o primeiro setor da clínica.' }}"
            >
                @if ($search === '' && $unitFilter === '' && $statusFilter === '')
                    <x-ui.button wire:click="startCreate">Novo setor</x-ui.button>
                @endif
            </x-ui.empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <caption class="sr-only">Setores da clínica</caption>
                    <thead class="border-b border-border text-xs uppercase tracking-wide text-text-muted">
                        <tr>
                            <th scope="col" class="px-3 py-3 font-semibold">Unidade</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Setor</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Código</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Mesas</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Status</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sectors as $sector)
                            <tr wire:key="sector-{{ $sector->id }}" class="border-b border-border/70">
                                <td class="px-3 py-3 text-text-muted">{{ $sector->unit?->name ?? '—' }}</td>
                                <td class="px-3 py-3 font-medium text-text">{{ $sector->name }}</td>
                                <td class="px-3 py-3 text-text-muted">{{ $sector->code }}</td>
                                <td class="px-3 py-3 text-text-muted">{{ $sector->desks_count }}</td>
                                <td class="px-3 py-3">
                                    @if ($sector->active)
                                        <x-ui.badge tone="success">Ativo</x-ui.badge>
                                    @else
                                        <x-ui.badge tone="warning">Inativo</x-ui.badge>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <x-ui.button variant="secondary" wire:click="edit({{ $sector->id }})">Editar</x-ui.button>
                                        @if ($sector->active)
                                            <x-ui.button variant="danger" wire:click="confirmDeactivation({{ $sector->id }})">Desativar</x-ui.button>
                                        @else
                                            <x-ui.button variant="secondary" wire:click="activate({{ $sector->id }})">Ativar</x-ui.button>
                                        @endif
                                        <x-ui.button variant="danger" wire:click="confirmDeletion({{ $sector->id }})">Excluir</x-ui.button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $sectors->links() }}</div>
        @endif
    </x-ui.card>

    <x-ui.modal title="Desativar setor" :open="$sectorPendingDeactivationId !== null">
        <p>O setor permanecerá no histórico e poderá ser reativado depois. Setores inativos não devem receber novos vínculos operacionais.</p>
        <x-slot:actions>
            <x-ui.button variant="secondary" wire:click="cancelDeactivation">Cancelar</x-ui.button>
            <x-ui.button variant="danger" wire:click="deactivate" wire:loading.attr="disabled">Desativar</x-ui.button>
        </x-slot:actions>
    </x-ui.modal>

    <x-ui.modal title="Excluir setor" :open="$sectorPendingDeletionId !== null">
        <p>A exclusão só é permitida quando não há mesas, totens, senhas ou painéis vinculados. Caso contrário, desative o setor.</p>
        <x-slot:actions>
            <x-ui.button variant="secondary" wire:click="cancelDeletion">Cancelar</x-ui.button>
            <x-ui.button variant="danger" wire:click="delete" wire:loading.attr="disabled">Excluir</x-ui.button>
        </x-slot:actions>
    </x-ui.modal>
</div>
