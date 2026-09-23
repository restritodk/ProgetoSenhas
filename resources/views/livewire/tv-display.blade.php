@php
    $p = $presentation ?? [];
    $logoUrl = $p['logo_url'] ?? null;
    $slogan = trim((string) ($p['slogan'] ?? ''));
    $showDate = (bool) ($p['show_date'] ?? true);
    $showTime = (bool) ($p['show_time'] ?? true);
    $showConnection = (bool) ($p['show_connection_status'] ?? true);
    $showTicketType = (bool) ($p['show_ticket_type'] ?? true);
    $footerEnabled = (bool) ($p['footer_enabled'] ?? true);
    $footer1 = $p['footer_1'] ?? ['title' => 'Acompanhe sua senha', 'text' => 'Fique atento ao painel.'];
    $footer2 = $p['footer_2'] ?? ['title' => 'Dirija-se à mesa', 'text' => 'Quando sua senha for chamada.'];
    $footer3 = $p['footer_3'] ?? ['title' => 'Aguarde sua vez', 'text' => 'Obrigado pela compreensão.'];
    $chimeEnabled = (bool) ($p['chime_enabled'] ?? true);
    $speechEnabled = (bool) ($p['speech_enabled'] ?? true);
    $chimeVolume = (int) ($p['chime_volume'] ?? 70);
    $primaryColor = (string) ($p['primary_color'] ?? '#1e3a5f');
    $accentColor = (string) ($p['accent_color'] ?? '#2563eb');
    $onPrimary = (string) ($p['on_primary_color'] ?? '#ffffff');
    $logoBackground = \App\Support\LogoSurface::normalizeMode($p['logo_background'] ?? 'transparent');
    $logoBackgroundColor = \App\Support\LogoSurface::normalizeColor($p['logo_background_color'] ?? '#FFFFFF');
    $logoSurfaceCss = \App\Support\LogoSurface::cssBackground($logoBackground, $logoBackgroundColor);
@endphp
<div
    wire:poll.3s="refreshFeed"
    class="tv-shell flex flex-col overflow-hidden"
    style="--color-primary: {{ $primaryColor }}; --color-accent: {{ $accentColor }}; --color-primary-dark: {{ $primaryColor }}; --color-primary-light: {{ $primaryColor }};"
    x-data="tvAudioUi(@js([
        'panelToken' => $publicToken,
        'chimeEnabled' => $chimeEnabled,
        'speechEnabled' => $speechEnabled,
        'chimeVolume' => $chimeVolume,
    ]))"
    x-init="init()"
>
    {{-- Stable audio host: never remounted by Livewire morphs that update calls / highlight. --}}
    <div
        wire:ignore
        class="hidden"
        aria-hidden="true"
        x-data
        x-init="$nextTick(() => window.__humanaTvCallAudio && window.__humanaTvCallAudio.ensure(@js($publicToken), @js([
            'chimeEnabled' => $chimeEnabled,
            'speechEnabled' => $speechEnabled,
            'chimeVolume' => $chimeVolume,
        ])))"
    ></div>
    {{-- HEADER --}}
    <header class="tv-header flex shrink-0 items-center justify-between gap-2 border-b-4 border-primary bg-primary px-[max(0.75rem,var(--tv-pad))] sm:gap-4 sm:px-5 lg:px-8" style="color: {{ $onPrimary }};">
        <div class="flex min-w-0 max-w-[55%] items-center lg:max-w-[60%]">
            @if ($logoUrl)
                <div
                    class="tv-brand-logo-wrap tv-brand-logo-wrap--{{ $logoBackground }}"
                    @if ($logoSurfaceCss !== null)
                        style="background: {{ $logoSurfaceCss }};"
                    @endif
                >
                    <img
                        src="{{ $logoUrl }}"
                        alt=""
                        class="tv-brand-logo"
                    >
                </div>
            @else
                <div class="flex min-w-0 items-center gap-2 sm:gap-3">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white/15 text-sm font-bold sm:size-11 sm:text-lg lg:size-12 lg:text-xl" aria-hidden="true">hC</span>
                    <p class="min-w-0 truncate text-base font-bold tracking-tight sm:text-xl md:text-2xl lg:text-3xl">
                        {{ $clinicName !== '' ? $clinicName : config('app.name') }}
                    </p>
                </div>
            @endif
        </div>

        <div class="flex min-w-[10.5rem] shrink-0 flex-col items-end justify-center gap-0.5 sm:min-w-[13rem] sm:gap-1 md:min-w-[15rem]">
            @if ($showDate || $showTime)
            <div
                class="max-w-full text-right leading-none"
                x-data="{
                    now: new Date(),
                    init() {
                        setInterval(() => { this.now = new Date() }, 1000)
                    },
                    formatDate(options) {
                        const raw = this.now.toLocaleDateString('pt-BR', options).toLowerCase()

                        return raw.charAt(0).toUpperCase() + raw.slice(1)
                    },
                    dateLabel() {
                        return this.formatDate({
                            weekday: 'long',
                            day: 'numeric',
                            month: 'long',
                            year: 'numeric',
                        })
                    },
                    dateLabelMedium() {
                        return this.formatDate({
                            weekday: 'long',
                            day: 'numeric',
                            month: 'short',
                        })
                    },
                    dateLabelShort() {
                        return this.formatDate({
                            weekday: 'short',
                            day: '2-digit',
                            month: 'short',
                        })
                    },
                    timeLabel() {
                        return this.now.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
                    }
                }"
                x-init="init()"
            >
                @if ($showDate)
                    <p class="hidden text-[0.7rem] font-medium opacity-70 xl:block xl:text-[0.8125rem]" x-text="dateLabel()"></p>
                    <p class="hidden text-[0.65rem] font-medium opacity-70 sm:block xl:hidden" x-text="dateLabelMedium()"></p>
                    <p class="text-[0.6rem] font-medium opacity-70 sm:hidden" x-text="dateLabelShort()"></p>
                @endif
                @if ($showTime)
                    <p class="tv-time mt-0.5 font-bold tabular-nums tracking-tight sm:mt-1" x-text="timeLabel()"></p>
                @endif
            </div>
            @endif

            <div class="flex flex-wrap items-center justify-end gap-x-1.5 gap-y-1 sm:gap-x-2.5">
                @if ($showConnection)
                <p class="text-[0.6rem] sm:text-xs">
                    @if ($connectionStatus === 'online')
                        <span class="inline-flex items-center gap-1 text-emerald-300 sm:gap-1.5">
                            <span class="size-1.5 rounded-full bg-emerald-400 sm:size-2" aria-hidden="true"></span>
                            Online
                        </span>
                    @elseif ($connectionStatus === 'reconnecting')
                        <span class="inline-flex items-center gap-1 text-amber-300 sm:gap-1.5">
                            <span class="size-1.5 rounded-full bg-amber-400 sm:size-2" aria-hidden="true"></span>
                            Reconectando
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 text-red-300 sm:gap-1.5">
                            <span class="size-1.5 rounded-full bg-red-400 sm:size-2" aria-hidden="true"></span>
                            Offline
                        </span>
                    @endif
                </p>
                @endif
                <button
                    type="button"
                    class="inline-flex min-h-7 items-center justify-center rounded-md border border-white/20 bg-white/10 px-1.5 py-0.5 text-[0.6rem] font-medium text-white/90 transition hover:bg-white/20 sm:min-h-8 sm:px-2.5 sm:text-xs"
                    @click="enableSound()"
                    x-text="soundEnabled ? '🔊 Som ativo' : '🔊 Ativar som'"
                ></button>
                <button
                    type="button"
                    class="inline-flex min-h-7 items-center justify-center rounded-md border border-white/20 bg-white/10 px-1.5 py-0.5 text-[0.6rem] font-medium text-white/90 transition hover:bg-white/20 sm:min-h-8 sm:px-2.5 sm:text-xs"
                    @click="toggleFullscreen()"
                    x-text="isFullscreen ? '⛶ Sair' : '⛶ Tela cheia'"
                ></button>
            </div>
        </div>
    </header>

    @if (! $available)
        <main class="flex min-h-0 flex-1 items-center justify-center bg-background px-4 text-center text-text sm:px-8">
            <div class="max-w-2xl rounded-2xl border-2 border-primary/20 bg-surface p-6 shadow-lg sm:rounded-3xl sm:p-12">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-accent sm:text-sm">{{ config('app.name') }}</p>
                <h1 class="mt-3 text-2xl font-bold text-primary sm:mt-4 sm:text-4xl">Painel indisponível</h1>
                <p class="mt-3 text-sm text-text-muted sm:mt-4 sm:text-lg">Este painel está inativo ou temporariamente sem dados operacionais.</p>
            </div>
        </main>
    @else
        {{-- MAIN: mobile prioriza senha; md+ usa grid mídia/chamadas --}}
        <main
            class="grid min-h-0 flex-1 grid-cols-1 gap-[var(--tv-gap)] p-[var(--tv-pad)]
                max-md:grid-rows-[minmax(0,1.15fr)_minmax(0,0.95fr)_minmax(7.5rem,0.7fr)]
                md:grid-cols-[minmax(0,1.55fr)_minmax(16rem,1fr)]
                lg:grid-cols-[minmax(0,1.9fr)_minmax(20rem,1fr)]
                xl:grid-cols-[minmax(0,2fr)_minmax(22rem,1fr)]"
        >
            {{-- MEDIA: nested player owns its DOM via wire:ignore inside the child component --}}
            <section class="relative min-h-0 overflow-hidden rounded-xl border-2 border-primary bg-primary shadow-sm max-md:order-3 sm:rounded-2xl sm:border-[3px] md:order-none">
                <livewire:tv-media-player
                    wire:key="tv-media-{{ $publicToken }}"
                    :public-token="$publicToken"
                    :clinic-name="$clinicName"
                />
            </section>

            {{-- CALLS COLUMN --}}
            <aside class="flex min-h-0 flex-col gap-[var(--tv-gap)] max-md:contents md:order-none">
                {{-- CURRENT CALL --}}
                <section
                    class="flex min-h-0 flex-col overflow-hidden rounded-xl border-2 border-primary bg-surface max-md:order-1 sm:rounded-2xl sm:border-[3px] md:flex-[1.15] {{ $highlight ? 'tv-highlight' : '' }}"
                    wire:key="current-call-{{ $currentCall['id'] ?? 'none' }}"
                >
                    <div class="shrink-0 bg-primary px-3 py-1.5 text-center sm:px-4 sm:py-2.5 md:py-3">
                        <h2 class="text-xs font-bold uppercase tracking-[0.18em] text-white sm:text-sm sm:tracking-[0.22em] md:text-base lg:text-lg">Senha atual</h2>
                    </div>

                    <div class="flex min-h-0 flex-1 flex-col items-center justify-center px-3 py-2 text-center sm:px-6 sm:py-4">
                        @if ($currentCall)
                            <p class="tv-code {{ $highlight ? 'tv-code-pulse' : '' }} font-bold leading-none tracking-tight text-danger">
                                {{ $currentCall['display_code'] }}
                            </p>
                            <div class="my-2 h-0.5 w-12 bg-danger/50 sm:my-3 sm:w-16 md:my-4 md:w-24" aria-hidden="true"></div>
                            <p class="tv-desk font-bold uppercase tracking-wide text-primary">
                                {{ $currentCall['desk_name'] }}
                            </p>
                            @if ($showTicketType)
                                <p class="mt-1 text-xs font-medium text-text-muted sm:mt-2 sm:text-sm md:text-base">
                                    {{ $currentCall['ticket_type_name'] }}
                                </p>
                            @endif
                        @else
                            <p class="tv-code font-bold leading-none text-primary/30">—</p>
                            <div class="my-2 h-0.5 w-12 bg-border sm:my-3 sm:w-16 md:my-4" aria-hidden="true"></div>
                            <p class="text-sm font-semibold uppercase tracking-wide text-text-muted sm:text-lg md:text-xl">Aguardando chamada</p>
                        @endif
                    </div>
                </section>

                {{-- RECENT CALLS --}}
                <section class="flex min-h-0 flex-col overflow-hidden rounded-xl border-2 border-primary bg-surface max-md:order-2 sm:rounded-2xl sm:border-[3px] md:flex-1">
                    <div class="shrink-0 bg-primary px-3 py-1.5 text-center sm:px-4 sm:py-2.5 md:py-3">
                        <h2 class="text-xs font-bold uppercase tracking-[0.18em] text-white sm:text-sm sm:tracking-[0.22em] md:text-base lg:text-lg">Últimas chamadas</h2>
                    </div>

                    <div class="min-h-0 flex-1 overflow-hidden px-1.5 py-1.5 sm:px-3 sm:py-3">
                        @if (count($recentCalls) === 0)
                            <p class="flex h-full items-center justify-center text-xs text-text-muted sm:text-sm md:text-base">Nenhuma chamada registrada.</p>
                        @else
                            <table class="h-full w-full table-fixed text-left">
                                <caption class="sr-only">Últimas chamadas desta unidade</caption>
                                <thead>
                                    <tr class="text-[0.55rem] uppercase tracking-wide text-text-muted sm:text-[0.65rem] md:text-xs">
                                        <th scope="col" class="px-1 pb-1 font-semibold sm:px-1.5 sm:pb-2 md:px-2">Senha</th>
                                        <th scope="col" class="px-1 pb-1 font-semibold sm:px-1.5 sm:pb-2 md:px-2">Tipo</th>
                                        <th scope="col" class="px-1 pb-1 font-semibold sm:px-1.5 sm:pb-2 md:px-2">Mesa</th>
                                        <th scope="col" class="px-1 pb-1 text-right font-semibold sm:px-1.5 sm:pb-2 md:px-2">Horário</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recentCalls as $call)
                                        <tr wire:key="tv-call-{{ $call['id'] }}" class="border-t border-border/80">
                                            <td class="px-1 py-1 align-middle sm:px-1.5 sm:py-2 md:px-2 md:py-2.5">
                                                <span class="tv-history-code font-bold text-primary">
                                                    {{ $call['display_code'] }}
                                                    @if (($call['call_type'] ?? '') === 'recall')
                                                        <span class="ml-0.5 text-[0.7em] font-semibold text-accent" title="Rechamada">↻</span>
                                                    @endif
                                                </span>
                                            </td>
                                            <td class="px-1 py-1 align-middle sm:px-1.5 md:px-2">
                                                <span class="inline-flex min-w-6 items-center justify-center rounded-md bg-primary/10 px-1 py-0.5 text-[0.65rem] font-bold text-primary sm:min-w-8 sm:px-1.5 sm:text-xs md:min-w-9 md:text-sm">
                                                    {{ $call['ticket_type_prefix'] ?? '—' }}
                                                </span>
                                            </td>
                                            <td class="truncate px-1 py-1 align-middle text-[0.7rem] font-semibold text-text sm:px-1.5 sm:text-sm md:px-2 md:text-base lg:text-lg">
                                                {{ $call['desk_name'] }}
                                            </td>
                                            <td class="px-1 py-1 text-right align-middle text-[0.7rem] font-medium tabular-nums text-text-muted sm:px-1.5 sm:text-sm md:px-2 md:text-base">
                                                {{ $call['called_at'] }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </section>
            </aside>
        </main>

        {{-- FOOTER --}}
        @if ($footerEnabled)
        <footer class="tv-footer flex shrink-0 items-stretch bg-gradient-to-r from-success via-accent to-primary text-white">
            <div class="mx-auto grid h-full w-full max-w-[100rem] grid-cols-3 divide-x divide-white/20">
                <div class="flex items-center justify-center gap-1.5 px-1 text-center sm:gap-3 sm:px-3 lg:px-4">
                    <span class="hidden text-xl md:inline lg:text-2xl" aria-hidden="true">👁</span>
                    <div class="min-w-0">
                        <p class="truncate text-[0.55rem] font-bold uppercase tracking-wide sm:text-xs md:text-sm lg:text-base">{{ $footer1['title'] ?? '' }}</p>
                        <p class="mt-0.5 hidden text-[0.65rem] text-white/85 sm:block md:text-xs">{{ $footer1['text'] ?? '' }}</p>
                    </div>
                </div>
                <div class="flex items-center justify-center gap-1.5 px-1 text-center sm:gap-3 sm:px-3 lg:px-4">
                    <span class="hidden text-xl md:inline lg:text-2xl" aria-hidden="true">➡</span>
                    <div class="min-w-0">
                        <p class="truncate text-[0.55rem] font-bold uppercase tracking-wide sm:text-xs md:text-sm lg:text-base">{{ $footer2['title'] ?? '' }}</p>
                        <p class="mt-0.5 hidden text-[0.65rem] text-white/85 sm:block md:text-xs">{{ $footer2['text'] ?? '' }}</p>
                    </div>
                </div>
                <div class="flex items-center justify-center gap-1.5 px-1 text-center sm:gap-3 sm:px-3 lg:px-4">
                    <span class="hidden text-xl md:inline lg:text-2xl" aria-hidden="true">🤝</span>
                    <div class="min-w-0">
                        <p class="truncate text-[0.55rem] font-bold uppercase tracking-wide sm:text-xs md:text-sm lg:text-base">{{ $footer3['title'] ?? '' }}</p>
                        <p class="mt-0.5 hidden text-[0.65rem] text-white/85 sm:block md:text-xs">{{ $footer3['text'] ?? '' }}</p>
                    </div>
                </div>
            </div>
        </footer>
        @endif
    @endif
</div>

<script>
    (function () {
        if (window.__humanaTvCallAudio) {
            return;
        }

        const storageKey = (token) => 'tvCallAudioUnlocked:' + token;

        window.__humanaTvCallAudio = {
            panels: Object.create(null),

            ensure(token, options = {}) {
                if (!token) {
                    return this.panels[token];
                }

                if (!this.panels[token]) {
                    this.panels[token] = {
                        soundEnabled: false,
                        speaking: false,
                        lastPlayedCallId: null,
                        audioCtx: null,
                        clinicChimeEnabled: options.chimeEnabled !== false,
                        clinicSpeechEnabled: options.speechEnabled !== false,
                        chimeVolume: Math.max(0, Math.min(100, Number(options.chimeVolume ?? 70))),
                    };

                    try {
                        this.panels[token].soundEnabled = sessionStorage.getItem(storageKey(token)) === '1';
                    } catch (e) {
                        this.panels[token].soundEnabled = false;
                    }
                } else {
                    this.panels[token].clinicChimeEnabled = options.chimeEnabled !== false;
                    this.panels[token].clinicSpeechEnabled = options.speechEnabled !== false;
                    this.panels[token].chimeVolume = Math.max(0, Math.min(100, Number(options.chimeVolume ?? 70)));
                }

                return this.panels[token];
            },

            isEnabled(token) {
                return Boolean(this.panels[token]?.soundEnabled);
            },

            async enable(token, options = {}) {
                const panel = this.ensure(token, options);
                panel.soundEnabled = true;

                try {
                    sessionStorage.setItem(storageKey(token), '1');
                } catch (e) {}

                // Unlock AudioContext inside the user gesture for this tab/panel instance.
                await this.ensureAudioContext(panel);

                if (panel.clinicChimeEnabled) {
                    await this.playChime(panel);
                }

                return true;
            },

            async ensureAudioContext(panel) {
                try {
                    const AudioCtx = window.AudioContext || window.webkitAudioContext;
                    if (!AudioCtx) {
                        return null;
                    }
                    if (!panel.audioCtx || panel.audioCtx.state === 'closed') {
                        panel.audioCtx = new AudioCtx();
                    }
                    if (panel.audioCtx.state === 'suspended') {
                        await panel.audioCtx.resume();
                    }
                    return panel.audioCtx;
                } catch (e) {
                    return null;
                }
            },

            announce(payload) {
                if (!payload || typeof payload !== 'object') {
                    return;
                }

                const token = payload.panelToken;
                const callId = payload.callId != null ? Number(payload.callId) : null;
                const panel = this.panels[token] || this.ensure(token);

                if (!panel.soundEnabled) {
                    return;
                }

                // Per-instance dedupe only — other TVs keep their own cursor.
                if (callId !== null && panel.lastPlayedCallId === callId) {
                    return;
                }
                if (callId !== null) {
                    panel.lastPlayedCallId = callId;
                }

                window.dispatchEvent(new CustomEvent('tv-call-audio-begin', { detail: { panelToken: token, callId } }));

                const finish = () => {
                    window.dispatchEvent(new CustomEvent('tv-call-audio-end', { detail: { panelToken: token, callId } }));
                };

                const runSpeech = () => {
                    if (panel.clinicSpeechEnabled) {
                        this.speak(panel, payload.announcement || '', finish);
                    } else {
                        finish();
                    }
                };

                if (panel.clinicChimeEnabled) {
                    this.playChime(panel).then(runSpeech).catch(runSpeech);
                } else {
                    runSpeech();
                }
            },

            async playChime(panel) {
                try {
                    const ctx = await this.ensureAudioContext(panel);
                    if (!ctx) {
                        return;
                    }
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    const peak = Math.max(0.0001, (panel.chimeVolume / 100) * 0.2);
                    const t0 = ctx.currentTime;
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(880, t0);
                    osc.frequency.exponentialRampToValueAtTime(660, t0 + 0.18);
                    gain.gain.setValueAtTime(0.0001, t0);
                    gain.gain.exponentialRampToValueAtTime(peak, t0 + 0.02);
                    gain.gain.exponentialRampToValueAtTime(0.0001, t0 + 0.28);
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start(t0);
                    osc.stop(t0 + 0.3);
                    await new Promise((resolve) => setTimeout(resolve, 320));
                } catch (e) {
                    // Autoplay/policy failures are expected until the operator enables sound.
                }
            },

            speak(panel, text, onEnd) {
                if (!text || !('speechSynthesis' in window)) {
                    if (typeof onEnd === 'function') {
                        onEnd();
                    }
                    return;
                }
                window.speechSynthesis.cancel();
                const utterance = new SpeechSynthesisUtterance(text);
                utterance.lang = 'pt-BR';
                utterance.rate = 0.95;
                panel.speaking = true;
                utterance.onend = () => {
                    panel.speaking = false;
                    if (typeof onEnd === 'function') {
                        onEnd();
                    }
                };
                utterance.onerror = () => {
                    panel.speaking = false;
                    if (typeof onEnd === 'function') {
                        onEnd();
                    }
                };
                window.speechSynthesis.speak(utterance);
            },
        };
    })();

    function tvAudioUi(options = {}) {
        const token = options.panelToken || '';

        return {
            soundEnabled: false,
            isFullscreen: false,
            panelToken: token,
            init() {
                if (window.__humanaTvCallAudio) {
                    window.__humanaTvCallAudio.ensure(token, options);
                    this.soundEnabled = window.__humanaTvCallAudio.isEnabled(token);
                }
                document.addEventListener('fullscreenchange', () => {
                    this.isFullscreen = Boolean(document.fullscreenElement);
                });
            },
            async enableSound() {
                if (!window.__humanaTvCallAudio) {
                    return;
                }
                // Set UI state before awaiting audio unlock so a Livewire morph mid-await
                // cannot leave the button stuck on "Ativar som" while the host is enabled.
                this.soundEnabled = true;
                await window.__humanaTvCallAudio.enable(token, options);
                this.soundEnabled = window.__humanaTvCallAudio.isEnabled(token);
            },
            toggleFullscreen() {
                const root = document.documentElement;
                if (!document.fullscreenElement) {
                    if (root.requestFullscreen) {
                        root.requestFullscreen().catch(() => {});
                    }
                } else if (document.exitFullscreen) {
                    document.exitFullscreen().catch(() => {});
                }
            },
        };
    }
</script>
