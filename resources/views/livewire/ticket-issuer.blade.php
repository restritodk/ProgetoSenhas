<div>
    @if ($statusMessage !== '')
        <x-ui.alert type="success" class="mb-4">{{ $statusMessage }}</x-ui.alert>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
        <x-ui.card title="Emissão" description="Emita uma senha para uma unidade e tipo ativos da sua clínica.">
            <form wire:submit="issue" class="grid gap-4">
                <div>
                    <x-ui.select label="Unidade" name="issue_unit_id" id="issue_unit_id" wire:model.live="unitId" required>
                        <option value="">Selecione</option>
                        @foreach ($this->availableUnits as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-input-error :messages="$errors->get('unitId')" />
                </div>

                <div>
                    <x-ui.select label="Tipo de senha" name="issue_ticket_type_id" id="issue_ticket_type_id" wire:model="ticketTypeId" required>
                        <option value="">Selecione</option>
                        @foreach ($this->availableTicketTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }} ({{ $type->prefix }}) · prioridade {{ $type->priority }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-input-error :messages="$errors->get('ticketTypeId')" />
                </div>

                <div class="flex flex-wrap gap-3">
                    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="issue">
                        <span wire:loading.remove wire:target="issue">Emitir senha</span>
                        <span wire:loading wire:target="issue">Emitindo...</span>
                    </x-ui.button>
                </div>
            </form>

            @if ($this->lastIssuedTicket)
                <div class="mt-6 rounded-2xl border border-accent/30 bg-blue-50 px-6 py-8 text-center">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-accent">Senha</p>
                    <p class="mt-3 text-5xl font-bold tracking-tight text-primary">{{ $this->lastIssuedTicket->display_code }}</p>
                    <p class="mt-2 text-sm font-medium text-text">{{ $this->lastIssuedTicket->ticketType?->name }}</p>
                    <p class="mt-1 text-xs text-text-muted">
                        Emitida às {{ $this->lastIssuedTicket->issued_at?->timezone(config('app.timezone'))->format('H:i:s') }}
                    </p>
                </div>
            @endif
        </x-ui.card>

        <x-ui.card title="Fila da unidade" description="Ordenação conforme prioridade efetiva (base + envelhecimento). Consulta não chama a senha.">
            @php
                $selector = app(\App\Services\NextTicketSelector::class);
                $now = \Carbon\CarbonImmutable::now(config('app.timezone'));
                $next = $this->queue->first();
            @endphp

            @if ($next)
                <x-ui.alert type="success" class="mb-4">
                    Próxima sugerida: <strong>{{ $next->display_code }}</strong>
                    (prioridade efetiva {{ $selector->effectivePriority($next, $now) }})
                </x-ui.alert>
            @endif

            @if ($this->queue->isEmpty())
                <x-ui.empty-state
                    title="Fila vazia"
                    description="Nenhuma senha aguardando nesta unidade."
                />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <caption class="sr-only">Fila de espera da unidade</caption>
                        <thead class="border-b border-border text-xs uppercase tracking-wide text-text-muted">
                            <tr>
                                <th scope="col" class="px-3 py-3 font-semibold">Senha</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Tipo</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Prioridade</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Efetiva</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Emitida</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Espera</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->queue as $ticket)
                                @php
                                    $waitingSeconds = max(0, $ticket->issued_at->diffInSeconds($now));
                                    $waitingMinutes = intdiv($waitingSeconds, 60);
                                    $waitingRemain = $waitingSeconds % 60;
                                @endphp
                                <tr wire:key="queue-ticket-{{ $ticket->id }}" class="border-b border-border/70">
                                    <td class="px-3 py-3 font-semibold text-primary">{{ $ticket->display_code }}</td>
                                    <td class="px-3 py-3 text-text">{{ $ticket->ticketType?->name }}</td>
                                    <td class="px-3 py-3 text-text-muted">{{ $ticket->ticketType?->priority }}</td>
                                    <td class="px-3 py-3 text-text-muted">{{ $selector->effectivePriority($ticket, $now) }}</td>
                                    <td class="px-3 py-3 text-text-muted">{{ $ticket->issued_at?->timezone(config('app.timezone'))->format('H:i:s') }}</td>
                                    <td class="px-3 py-3 text-text-muted">{{ sprintf('%d:%02d', $waitingMinutes, $waitingRemain) }}</td>
                                    <td class="px-3 py-3">
                                        <x-ui.badge tone="warning">{{ $ticket->status->label() }}</x-ui.badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>
    </div>
</div>
