<div>
    @if ($statusMessage !== '')
        <x-ui.alert type="success" class="mb-4">{{ $statusMessage }}</x-ui.alert>
    @endif

    <x-ui.card title="Painéis / TVs" description="Configure os painéis públicos de chamada por unidade e setor. Cada painel atende um setor.">
        <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div class="grid flex-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                <x-ui.input label="Buscar" name="panel_search" id="panel_search" wire:model.live.debounce.400ms="search" placeholder="Nome ou código" />
                <x-ui.select label="Unidade" name="panel_unit_filter" id="panel_unit_filter" wire:model.live="unitFilter">
                    <option value="">Todas as unidades</option>
                    @foreach ($this->availableUnits as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.select label="Setor" name="panel_sector_filter" id="panel_sector_filter" wire:model.live="sectorFilter" :disabled="$unitFilter === ''">
                    <option value="">{{ $unitFilter === '' ? 'Selecione a unidade' : 'Todos os setores' }}</option>
                    @foreach ($this->filterSectors as $sector)
                        <option value="{{ $sector->id }}">{{ $sector->name }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.select label="Status" name="panel_status_filter" id="panel_status_filter" wire:model.live="statusFilter">
                    <option value="">Todos os status</option>
                    <option value="active">Ativos</option>
                    <option value="inactive">Inativos</option>
                </x-ui.select>
            </div>
            <x-ui.button wire:click="startCreate" wire:loading.attr="disabled">
                <x-admin.icon name="plus" class="size-4" />
                Novo painel
            </x-ui.button>
        </div>

        @if ($showForm)
            <form wire:submit="save" class="mb-6 grid gap-4 rounded-xl border border-border bg-background p-4 md:grid-cols-2">
                <x-ui.input label="Nome" name="panel_name" id="panel_name" wire:model="name" required maxlength="255">
                    <x-input-error :messages="$errors->get('name')" />
                </x-ui.input>
                <x-ui.input label="Código" name="panel_code" id="panel_code" wire:model="code" required maxlength="64" placeholder="Ex.: TV-REC">
                    <p class="mt-1 text-xs text-text-muted">Único por clínica. Será convertido para maiúsculas.</p>
                    <x-input-error :messages="$errors->get('code')" />
                </x-ui.input>
                <div>
                    <x-ui.select label="Unidade" name="panel_unit_id" id="panel_unit_id" wire:model.live="unitId" required>
                        <option value="">Selecione</option>
                        @foreach ($this->availableUnits as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }}@unless ($unit->active) (desativada)@endunless</option>
                        @endforeach
                    </x-ui.select>
                    <x-input-error :messages="$errors->get('unitId')" />
                </div>
                <div>
                    <x-ui.select label="Setor" name="panel_sector_id" id="panel_sector_id" wire:model="sectorId" required :disabled="$unitId === null">
                        <option value="">{{ $unitId === null ? 'Selecione a unidade primeiro' : 'Selecione' }}</option>
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
                        <span wire:loading.remove wire:target="save">{{ $editingPanelId ? 'Salvar painel' : 'Criar painel' }}</span>
                        <span wire:loading wire:target="save">Salvando...</span>
                    </x-ui.button>
                    <x-ui.button variant="secondary" wire:click="cancel">Cancelar</x-ui.button>
                </div>
            </form>
        @endif

        @php
            $hasFilters = $search !== '' || $unitFilter !== '' || $sectorFilter !== '' || $statusFilter !== '';
        @endphp

        @if ($panels->isEmpty() && ! $showForm)
            <x-ui.empty-state
                title="{{ $hasFilters ? 'Nenhum painel encontrado.' : 'Nenhum painel cadastrado.' }}"
                description="{{ $hasFilters ? 'Ajuste a busca ou os filtros.' : 'Cadastre o primeiro painel de TV da clínica.' }}"
            >
                @if (! $hasFilters)
                    <x-ui.button wire:click="startCreate">Novo painel</x-ui.button>
                @endif
            </x-ui.empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <caption class="sr-only">Painéis de TV da clínica</caption>
                    <thead class="border-b border-border text-xs uppercase tracking-wide text-text-muted">
                        <tr>
                            <th scope="col" class="px-3 py-3 font-semibold">Nome</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Código</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Unidade</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Setor</th>
                            <th scope="col" class="px-3 py-3 font-semibold">URL pública</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Status</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($panels as $panel)
                            @php
                                $primarySector = $panel->sectors->first();
                            @endphp
                            <tr wire:key="panel-{{ $panel->id }}" class="border-b border-border/70">
                                <td class="px-3 py-3 font-medium text-text">{{ $panel->name }}</td>
                                <td class="px-3 py-3 text-text-muted">{{ $panel->code }}</td>
                                <td class="px-3 py-3 text-text-muted">{{ $panel->unit?->name ?? '—' }}</td>
                                <td class="px-3 py-3 text-text-muted">{{ $primarySector?->name ?? '—' }}</td>
                                <td class="px-3 py-3">
                                    <div class="flex max-w-xs flex-col gap-2">
                                        <code class="truncate text-xs text-text-muted" title="{{ $panel->publicUrl() }}">{{ $panel->publicUrl() }}</code>
                                        <button
                                            type="button"
                                            class="inline-flex min-h-9 w-fit cursor-pointer items-center justify-center rounded-lg border border-border bg-surface px-3 text-xs font-semibold text-text hover:bg-background"
                                            x-data="{
                                                label: 'Copiar URL',
                                                async copy(text) {
                                                    try {
                                                        if (navigator.clipboard && window.isSecureContext) {
                                                            await navigator.clipboard.writeText(text);
                                                        } else {
                                                            const ta = document.createElement('textarea');
                                                            ta.value = text;
                                                            ta.setAttribute('readonly', '');
                                                            ta.style.position = 'fixed';
                                                            ta.style.left = '-9999px';
                                                            document.body.appendChild(ta);
                                                            ta.select();
                                                            document.execCommand('copy');
                                                            document.body.removeChild(ta);
                                                        }
                                                        this.label = 'Copiado';
                                                        $wire.markUrlCopied({{ $panel->id }});
                                                        setTimeout(() => { this.label = 'Copiar URL' }, 2000);
                                                    } catch (e) {
                                                        this.label = 'Falha ao copiar';
                                                        setTimeout(() => { this.label = 'Copiar URL' }, 2500);
                                                    }
                                                }
                                            }"
                                            @click="copy(@js($panel->publicUrl()))"
                                        >
                                            <span x-text="label">{{ (string) $copiedUrlPanelId === (string) $panel->id ? 'Copiado' : 'Copiar URL' }}</span>
                                        </button>
                                    </div>
                                </td>
                                <td class="px-3 py-3">
                                    @if ($panel->active)
                                        <x-ui.badge tone="success">Ativo</x-ui.badge>
                                    @else
                                        <x-ui.badge tone="warning">Inativo</x-ui.badge>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <x-ui.button variant="secondary" wire:click="edit({{ $panel->id }})">Editar</x-ui.button>
                                        <a href="{{ route('display-panels.playlist', $panel) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-border bg-surface px-4 text-sm font-semibold text-text transition duration-200 hover:bg-background">
                                            Gerenciar playlist
                                        </a>
                                        <x-ui.button variant="secondary" wire:click="confirmTokenRegen({{ $panel->id }})">Regenerar link</x-ui.button>
                                        @if ($panel->active)
                                            <x-ui.button variant="danger" wire:click="confirmDeactivation({{ $panel->id }})">Desativar</x-ui.button>
                                        @else
                                            <x-ui.button variant="secondary" wire:click="activate({{ $panel->id }})">Ativar</x-ui.button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $panels->links() }}</div>
        @endif
    </x-ui.card>

    <x-ui.modal title="Desativar painel/TV" :open="$panelPendingDeactivationId !== null">
        <p>O painel deixará de exibir chamadas operacionais. Poderá ser reativado depois.</p>
        <x-slot:actions>
            <x-ui.button variant="secondary" wire:click="cancelDeactivation">Cancelar</x-ui.button>
            <x-ui.button variant="danger" wire:click="deactivate" wire:loading.attr="disabled">Desativar</x-ui.button>
        </x-slot:actions>
    </x-ui.modal>

    <x-ui.modal title="Regenerar link público" :open="$panelPendingTokenRegenId !== null">
        <p>A URL pública atual (código curto e token legado) deixará de funcionar imediatamente. Será necessário atualizar o endereço na TV.</p>
        <x-slot:actions>
            <x-ui.button variant="secondary" wire:click="cancelTokenRegen">Cancelar</x-ui.button>
            <x-ui.button variant="danger" wire:click="regenerateToken" wire:loading.attr="disabled">Regenerar</x-ui.button>
        </x-slot:actions>
    </x-ui.modal>
</div>
