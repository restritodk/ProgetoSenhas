<div wire:poll.3s="refreshInbox" class="grid gap-4 xl:grid-cols-[minmax(16rem,22rem)_minmax(0,1fr)]">
    <aside class="flex max-h-[42rem] flex-col rounded-2xl border border-border bg-surface shadow-sm xl:max-h-[calc(100vh-10rem)]">
        <div class="border-b border-border px-4 py-4">
            <h2 class="text-base font-semibold text-text">Conversas</h2>
            <p class="mt-1 text-xs text-text-muted">Atendentes e supervisores da sua clínica</p>
        </div>

        <ul class="min-h-0 flex-1 overflow-y-auto p-2" role="list" aria-label="Conversas">
            @forelse ($this->conversations as $row)
                @php
                    $peer = $row['user'];
                    $active = $activeConversationId !== null && (int) $row['conversation_id'] === (int) $activeConversationId;
                    $online = $row['online'] && ! $row['inactive'];
                @endphp
                <li>
                    <button
                        type="button"
                        wire:click="openConversation({{ $row['conversation_id'] }})"
                        wire:key="conversation-{{ $row['conversation_id'] }}"
                        @class([
                            'flex w-full cursor-pointer items-start gap-3 rounded-xl px-3 py-3 text-left transition duration-200',
                            'bg-blue-50 ring-1 ring-accent/40' => $active,
                            'hover:bg-background' => ! $active,
                        ])
                    >
                        <span class="relative shrink-0">
                            @if ($peer->avatarUrl())
                                <img src="{{ $peer->avatarUrl() }}" alt="" class="size-10 rounded-full object-cover">
                            @else
                                <span class="flex size-10 items-center justify-center rounded-full bg-primary text-xs font-bold text-white">
                                    {{ $peer->initials() }}
                                </span>
                            @endif
                            <span
                                @class([
                                    'absolute bottom-0 right-0 size-3 rounded-full ring-2 ring-surface',
                                    'bg-success' => $online,
                                    'bg-danger' => ! $online,
                                ])
                                aria-hidden="true"
                            ></span>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center justify-between gap-2">
                                <span class="truncate text-sm font-semibold text-text">{{ $peer->name }}</span>
                                @if ($row['unread'] > 0)
                                    <span class="inline-flex min-w-5 items-center justify-center rounded-full bg-danger px-1.5 text-[11px] font-bold text-white">
                                        {{ $row['unread'] > 99 ? '99+' : $row['unread'] }}
                                    </span>
                                @elseif ($row['last_message']?->created_at)
                                    <span class="shrink-0 text-[11px] tabular-nums text-text-muted">
                                        {{ $row['last_message']->created_at->timezone(config('app.timezone'))->format('H:i') }}
                                    </span>
                                @endif
                            </span>
                            <span class="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs">
                                <span class="text-text-muted">{{ $peer->role?->label() }}</span>
                                <span aria-hidden="true" class="text-border">·</span>
                                <span @class(['font-medium', 'text-success' => $online, 'text-danger' => ! $online || $row['inactive']])>
                                    {{ $row['status_label'] }}
                                </span>
                            </span>
                            <span class="mt-0.5 block truncate text-xs text-text-muted">
                                {{ $row['last_message']?->body ?? '' }}
                            </span>
                        </span>
                    </button>
                </li>
            @empty
                <li class="px-3 py-8 text-center text-sm text-text-muted">
                    <p class="font-medium text-text">Nenhuma conversa ainda.</p>
                    <p class="mt-1">Selecione um colega abaixo para iniciar uma conversa.</p>
                </li>
            @endforelse
        </ul>

        <div class="border-t border-border px-4 py-3">
            <h3 class="text-sm font-semibold text-text">Nova conversa</h3>
            <p class="mt-0.5 text-xs text-text-muted">Equipe disponível da sua clínica</p>
            <div class="mt-3">
                <x-ui.input
                    label="Buscar colega"
                    name="peer_search"
                    wire:model.live.debounce.300ms="peerSearch"
                    placeholder="Buscar colega..."
                />
            </div>
        </div>

        <ul class="max-h-56 overflow-y-auto border-t border-border/70 p-2" role="list" aria-label="Nova conversa">
            @forelse ($this->directoryPeers as $row)
                @php
                    $peer = $row['user'];
                    $online = $row['online'];
                @endphp
                <li>
                    <button
                        type="button"
                        wire:click="startWithPeer({{ $peer->id }})"
                        wire:key="peer-{{ $peer->id }}"
                        class="flex w-full cursor-pointer items-center gap-3 rounded-xl px-3 py-2.5 text-left transition duration-200 hover:bg-background"
                    >
                        <span class="relative shrink-0">
                            @if ($peer->avatarUrl())
                                <img src="{{ $peer->avatarUrl() }}" alt="" class="size-9 rounded-full object-cover">
                            @else
                                <span class="flex size-9 items-center justify-center rounded-full bg-primary text-[11px] font-bold text-white">
                                    {{ $peer->initials() }}
                                </span>
                            @endif
                            <span
                                @class([
                                    'absolute bottom-0 right-0 size-2.5 rounded-full ring-2 ring-surface',
                                    'bg-success' => $online,
                                    'bg-danger' => ! $online,
                                ])
                                aria-hidden="true"
                            ></span>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-text">{{ $peer->name }}</span>
                            <span class="mt-0.5 flex items-center gap-1.5 text-xs text-text-muted">
                                <span>{{ $peer->role?->label() }}</span>
                                <span aria-hidden="true">·</span>
                                <span @class(['font-medium', 'text-success' => $online, 'text-danger' => ! $online])>
                                    {{ $row['status_label'] }}
                                </span>
                            </span>
                        </span>
                    </button>
                </li>
            @empty
                <li class="px-3 py-6 text-center text-sm text-text-muted">
                    Nenhum colega disponível.
                </li>
            @endforelse
        </ul>
    </aside>

    <section class="flex min-h-[28rem] flex-col rounded-2xl border border-border bg-surface shadow-sm">
        @if ($statusMessage !== '')
            <div class="px-4 pt-4">
                <x-ui.alert type="success">{{ $statusMessage }}</x-ui.alert>
            </div>
        @endif
        @if ($errorMessage !== '')
            <div class="px-4 pt-4">
                <x-ui.alert type="danger">{{ $errorMessage }}</x-ui.alert>
            </div>
        @endif

        @if ($this->activeConversation === null)
            <div class="flex flex-1 items-center justify-center p-8">
                <x-ui.empty-state
                    title="Nenhuma conversa selecionada"
                    description="Escolha uma conversa existente ou inicie uma nova com um colega da equipe."
                />
            </div>
        @else
            <header class="flex items-center gap-3 border-b border-border px-4 py-4">
                <span class="relative shrink-0">
                    @if ($this->peer?->avatarUrl())
                        <img src="{{ $this->peer->avatarUrl() }}" alt="" class="size-10 rounded-full object-cover">
                    @else
                        <span class="flex size-10 items-center justify-center rounded-full bg-primary text-xs font-bold text-white">
                            {{ $this->peer?->initials() ?? '?' }}
                        </span>
                    @endif
                    <span
                        @class([
                            'absolute bottom-0 right-0 size-3 rounded-full ring-2 ring-surface',
                            'bg-success' => $this->peerOnline && ($this->peer?->active ?? false),
                            'bg-danger' => ! $this->peerOnline || ! ($this->peer?->active ?? false),
                        ])
                        aria-hidden="true"
                    ></span>
                </span>
                <div class="min-w-0">
                    <h2 class="truncate text-base font-semibold text-text">{{ $this->peer?->name ?? 'Conversa' }}</h2>
                    <p class="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs">
                        <span class="text-text-muted">{{ $this->peer?->role?->label() }}</span>
                        <span aria-hidden="true" class="text-border">·</span>
                        @if (! ($this->peer?->active ?? false))
                            <span class="font-medium text-danger">Usuário inativo</span>
                        @else
                            <span
                                @class([
                                    'inline-block size-2 rounded-full',
                                    'bg-success' => $this->peerOnline,
                                    'bg-danger' => ! $this->peerOnline,
                                ])
                                aria-hidden="true"
                            ></span>
                            <span @class(['font-medium', 'text-success' => $this->peerOnline, 'text-danger' => ! $this->peerOnline])>
                                {{ $this->peerOnline ? 'Online' : 'Offline' }}
                            </span>
                        @endif
                    </p>
                </div>
            </header>

            <div
                class="flex-1 space-y-3 overflow-y-auto px-4 py-4"
                wire:key="thread-{{ $activeConversationId }}"
                x-data
                x-init="$nextTick(() => { $el.scrollTop = $el.scrollHeight })"
            >
                @forelse ($this->messages as $message)
                    @php $mine = (int) $message->sender_id === (int) auth()->id(); @endphp
                    <div wire:key="msg-{{ $message->id }}" @class(['flex', 'justify-end' => $mine, 'justify-start' => ! $mine])>
                        <div @class([
                            'max-w-[80%] rounded-2xl px-4 py-2 text-sm',
                            'bg-primary text-white' => $mine,
                            'bg-background text-text' => ! $mine,
                        ])>
                            <p class="whitespace-pre-wrap break-words">{{ $message->body }}</p>
                            <p @class([
                                'mt-1 text-[11px]',
                                'text-white/70' => $mine,
                                'text-text-muted' => ! $mine,
                            ])>
                                {{ $message->created_at?->timezone(config('app.timezone'))->format('d/m H:i') }}
                            </p>
                        </div>
                    </div>
                @empty
                    <p class="py-8 text-center text-sm text-text-muted">Nenhuma mensagem nesta conversa.</p>
                @endforelse
            </div>

            <form wire:submit="sendMessage" class="border-t border-border p-4" wire:key="composer-{{ $activeConversationId }}">
                <label for="message_body" class="sr-only">Mensagem</label>
                @if ($this->canSendToPeer)
                    <div class="flex gap-2">
                        <textarea
                            id="message_body"
                            wire:model="body"
                            rows="2"
                            maxlength="2000"
                            placeholder="Digite uma mensagem..."
                            class="min-h-11 flex-1 rounded-xl border border-border bg-surface px-3 py-2 text-sm text-text placeholder:text-text-muted focus:border-accent"
                        ></textarea>
                        <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="sendMessage">
                            <span wire:loading.remove wire:target="sendMessage">Enviar</span>
                            <span wire:loading wire:target="sendMessage">...</span>
                        </x-ui.button>
                    </div>
                @else
                    <p class="rounded-xl border border-border bg-background px-3 py-3 text-sm text-text-muted">
                        Não é possível enviar novas mensagens para este usuário.
                    </p>
                @endif
            </form>
        @endif
    </section>
</div>
