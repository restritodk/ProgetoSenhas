<div wire:poll.10s="refreshPanel">
    @if ($statusMessage !== '')
        <x-ui.alert type="success" class="mb-4">{{ $statusMessage }}</x-ui.alert>
    @endif
    @if ($errorMessage !== '')
        <x-ui.alert type="danger" class="mb-4">{{ $errorMessage }}</x-ui.alert>
    @endif

    @if ($this->activeUnit === null)
        <x-ui.card title="Selecionar unidade" description="Escolha a unidade em que você vai atender.">
            @if ($this->operableUnits->isEmpty())
                <x-ui.empty-state
                    title="Nenhuma unidade disponível"
                    description="Você não possui vínculo operacional com unidades ativas."
                />
            @else
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($this->operableUnits as $unit)
                        <button
                            type="button"
                            wire:click="selectUnit({{ $unit->id }})"
                            wire:loading.attr="disabled"
                            class="min-h-24 rounded-2xl border border-border bg-background px-4 py-5 text-left transition duration-200 hover:border-accent hover:bg-blue-50"
                        >
                            <p class="text-base font-semibold text-primary">{{ $unit->name }}</p>
                            <p class="mt-1 text-xs text-text-muted">Entrar nesta unidade</p>
                        </button>
                    @endforeach
                </div>
            @endif
        </x-ui.card>
    @elseif ($this->activeDesk === null)
        <x-ui.card title="Selecionar mesa" description="Ative a mesa/guichê onde você está trabalhando. Apenas uma pessoa por mesa.">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-text-muted">Unidade: <strong class="text-text">{{ $this->activeUnit->name }}</strong></p>
                <button
                    type="button"
                    wire:click="clearUnit"
                    wire:loading.attr="disabled"
                    class="inline-flex min-h-10 cursor-pointer items-center justify-center rounded-xl border border-border bg-surface px-3 text-sm font-medium text-text transition duration-200 hover:bg-background"
                >
                    Trocar unidade
                </button>
            </div>
            @if ($this->availableDesks->isEmpty())
                <x-ui.empty-state title="Nenhuma mesa ativa" description="Peça ao administrador para cadastrar mesas nesta unidade." />
            @else
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($this->availableDesks as $desk)
                        <button
                            type="button"
                            wire:click="selectDesk({{ $desk->id }})"
                            wire:loading.attr="disabled"
                            wire:target="selectDesk"
                            class="min-h-24 rounded-2xl border border-border bg-background px-4 py-5 text-left transition duration-200 hover:border-accent hover:bg-blue-50"
                        >
                            <p class="text-base font-semibold text-primary">{{ $desk->name }}</p>
                            <p class="mt-1 text-xs text-text-muted">Código {{ $desk->code }}</p>
                        </button>
                    @endforeach
                </div>
            @endif
        </x-ui.card>
    @else
        @php
            $deskState = $this->deskState();
            $current = $this->currentTicket;
            $totalWaiting = $this->queueCountsByType->sum();
        @endphp

        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.badge tone="success">Online</x-ui.badge>
                <x-ui.badge :tone="$deskState === 'free' ? 'accent' : ($deskState === 'calling' ? 'warning' : 'success')">
                    @if ($deskState === 'free') Livre
                    @elseif ($deskState === 'calling') Chamando
                    @else Em atendimento
                    @endif
                </x-ui.badge>
                <span class="text-sm text-text-muted">{{ $this->activeDesk->name }} · {{ $this->activeUnit->name }}</span>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <p class="text-sm text-text-muted">Fila aguardando: <strong class="text-text">{{ $totalWaiting }}</strong></p>
                <button
                    type="button"
                    wire:click="clearDesk"
                    wire:loading.attr="disabled"
                    class="inline-flex min-h-10 cursor-pointer items-center justify-center rounded-xl border border-border bg-surface px-3 text-sm font-medium text-text transition duration-200 hover:bg-background"
                >
                    Trocar mesa
                </button>
            </div>
        </div>

        <div class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.card title="Total">
                <p class="text-3xl font-bold text-primary">{{ $totalWaiting }}</p>
            </x-ui.card>
            @foreach ($this->queueTicketTypes->take(3) as $type)
                <x-ui.card title="{{ $type->name }} ({{ $type->prefix }})">
                    <p class="text-3xl font-bold text-primary">{{ $this->queueCountsByType[$type->id] ?? 0 }}</p>
                </x-ui.card>
            @endforeach
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,0.9fr)]">
            <section class="rounded-2xl border border-border bg-surface p-5 shadow-sm">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-text">Senha atual</h2>
                    <span class="text-xs text-text-muted">Automático por prioridade</span>
                </div>

                <div class="@if ($current) bg-primary @else bg-background @endif rounded-2xl px-6 py-10 text-center">
                    @if ($current)
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-white/70">Senha atual</p>
                        <p class="mt-3 text-6xl font-bold tracking-tight text-white sm:text-7xl">{{ $current->display_code }}</p>
                        <p class="mt-3 text-lg font-medium text-white/90">{{ $current->ticketType?->name }}</p>
                        <p class="mt-2 text-sm text-white/70">{{ $current->status->label() }}</p>
                        @if ($current->called_at)
                            <p class="mt-1 text-xs text-white/60">Chamada às {{ $current->called_at->timezone(config('app.timezone'))->format('H:i:s') }}</p>
                        @endif
                    @else
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-text-muted">Pronto para atender</p>
                        <p class="mt-3 text-4xl font-bold text-primary">—</p>
                        <p class="mt-3 text-sm text-text-muted">Nenhuma senha atribuída a esta mesa</p>
                    @endif
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <button
                        type="button"
                        wire:click="callNext"
                        wire:loading.attr="disabled"
                        wire:target="callNext"
                        @disabled($current !== null)
                        class="inline-flex min-h-14 cursor-pointer items-center justify-center gap-2 rounded-2xl bg-success px-5 text-base font-semibold text-white transition duration-200 hover:bg-green-800 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="callNext">Chamar próxima senha</span>
                        <span wire:loading wire:target="callNext">Chamando...</span>
                    </button>
                    <button
                        type="button"
                        wire:click="recall"
                        wire:loading.attr="disabled"
                        @disabled($current === null || $current->status !== \App\TicketStatus::CALLED)
                        class="inline-flex min-h-14 cursor-pointer items-center justify-center rounded-2xl border border-border bg-surface px-5 text-base font-semibold text-text transition duration-200 hover:bg-background disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Rechamar
                    </button>
                    <button
                        type="button"
                        disabled
                        class="inline-flex min-h-14 cursor-not-allowed items-center justify-center rounded-2xl border border-dashed border-border bg-background px-5 text-base font-semibold text-text-muted opacity-70"
                        title="Disponível em fase futura"
                    >
                        Transferir — em breve
                    </button>
                    <button
                        type="button"
                        wire:click="startService"
                        wire:loading.attr="disabled"
                        @disabled($current === null || $current->status !== \App\TicketStatus::CALLED)
                        class="inline-flex min-h-14 cursor-pointer items-center justify-center rounded-2xl bg-accent px-5 text-base font-semibold text-white transition duration-200 hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Iniciar atendimento
                    </button>
                    <button
                        type="button"
                        wire:click="complete"
                        wire:loading.attr="disabled"
                        @disabled($current === null || $current->status !== \App\TicketStatus::IN_SERVICE)
                        class="inline-flex min-h-14 cursor-pointer items-center justify-center rounded-2xl bg-primary px-5 text-base font-semibold text-white transition duration-200 hover:bg-primary-dark disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Finalizar atendimento
                    </button>
                    <button
                        type="button"
                        wire:click="noShow"
                        wire:loading.attr="disabled"
                        @disabled($current === null || $current->status !== \App\TicketStatus::CALLED)
                        class="inline-flex min-h-14 cursor-pointer items-center justify-center rounded-2xl bg-danger px-5 text-base font-semibold text-white transition duration-200 hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Não compareceu
                    </button>
                </div>
            </section>

            <section class="rounded-2xl border border-border bg-surface p-5 shadow-sm">
                <h2 class="text-base font-semibold text-text">Próximas da fila</h2>
                <p class="mt-1 text-xs text-text-muted">Ordenação por prioridade efetiva. Apenas visualização.</p>

                @if ($this->upcomingQueue->isEmpty())
                    <div class="mt-6">
                        <x-ui.empty-state title="Fila vazia" description="Não há senhas aguardando." />
                    </div>
                @else
                    <ul class="mt-4 space-y-3">
                        @foreach ($this->upcomingQueue as $ticket)
                            @php
                                $waitingSeconds = max(0, $ticket->issued_at->diffInSeconds($now));
                            @endphp
                            <li class="rounded-xl border border-border bg-background px-4 py-3">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="text-lg font-bold text-primary">{{ $ticket->display_code }}</p>
                                        <p class="text-xs text-text-muted">{{ $ticket->ticketType?->name }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs font-semibold text-text">{{ $selector->effectivePriority($ticket, $now) }}</p>
                                        <p class="text-xs text-text-muted">{{ intdiv($waitingSeconds, 60) }} min</p>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        <section class="mt-6 rounded-2xl border border-border bg-surface p-5 shadow-sm">
            <h2 class="text-base font-semibold text-text">Histórico recente</h2>
            @if ($this->recentCalls->isEmpty())
                <p class="mt-3 text-sm text-text-muted">Nenhuma chamada registrada nesta unidade ainda.</p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-border text-xs uppercase tracking-wide text-text-muted">
                            <tr>
                                <th scope="col" class="px-3 py-3 font-semibold">Senha</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Tipo</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Mesa</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Evento</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Horário</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->recentCalls as $call)
                                <tr wire:key="call-{{ $call->id }}" class="border-b border-border/70">
                                    <td class="px-3 py-3 font-semibold text-primary">{{ $call->ticket?->display_code }}</td>
                                    <td class="px-3 py-3 text-text-muted">{{ $call->ticket?->ticketType?->name }}</td>
                                    <td class="px-3 py-3 text-text-muted">{{ $call->desk?->name }}</td>
                                    <td class="px-3 py-3 text-text-muted">{{ $call->call_type->label() }}</td>
                                    <td class="px-3 py-3 text-text-muted">{{ $call->called_at?->timezone(config('app.timezone'))->format('H:i:s') }}</td>
                                    <td class="px-3 py-3">
                                        <x-ui.badge>{{ $call->ticket?->status?->label() }}</x-ui.badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif
</div>
