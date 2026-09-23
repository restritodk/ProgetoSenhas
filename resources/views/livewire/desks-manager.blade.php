<div>
    @if ($statusMessage !== '')
        <x-ui.alert type="success" class="mb-4">{{ $statusMessage }}</x-ui.alert>
    @endif
    @error('active')
        <x-ui.alert type="danger" class="mb-4">{{ $message }}</x-ui.alert>
    @enderror

    <x-ui.card title="Mesas / Guichês" description="Pontos de atendimento vinculados a unidade e setor.">
        <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div class="grid flex-1 gap-3 md:grid-cols-3">
                <x-ui.input label="Buscar" name="desk_search" id="desk_search" wire:model.live.debounce.400ms="search" placeholder="Nome ou código" />
                <x-ui.select label="Unidade" name="desk_unit_filter" id="desk_unit_filter" wire:model.live="unitFilter">
                    <option value="">Todas as unidades</option>
                    @foreach ($this->availableUnits as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.select label="Status" name="desk_status_filter" id="desk_status_filter" wire:model.live="statusFilter">
                    <option value="">Todos os status</option>
                    <option value="active">Ativos</option>
                    <option value="inactive">Inativos</option>
                </x-ui.select>
            </div>
            <x-ui.button wire:click="startCreate" wire:loading.attr="disabled">
                <x-admin.icon name="plus" class="size-4" />
                Nova mesa
            </x-ui.button>
        </div>

        @if ($showForm)
            <form wire:submit="save" class="mb-6 grid gap-4 rounded-xl border border-border bg-background p-4 md:grid-cols-2">
                <x-ui.input label="Nome" name="desk_name" id="desk_name" wire:model="name" required maxlength="255">
                    <x-input-error :messages="$errors->get('name')" />
                </x-ui.input>
                <x-ui.input label="Código" name="desk_code" id="desk_code" wire:model="code" required maxlength="32" placeholder="Ex.: M01">
                    <p class="mt-1 text-xs text-text-muted">Único por unidade. Será convertido para maiúsculas.</p>
                    <x-input-error :messages="$errors->get('code')" />
                </x-ui.input>
                <div>
                    <x-ui.select label="Unidade" name="desk_unit_id" id="desk_unit_id" wire:model.live="unitId" required>
                        <option value="">Selecione</option>
                        @foreach ($this->availableUnits as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }}@unless ($unit->active) (desativada)@endunless</option>
                        @endforeach
                    </x-ui.select>
                    <x-input-error :messages="$errors->get('unitId')" />
                </div>
                <div>
                    <x-ui.select label="Setor" name="desk_sector_id" id="desk_sector_id" wire:model="sectorId" required :disabled="$unitId === null">
                        <option value="">{{ $unitId === null ? 'Selecione a unidade' : 'Selecione' }}</option>
                        @foreach ($this->availableSectors as $sector)
                            <option value="{{ $sector->id }}">{{ $sector->name }}@unless ($sector->active) (inativo)@endunless</option>
                        @endforeach
                    </x-ui.select>
                    <x-input-error :messages="$errors->get('sectorId')" />
                </div>
                <div class="flex items-end md:col-span-2">
                    <label class="flex min-h-11 items-center gap-3 text-sm text-text">
                        <input type="checkbox" wire:model="active" class="size-4 rounded border-border text-accent">
                        Ativo
                    </label>
                </div>
                <div class="flex flex-wrap gap-3 md:col-span-2">
                    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">{{ $editingDeskId ? 'Salvar mesa' : 'Criar mesa' }}</span>
                        <span wire:loading wire:target="save">Salvando...</span>
                    </x-ui.button>
                    <x-ui.button variant="secondary" wire:click="cancel">Cancelar</x-ui.button>
                </div>
            </form>
        @endif

        @if ($desks->isEmpty() && ! $showForm)
            <x-ui.empty-state
                title="{{ $search !== '' || $unitFilter !== '' || $statusFilter !== '' ? 'Nenhuma mesa encontrada.' : 'Nenhuma mesa cadastrada.' }}"
                description="{{ $search !== '' || $unitFilter !== '' || $statusFilter !== '' ? 'Ajuste a busca ou os filtros para tentar novamente.' : 'Cadastre a primeira mesa ou guichê da clínica.' }}"
            >
                @if ($search === '' && $unitFilter === '' && $statusFilter === '')
                    <x-ui.button wire:click="startCreate">Nova mesa</x-ui.button>
                @endif
            </x-ui.empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <caption class="sr-only">Mesas e guichês da clínica</caption>
                    <thead class="border-b border-border text-xs uppercase tracking-wide text-text-muted">
                        <tr>
                            <th scope="col" class="px-3 py-3 font-semibold">Nome</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Código</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Unidade</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Setor</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Status</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($desks as $desk)
                            <tr wire:key="desk-{{ $desk->id }}" class="border-b border-border/70">
                                <td class="px-3 py-3 font-medium text-text">{{ $desk->name }}</td>
                                <td class="px-3 py-3 text-text-muted">{{ $desk->code }}</td>
                                <td class="px-3 py-3 text-text-muted">{{ $desk->unit?->name ?? '—' }}</td>
                                <td class="px-3 py-3 text-text-muted">{{ $desk->sector?->name ?? '—' }}</td>
                                <td class="px-3 py-3">
                                    @if ($desk->active)
                                        <x-ui.badge tone="success">Ativo</x-ui.badge>
                                    @else
                                        <x-ui.badge tone="warning">Inativo</x-ui.badge>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <x-ui.button variant="secondary" wire:click="edit({{ $desk->id }})">Editar</x-ui.button>
                                        @if ($desk->active)
                                            <x-ui.button variant="danger" wire:click="confirmDeactivation({{ $desk->id }})">Desativar</x-ui.button>
                                        @else
                                            <x-ui.button variant="secondary" wire:click="activate({{ $desk->id }})">Ativar</x-ui.button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $desks->links() }}</div>
        @endif
    </x-ui.card>

    <x-ui.modal title="Desativar mesa/guichê" :open="$deskPendingDeactivationId !== null">
        <p>O ponto de atendimento permanecerá no histórico e poderá ser reativado depois. Pontos inativos não serão elegíveis para operação futura.</p>
        <x-slot:actions>
            <x-ui.button variant="secondary" wire:click="cancelDeactivation">Cancelar</x-ui.button>
            <x-ui.button variant="danger" wire:click="deactivate" wire:loading.attr="disabled">Desativar</x-ui.button>
        </x-slot:actions>
    </x-ui.modal>
</div>
