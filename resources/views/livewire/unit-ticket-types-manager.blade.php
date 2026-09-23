<div>
    @if ($statusMessage !== '')
        <x-ui.alert type="success" class="mb-4">{{ $statusMessage }}</x-ui.alert>
    @endif

    <x-ui.card title="Tipos de senha por unidade" description="Define quais tipos o Totem oferecece nesta unidade, a ordem dos botões e o nome exibido ao público. Um tipo ativo na clínica só aparece no Totem se estiver marcado como “Oferecer” aqui.">
        <div class="mb-6 max-w-md">
            <x-ui.select label="Unidade" name="utt_unit_id" id="utt_unit_id" wire:model.live="unitId" required>
                <option value="">Selecione a unidade</option>
                @foreach ($this->availableUnits as $unit)
                    <option value="{{ $unit->id }}">{{ $unit->name }}@unless ($unit->active) (desativada)@endunless</option>
                @endforeach
            </x-ui.select>
        </div>

        @if ($unitId === null)
            <x-ui.empty-state title="Selecione uma unidade" description="Escolha a unidade para configurar os tipos disponíveis no Totem." />
        @elseif (count($rows) === 0)
            <x-ui.empty-state title="Nenhum tipo de senha" description="Cadastre tipos de senha na clínica antes de associá-los à unidade." />
        @else
            <form wire:submit="save" class="space-y-4">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <caption class="sr-only">Tipos de senha disponíveis na unidade</caption>
                        <thead class="border-b border-border text-xs uppercase tracking-wide text-text-muted">
                            <tr>
                                <th scope="col" class="px-3 py-3 font-semibold">Tipo</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Ativo no Totem</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Nome exibido</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Ordem</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $index => $row)
                                <tr wire:key="utt-row-{{ $row['ticket_type_id'] }}" class="border-b border-border/70">
                                    <td class="px-3 py-3">
                                        <p class="font-medium text-text">{{ $row['type_name'] }} ({{ $row['type_prefix'] }})</p>
                                        @unless ($row['type_active'])
                                            <p class="text-xs text-warning">Tipo globalmente inativo — não aparece no Totem.</p>
                                        @endunless
                                        <input type="hidden" wire:model="rows.{{ $index }}.ticket_type_id">
                                    </td>
                                    <td class="px-3 py-3">
                                        <label class="inline-flex min-h-11 items-center gap-2 text-sm text-text">
                                            <input type="checkbox" wire:model="rows.{{ $index }}.active" class="size-4 rounded border-border text-accent">
                                            Oferecer
                                        </label>
                                    </td>
                                    <td class="px-3 py-3">
                                        <x-ui.input
                                            label="Nome público"
                                            :name="'utt_display_'.$index"
                                            :id="'utt_display_'.$index"
                                            wire:model="rows.{{ $index }}.display_name"
                                            maxlength="255"
                                            placeholder="{{ $row['type_name'] }}"
                                        />
                                    </td>
                                    <td class="px-3 py-3 w-28">
                                        <x-ui.input
                                            label="Ordem"
                                            :name="'utt_pos_'.$index"
                                            :id="'utt_pos_'.$index"
                                            type="number"
                                            min="0"
                                            max="9999"
                                            wire:model="rows.{{ $index }}.position"
                                        />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">Salvar configuração</span>
                    <span wire:loading wire:target="save">Salvando...</span>
                </x-ui.button>
            </form>
        @endif
    </x-ui.card>
</div>
