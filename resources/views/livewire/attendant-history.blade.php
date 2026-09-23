<div>
    <div class="mb-5 grid gap-3 lg:grid-cols-[minmax(0,1fr)_auto]">
        <div class="flex flex-wrap gap-3">
            <div class="min-w-[14rem] flex-1">
                <x-ui.input
                    label="Buscar"
                    name="history_search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Código ou tipo"
                />
            </div>
            <div class="min-w-[10rem]">
                <x-ui.select label="Status" name="history_status" wire:model.live="statusFilter">
                    <option value="">Todos</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                    @endforeach
                </x-ui.select>
            </div>
            <div class="min-w-[10rem]">
                <x-ui.select label="Tipo" name="history_type" wire:model.live="typeFilter">
                    <option value="">Todos</option>
                    @foreach ($this->ticketTypes as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                    @endforeach
                </x-ui.select>
            </div>
            <div class="min-w-[10rem]">
                <x-ui.select label="Período" name="history_period" wire:model.live="period">
                    <option value="today">Hoje</option>
                    <option value="7d">7 dias</option>
                    <option value="30d">30 dias</option>
                    <option value="month">Este mês</option>
                    <option value="year">Este ano</option>
                    <option value="custom">Personalizado</option>
                </x-ui.select>
            </div>
        </div>
    </div>

    @if ($period === 'custom')
        <div class="mb-5 flex flex-wrap items-end gap-2">
            <x-ui.input label="De" name="history_from" type="date" wire:model="customFrom" />
            <x-ui.input label="Até" name="history_to" type="date" wire:model="customTo" />
            <x-ui.button wire:click="applyCustomPeriod">Aplicar</x-ui.button>
        </div>
    @endif

    <x-ui.card title="Meus atendimentos" description="Senhas em que você chamou, iniciou, concluiu ou registrou não comparecimento.">
        @if ($tickets->isEmpty())
            <x-ui.empty-state title="Nenhum registro" description="Não há histórico pessoal neste filtro." />
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-border text-xs uppercase tracking-wide text-text-muted">
                        <tr>
                            <th scope="col" class="px-3 py-3 font-semibold">Senha</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Tipo</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Unidade</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Chamada</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Início</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Fim</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Espera</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Atendimento</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tickets as $ticket)
                            <tr wire:key="history-ticket-{{ $ticket->id }}" class="border-b border-border/70">
                                <td class="px-3 py-3 font-bold text-primary">{{ $ticket->display_code }}</td>
                                <td class="px-3 py-3 text-text-muted">{{ $ticket->ticketType?->name }}</td>
                                <td class="px-3 py-3 text-text-muted">{{ $ticket->unit?->name }}</td>
                                <td class="px-3 py-3 tabular-nums text-text-muted">
                                    {{ $ticket->called_at?->timezone(config('app.timezone'))->format('d/m H:i') ?? '—' }}
                                </td>
                                <td class="px-3 py-3 tabular-nums text-text-muted">
                                    {{ $ticket->service_started_at?->timezone(config('app.timezone'))->format('d/m H:i') ?? '—' }}
                                </td>
                                <td class="px-3 py-3 tabular-nums text-text-muted">
                                    {{ $ticket->completed_at?->timezone(config('app.timezone'))->format('d/m H:i') ?? '—' }}
                                </td>
                                <td class="px-3 py-3 tabular-nums text-text-muted">{{ $this->waitDurationLabel($ticket) }}</td>
                                <td class="px-3 py-3 tabular-nums text-text-muted">{{ $this->serviceDurationLabel($ticket) }}</td>
                                <td class="px-3 py-3">
                                    <x-ui.badge>{{ $ticket->status->label() }}</x-ui.badge>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $tickets->links() }}
            </div>
        @endif
    </x-ui.card>
</div>
