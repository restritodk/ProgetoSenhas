<div @if (! $this->pollPaused()) wire:poll.2.5s="refreshPanel" @endif>
    @if ($statusMessage !== '')
        <x-ui.alert type="success" class="mb-4">{{ $statusMessage }}</x-ui.alert>
    @endif
    @if ($errorMessage !== '')
        <x-ui.alert type="danger" class="mb-4">{{ $errorMessage }}</x-ui.alert>
    @endif

    @if ($this->activeUnit === null)
        <section class="rounded-2xl border border-border bg-surface p-5 shadow-sm sm:p-6">
            <div class="max-w-2xl">
                <h2 class="text-xl font-semibold tracking-tight text-text">Selecione a unidade</h2>
                <p class="mt-1 text-sm leading-6 text-text-muted">
                    Em qual local/filial você está trabalhando hoje? Escolha apenas unidades físicas autorizadas para o seu usuário.
                </p>
            </div>

            @if ($this->operableUnits->isEmpty())
                <div class="mt-6">
                    <x-ui.empty-state
                        title="Nenhuma unidade disponível"
                        description="Você não possui vínculo operacional com unidades ativas desta clínica."
                    />
                </div>
            @else
                <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($this->operableUnits as $unit)
                        <button
                            type="button"
                            wire:key="unit-card-{{ $unit->id }}"
                            wire:click="selectUnit({{ $unit->id }})"
                            wire:loading.attr="disabled"
                            class="flex min-h-28 cursor-pointer flex-col justify-between rounded-2xl border border-border bg-background px-5 py-5 text-left transition duration-200 hover:border-accent hover:bg-blue-50/60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                        >
                            <span class="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary" aria-hidden="true">
                                <x-admin.icon name="building" class="size-5" />
                            </span>
                            <span>
                                <span class="mt-4 block text-base font-semibold text-primary">{{ $unit->name }}</span>
                                <span class="mt-1 block text-xs text-text-muted">Entrar nesta unidade</span>
                            </span>
                        </button>
                    @endforeach
                </div>
            @endif
        </section>
    @elseif ($this->activeDesk === null)
        @php
            $deskCards = $this->deskSelectionCards;
            $hasAnyDesk = $deskCards->isNotEmpty();
            $hasSelectable = $this->hasSelectableDesks();
        @endphp
        <section class="rounded-2xl border border-border bg-surface p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="max-w-2xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-text-muted">{{ $this->activeUnit->name }}</p>
                    <h2 class="mt-1 text-xl font-semibold tracking-tight text-text">Selecione sua mesa / guichê</h2>
                    <p class="mt-1 text-sm leading-6 text-text-muted">
                        Escolha a mesa ativa nesta unidade. Apenas uma pessoa por mesa.
                    </p>
                </div>
                <button
                    type="button"
                    wire:click="clearUnit"
                    wire:loading.attr="disabled"
                    class="inline-flex min-h-11 cursor-pointer items-center justify-center rounded-xl border border-border bg-background px-4 text-sm font-medium text-text transition duration-200 hover:bg-surface"
                >
                    Escolher outra unidade
                </button>
            </div>

            @if (! $hasAnyDesk)
                <div class="mt-6 rounded-2xl border border-dashed border-border bg-background px-5 py-10 text-center">
                    <p class="text-base font-semibold text-text">Esta unidade ainda não possui mesas/guichês ativos</p>
                    <p class="mt-2 text-sm text-text-muted">
                        Peça ao administrador para cadastrar mesas nesta unidade ou escolha outro local.
                    </p>
                    <button
                        type="button"
                        wire:click="clearUnit"
                        class="mt-5 inline-flex min-h-11 cursor-pointer items-center justify-center rounded-xl bg-accent px-4 text-sm font-semibold text-white hover:bg-blue-700"
                    >
                        Escolher outra unidade
                    </button>
                </div>
            @elseif (! $hasSelectable)
                <div class="mt-6 rounded-2xl border border-dashed border-border bg-background px-5 py-10 text-center">
                    <p class="text-base font-semibold text-text">Nenhuma mesa disponível nesta unidade</p>
                    <p class="mt-2 text-sm text-text-muted">
                        Todas as mesas/guichês ativos estão em uso no momento.
                    </p>
                    <button
                        type="button"
                        wire:click="clearUnit"
                        class="mt-5 inline-flex min-h-11 cursor-pointer items-center justify-center rounded-xl bg-accent px-4 text-sm font-semibold text-white hover:bg-blue-700"
                    >
                        Escolher outra unidade
                    </button>
                </div>
            @else
                <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($deskCards as $card)
                        @php
                            $desk = $card['desk'];
                            $available = $card['available'];
                        @endphp
                        <div
                            wire:key="desk-card-{{ $desk->id }}"
                            @class([
                                'flex min-h-32 flex-col justify-between rounded-2xl border px-5 py-5',
                                'border-border bg-background' => $available,
                                'border-border/70 bg-background/70 opacity-75' => ! $available,
                            ])
                        >
                            <div class="flex items-start justify-between gap-3">
                                <span class="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary" aria-hidden="true">
                                    <x-admin.icon name="desk" class="size-5" />
                                </span>
                                @if ($available)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-success/10 px-2.5 py-1 text-[11px] font-semibold text-success">
                                        <span class="size-1.5 rounded-full bg-success" aria-hidden="true"></span>
                                        Disponível
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-warning/10 px-2.5 py-1 text-[11px] font-semibold text-warning">
                                        <span class="size-1.5 rounded-full bg-warning" aria-hidden="true"></span>
                                        Em uso
                                    </span>
                                @endif
                            </div>
                            <div class="mt-4">
                                <p class="text-base font-semibold text-primary">{{ $desk->name }}</p>
                                <p class="mt-1 text-xs text-text-muted">
                                    {{ $desk->sector?->name ?? 'Sem setor' }}
                                    <span aria-hidden="true">·</span>
                                    Código {{ $desk->code }}
                                </p>
                            </div>
                            @if ($available)
                                <button
                                    type="button"
                                    wire:click="selectDesk({{ $desk->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="selectDesk"
                                    class="mt-4 inline-flex min-h-11 w-full cursor-pointer items-center justify-center rounded-xl bg-accent px-4 text-sm font-semibold text-white transition duration-200 hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    <span wire:loading.remove wire:target="selectDesk({{ $desk->id }})">Selecionar</span>
                                    <span wire:loading wire:target="selectDesk({{ $desk->id }})">Ativando…</span>
                                </button>
                            @else
                                <p class="mt-4 text-xs font-medium text-text-muted">Indisponível no momento</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    @else
        @php
            $deskState = $this->deskState();
            $current = $this->currentTicket;
            $unitWaiting = $this->unitWaitingCount;
            $deskAvailable = $this->deskAvailableCount;
            $firstName = auth()->user()?->firstName() ?? 'Atendente';
            $currentWaitLabel = $current
                ? $this->formatWaitDuration($current->queued_at ?? $current->issued_at, $current->called_at ?? now())
                : null;
        @endphp

        <div class="mb-5 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-border bg-surface p-4 shadow-sm">
            <div class="flex min-w-0 flex-wrap items-center gap-3">
                <div class="rounded-xl bg-primary px-4 py-3 text-white">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-white/70">Mesa ativa</p>
                    <p class="text-lg font-bold leading-tight">{{ $this->activeDesk->name }}</p>
                    <p class="text-xs text-white/70">{{ $this->activeUnit->name }}</p>
                    @if ($this->activeSector)
                        <p class="text-xs text-white/80">{{ $this->activeSector->name }}</p>
                    @endif
                </div>
                <div class="min-w-0">
                    <p class="text-lg font-semibold text-text">Olá, {{ $firstName }}</p>
                    <div class="mt-1 flex flex-wrap items-center gap-2">
                        <x-ui.badge tone="success">Online</x-ui.badge>
                        <x-ui.badge :tone="$deskState === 'free' ? 'accent' : ($deskState === 'calling' ? 'warning' : 'success')">
                            @if ($deskState === 'free') Livre
                            @elseif ($deskState === 'calling') Chamando
                            @else Em atendimento
                            @endif
                        </x-ui.badge>
                        <span class="text-xs text-text-muted">
                            Na fila {{ $unitWaiting }} · Elegíveis {{ $deskAvailable }}
                        </span>
                    </div>
                </div>
            </div>
            <button
                type="button"
                wire:click="clearDesk"
                wire:loading.attr="disabled"
                class="inline-flex min-h-11 cursor-pointer items-center justify-center rounded-xl border border-border bg-background px-4 text-sm font-medium text-text transition duration-200 hover:bg-surface"
            >
                Trocar mesa
            </button>
        </div>

        <div class="grid gap-5 xl:grid-cols-[minmax(14rem,18rem)_minmax(0,1.5fr)_minmax(16rem,20rem)]">
            {{-- Esquerda: espera por tipo --}}
            <section class="rounded-2xl border border-border bg-surface p-4 shadow-sm">
                <h2 class="text-sm font-semibold text-text">Fila de espera</h2>
                <p class="mt-1 text-xs text-text-muted">Por tipo · elegíveis a esta mesa</p>
                <ul class="mt-4 space-y-2">
                    @forelse ($this->queueTicketTypes as $type)
                        <li class="flex items-center justify-between gap-3 rounded-xl border border-border bg-background px-3 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-text">{{ $type->name }}</p>
                                <p class="text-[11px] text-text-muted">{{ $type->prefix }}</p>
                            </div>
                            <span class="text-2xl font-bold tabular-nums text-primary">{{ $this->waitingCountForType($type->id) }}</span>
                        </li>
                    @empty
                        <li>
                            <x-ui.empty-state title="Sem tipos" description="Nenhum tipo cadastrado." />
                        </li>
                    @endforelse
                </ul>
            </section>

            {{-- Centro: senha atual + ações --}}
            <section class="rounded-2xl border border-border bg-surface p-5 shadow-sm">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-text">Senha atual</h2>
                    <span class="text-xs text-text-muted">Ordem automática por prioridade</span>
                </div>

                <div class="@if ($current) bg-primary @else bg-background @endif rounded-3xl px-6 py-12 text-center shadow-inner">
                    @if ($current)
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-white/70">Em atendimento nesta mesa</p>
                        <p class="mt-4 text-7xl font-bold tracking-tight text-white sm:text-8xl">{{ $current->display_code }}</p>
                        <p class="mt-4 text-xl font-medium text-white/90">{{ $current->ticketType?->name }}</p>
                        <p class="mt-2 text-sm text-white/75">{{ $current->status->label() }}</p>
                        @if ($current->called_at)
                            <p class="mt-2 text-xs text-white/60">
                                Chamada às {{ $current->called_at->timezone(config('app.timezone'))->format('H:i:s') }}
                                · Espera {{ $currentWaitLabel }}
                            </p>
                        @endif
                    @else
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-text-muted">Pronto para atender</p>
                        <p class="mt-4 text-6xl font-bold text-primary">—</p>
                        <p class="mt-4 text-sm text-text-muted">Nenhuma senha atribuída a esta mesa</p>
                        @if ($this->unitWaitingCount === 0)
                            <p class="mx-auto mt-3 max-w-sm text-xs text-text-muted">
                                Fila vazia em <strong class="text-text">{{ $this->activeUnit->name }}</strong>.
                                Só aparecem senhas WAITING emitidas para esta unidade.
                            </p>
                            @if ($this->crossUnitWaitingHint !== null)
                                <p class="mx-auto mt-2 max-w-sm text-xs text-warning">
                                    {{ $this->crossUnitWaitingHint }}
                                </p>
                            @endif
                        @endif
                    @endif
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    @if ($canCall)
                        <button
                            type="button"
                            wire:click="callNext"
                            wire:loading.attr="disabled"
                            wire:target="callNext"
                            @disabled($current !== null)
                            class="inline-flex min-h-14 cursor-pointer items-center justify-center gap-2 rounded-2xl bg-success px-5 text-base font-semibold text-white transition duration-200 hover:bg-green-800 disabled:cursor-not-allowed disabled:opacity-50 sm:col-span-2"
                        >
                            <span wire:loading.remove wire:target="callNext">Chamar próxima senha</span>
                            <span wire:loading wire:target="callNext">Chamando...</span>
                        </button>
                    @else
                        <p class="sm:col-span-2 rounded-xl border border-warning/30 bg-amber-50 px-4 py-3 text-sm text-warning">
                            Seu perfil não tem permissão para chamar senhas.
                        </p>
                    @endif
                    @if ($canRecall)
                        <button
                            type="button"
                            wire:click="recall"
                            wire:loading.attr="disabled"
                            wire:target="recall"
                            @disabled($current === null || $current->status !== \App\TicketStatus::CALLED)
                            class="inline-flex min-h-14 cursor-pointer items-center justify-center rounded-2xl bg-warning px-5 text-base font-semibold text-white transition duration-200 hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <span wire:loading.remove wire:target="recall">Rechamar</span>
                            <span wire:loading wire:target="recall">Rechamando...</span>
                        </button>
                    @endif
                    @if ($canStart)
                        <button
                            type="button"
                            wire:click="startService"
                            wire:loading.attr="disabled"
                            wire:target="startService"
                            @disabled($current === null || $current->status !== \App\TicketStatus::CALLED)
                            class="inline-flex min-h-14 cursor-pointer items-center justify-center rounded-2xl bg-accent px-5 text-base font-semibold text-white transition duration-200 hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <span wire:loading.remove wire:target="startService">Iniciar atendimento</span>
                            <span wire:loading wire:target="startService">Iniciando...</span>
                        </button>
                    @endif
                    @if ($canComplete)
                        <button
                            type="button"
                            wire:click="complete"
                            wire:loading.attr="disabled"
                            wire:target="complete"
                            @disabled($current === null || $current->status !== \App\TicketStatus::IN_SERVICE)
                            class="inline-flex min-h-14 cursor-pointer items-center justify-center rounded-2xl bg-danger px-5 text-base font-semibold text-white transition duration-200 hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <span wire:loading.remove wire:target="complete">Finalizar</span>
                            <span wire:loading wire:target="complete">Finalizando...</span>
                        </button>
                    @endif
                    @if ($canNoShow)
                        <button
                            type="button"
                            wire:click="openNoShowModal"
                            wire:loading.attr="disabled"
                            @disabled($current === null || $current->status !== \App\TicketStatus::CALLED)
                            class="inline-flex min-h-14 cursor-pointer items-center justify-center rounded-2xl border border-danger/40 bg-red-50 px-5 text-base font-semibold text-danger transition duration-200 hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Não compareceu
                        </button>
                    @endif
                    @if ($canTransfer)
                        <button
                            type="button"
                            wire:click="openTransferModal"
                            wire:loading.attr="disabled"
                            @disabled($current === null || ! in_array($current->status, [\App\TicketStatus::CALLED, \App\TicketStatus::IN_SERVICE], true))
                            class="inline-flex min-h-14 cursor-pointer items-center justify-center rounded-2xl border border-border bg-surface px-5 text-base font-semibold text-text transition duration-200 hover:bg-background disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Transferir
                        </button>
                    @endif
                </div>
            </section>

            {{-- Direita: próximas + recentes --}}
            <div class="space-y-5">
                <section class="rounded-2xl border border-border bg-surface p-4 shadow-sm">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h2 class="text-sm font-semibold text-text">Próximas da fila</h2>
                        <a href="{{ route('attendant.queue') }}" class="text-xs font-semibold text-accent hover:underline">Ver fila</a>
                    </div>
                    @if ($this->upcomingQueue->isEmpty())
                        <p class="text-sm text-text-muted">
                            Nenhuma senha elegível em {{ $this->activeUnit?->name ?? 'esta unidade' }}.
                        </p>
                        @if ($this->crossUnitWaitingHint !== null)
                            <p class="mt-2 text-xs text-warning">{{ $this->crossUnitWaitingHint }}</p>
                        @endif
                    @else
                        <ul class="space-y-2">
                            @foreach ($this->upcomingQueue as $ticket)
                                @php
                                    $queuedAt = $ticket->queued_at ?? $ticket->issued_at;
                                @endphp
                                <li class="rounded-xl border border-border bg-background px-3 py-2.5">
                                    <div class="flex items-center justify-between gap-2">
                                        <div>
                                            <p class="text-base font-bold text-primary">{{ $ticket->display_code }}</p>
                                            <p class="text-[11px] text-text-muted">{{ $ticket->ticketType?->name }}</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-[11px] font-semibold text-text">{{ $selector->effectivePriority($ticket, $now) }}</p>
                                            <p class="text-[11px] text-text-muted">{{ $this->formatWaitDuration($queuedAt) }}</p>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                <section class="rounded-2xl border border-border bg-surface p-4 shadow-sm">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h2 class="text-sm font-semibold text-text">Últimas chamadas</h2>
                        <a href="{{ route('attendant.history') }}" class="text-xs font-semibold text-accent hover:underline">Histórico</a>
                    </div>
                    @if ($this->recentHistory->isEmpty())
                        <p class="text-sm text-text-muted">Nenhum evento recente.</p>
                    @else
                        <ul class="space-y-2">
                            @foreach ($this->recentHistory as $index => $row)
                                <li wire:key="recent-call-{{ $row['call_id'] ?? $index }}" class="rounded-xl border border-border/80 bg-background px-3 py-2">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="text-sm font-bold text-primary">{{ $row['ticket_code'] }}</p>
                                        <p class="text-[11px] tabular-nums text-text-muted">{{ $row['at']->format('H:i') }}</p>
                                    </div>
                                    <p class="mt-0.5 text-[11px] text-text-muted">{{ $row['event_label'] }} · {{ $row['desk_label'] }}</p>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>
        </div>
    @endif

    <x-ui.modal title="Transferir senha" :open="$showTransferModal" id="transfer-modal-title" close-method="closeTransferModal">
        @if ($this->currentTicket)
            <p class="mb-4 text-sm text-text">
                Senha:
                <strong class="text-primary">{{ $this->currentTicket->display_code }}</strong>
            </p>
        @endif

        <fieldset class="space-y-3">
            <legend class="sr-only">Destino da transferência</legend>
            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-border bg-background px-4 py-3">
                <input type="radio" wire:model.live="transferDestination" value="queue" class="mt-1 size-4 border-border text-accent">
                <span>
                    <span class="block text-sm font-semibold text-text">Voltar para fila geral</span>
                    <span class="block text-xs text-text-muted">Qualquer mesa ativa poderá chamar esta senha.</span>
                </span>
            </label>
            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-border bg-background px-4 py-3">
                <input type="radio" wire:model.live="transferDestination" value="desk" class="mt-1 size-4 border-border text-accent">
                <span>
                    <span class="block text-sm font-semibold text-text">Mesa específica</span>
                    <span class="block text-xs text-text-muted">Somente a mesa escolhida poderá receber esta senha.</span>
                </span>
            </label>
        </fieldset>

        @if ($transferDestination === 'desk')
            <div class="mt-4">
                <x-ui.select label="Mesa" name="transfer_to_desk_id" id="transfer_to_desk_id" wire:model="transferToDeskId" required>
                    <option value="">Selecione</option>
                    @foreach ($this->transferDestinationDesks as $desk)
                        <option value="{{ $desk->id }}">{{ $desk->name }} ({{ $desk->code }})</option>
                    @endforeach
                </x-ui.select>
                <x-input-error :messages="$errors->get('transferToDeskId')" />
            </div>
        @endif

        <div class="mt-4">
            <x-ui.input
                label="Motivo (opcional)"
                name="transfer_reason"
                id="transfer_reason"
                wire:model="transferReason"
                maxlength="255"
                placeholder="Ex.: Encaminhado para triagem"
            />
            <x-input-error :messages="$errors->get('transferReason')" />
        </div>

        <x-slot:actions>
            <x-ui.button variant="secondary" wire:click="closeTransferModal" wire:loading.attr="disabled" wire:target="transfer">
                Cancelar
            </x-ui.button>
            <x-ui.button wire:click="transfer" wire:loading.attr="disabled" wire:target="transfer">
                <span wire:loading.remove wire:target="transfer">Transferir</span>
                <span wire:loading wire:target="transfer">Transferindo...</span>
            </x-ui.button>
        </x-slot:actions>
    </x-ui.modal>

    <x-ui.modal title="Confirmar não comparecimento" :open="$showNoShowModal" id="no-show-modal-title" close-method="closeNoShowModal">
        <p class="text-sm text-text">
            Registrar que a senha
            <strong class="text-primary">{{ $this->currentTicket?->display_code ?? '—' }}</strong>
            não compareceu?
        </p>
        <p class="mt-2 text-xs text-text-muted">Esta ação encerra a chamada atual como não comparecimento.</p>

        <x-slot:actions>
            <x-ui.button variant="secondary" wire:click="closeNoShowModal" wire:loading.attr="disabled" wire:target="noShow">
                Cancelar
            </x-ui.button>
            <x-ui.button variant="danger" wire:click="noShow" wire:loading.attr="disabled" wire:target="noShow">
                <span wire:loading.remove wire:target="noShow">Confirmar</span>
                <span wire:loading wire:target="noShow">Registrando...</span>
            </x-ui.button>
        </x-slot:actions>
    </x-ui.modal>
</div>
