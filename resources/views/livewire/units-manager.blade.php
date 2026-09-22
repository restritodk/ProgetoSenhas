<div>
    @if ($statusMessage !== '')
        <x-ui.alert type="success" class="mb-4">{{ $statusMessage }}</x-ui.alert>
    @endif

    <x-ui.card title="Unidades" description="As unidades pertencem automaticamente à sua clínica.">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-text-muted">{{ $this->units->count() }} unidade(s) cadastrada(s)</p>
            <x-ui.button wire:click="startCreate" wire:loading.attr="disabled">
                <x-admin.icon name="plus" class="size-4" />
                Adicionar unidade
            </x-ui.button>
        </div>

        @if ($showForm)
            <form wire:submit="save" class="mb-6 grid gap-4 rounded-xl border border-border bg-background p-4 md:grid-cols-2">
                <x-ui.input label="Nome" name="unit_name" id="unit_name" wire:model="name" required maxlength="255">
                    <x-input-error :messages="$errors->get('name')" />
                </x-ui.input>
                <x-ui.input label="Identificador" name="unit_slug" id="unit_slug" wire:model="slug" maxlength="255" placeholder="Gerado a partir do nome se ficar em branco">
                    <x-input-error :messages="$errors->get('slug')" />
                </x-ui.input>
                <div class="flex flex-wrap gap-3 md:col-span-2">
                    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">{{ $editingUnitId ? 'Salvar unidade' : 'Criar unidade' }}</span>
                        <span wire:loading wire:target="save">Salvando...</span>
                    </x-ui.button>
                    <x-ui.button variant="secondary" wire:click="cancel">Cancelar</x-ui.button>
                </div>
            </form>
        @endif

        @if ($this->units->isEmpty() && ! $showForm)
            <x-ui.empty-state title="Nenhuma unidade cadastrada." description="Cadastre a primeira unidade para organizar o atendimento.">
                <x-ui.button wire:click="startCreate">Adicionar primeira unidade</x-ui.button>
            </x-ui.empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <caption class="sr-only">Unidades da clínica</caption>
                    <thead class="border-b border-border text-xs uppercase tracking-wide text-text-muted">
                        <tr>
                            <th scope="col" class="px-3 py-3 font-semibold">Nome</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Identificador</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Status</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->units as $unit)
                            <tr wire:key="unit-{{ $unit->id }}" class="border-b border-border/70">
                                <td class="px-3 py-3 font-medium text-text">{{ $unit->name }}</td>
                                <td class="px-3 py-3 text-text-muted">{{ $unit->slug }}</td>
                                <td class="px-3 py-3">
                                    @if ($unit->active)
                                        <x-ui.badge tone="success">Ativa</x-ui.badge>
                                    @else
                                        <x-ui.badge tone="warning">Desativada</x-ui.badge>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <x-ui.button variant="secondary" wire:click="edit({{ $unit->id }})">Editar</x-ui.button>
                                        @if ($unit->active)
                                            <x-ui.button variant="danger" wire:click="confirmDeactivation({{ $unit->id }})">Desativar</x-ui.button>
                                        @else
                                            <x-ui.button variant="secondary" wire:click="activate({{ $unit->id }})">Ativar</x-ui.button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>

    <x-ui.modal title="Desativar unidade" :open="$unitPendingDeactivationId !== null">
        <p>A unidade permanecerá no histórico e poderá ser reativada depois. Esta ação não apaga o cadastro.</p>
        <x-slot:actions>
            <x-ui.button variant="secondary" wire:click="cancelDeactivation">Cancelar</x-ui.button>
            <x-ui.button variant="danger" wire:click="deactivate" wire:loading.attr="disabled">Desativar</x-ui.button>
        </x-slot:actions>
    </x-ui.modal>
</div>
