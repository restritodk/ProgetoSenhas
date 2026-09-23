<div wire:poll.2.5s="refreshQueue">
    @if ($this->activeUnit === null || $this->activeDesk === null)
        <x-ui.card title="Contexto operacional necessário">
            <x-ui.empty-state
                title="Selecione unidade e mesa"
                description="Abra Atendimento, escolha a unidade e ative a mesa para visualizar a fila elegível."
            />
            <div class="mt-4">
                <a href="{{ route('attendant.panel') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-accent px-4 text-sm font-semibold text-white hover:bg-blue-700">
                    Ir para Atendimento
                </a>
            </div>
        </x-ui.card>
    @else
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm text-text-muted">
                    Unidade <strong class="text-text">{{ $this->activeUnit->name }}</strong>
                    · Mesa <strong class="text-text">{{ $this->activeDesk->name }}</strong>
                </p>
                <p class="mt-1 text-xs text-text-muted">Ordem conforme política de filas — visualização apenas, sem reservar senha.</p>
            </div>
            <p class="text-sm font-semibold text-primary">{{ $this->rankedPreview->count() }} na fila elegível</p>
        </div>

        <div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @forelse ($this->queueTicketTypes as $type)
                <button
                    type="button"
                    wire:click="$set('typeFilter', '{{ $this->typeFilter === (string) $type->id ? '' : $type->id }}')"
                    @class([
                        'rounded-2xl border px-4 py-3 text-left transition duration-200',
                        'border-accent bg-blue-50' => $typeFilter === (string) $type->id,
                        'border-border bg-surface hover:border-accent/60' => $typeFilter !== (string) $type->id,
                    ])
                >
                    <p class="text-xs font-medium text-text-muted">{{ $type->name }} ({{ $type->prefix }})</p>
                    <p class="mt-1 text-2xl font-bold text-primary">{{ $this->countsByType[$type->id] ?? 0 }}</p>
                </button>
            @empty
                <p class="text-sm text-text-muted">Nenhum tipo de senha ativo nesta clínica.</p>
            @endforelse
        </div>

        <div class="mb-4 flex flex-wrap gap-3">
            <div class="min-w-[16rem] flex-1">
                <x-ui.input
                    label="Buscar senha ou tipo"
                    name="queue_search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Ex.: P001 ou Preferencial"
                />
            </div>
        </div>

        <x-ui.card title="Fila elegível">
            @if ($this->rankedPreview->isEmpty())
                <x-ui.empty-state title="Fila vazia" description="Não há senhas aguardando disponíveis para esta mesa." />
            @elseif ($this->waitingTickets->isEmpty())
                <x-ui.empty-state title="Nenhum resultado" description="Nenhuma senha corresponde à busca ou filtro atual. Limpe a busca para ver a fila completa." />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-border text-xs uppercase tracking-wide text-text-muted">
                            <tr>
                                <th scope="col" class="px-3 py-3 font-semibold">#</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Senha</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Tipo</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Emissão</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Espera</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Prioridade base</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Prioridade efetiva</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->waitingTickets as $index => $ticket)
                                <tr wire:key="queue-{{ $ticket->id }}" class="border-b border-border/70">
                                    <td class="px-3 py-3 text-text-muted">{{ $index + 1 }}</td>
                                    <td class="px-3 py-3 text-lg font-bold text-primary">{{ $ticket->display_code }}</td>
                                    <td class="px-3 py-3 text-text-muted">{{ $ticket->ticketType?->name }}</td>
                                    <td class="px-3 py-3 tabular-nums text-text-muted">
                                        {{ ($ticket->issued_at ?? $ticket->queued_at)?->timezone(config('app.timezone'))->format('H:i:s') ?? '—' }}
                                    </td>
                                    <td class="px-3 py-3 tabular-nums text-text-muted">{{ $this->formatWaitSeconds($ticket) }}</td>
                                    <td class="px-3 py-3 text-text">{{ (int) ($ticket->ticketType?->priority ?? 0) }}</td>
                                    <td class="px-3 py-3 font-semibold text-text">{{ $selector->effectivePriority($ticket, $now, $policy) }}</td>
                                    <td class="px-3 py-3">
                                        <x-ui.badge>{{ $ticket->status?->label() ?? 'Aguardando' }}</x-ui.badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>
    @endif
</div>
