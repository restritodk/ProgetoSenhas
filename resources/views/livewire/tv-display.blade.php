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
    $isSmartTv = \App\Support\SmartTvBrowser::matches(request()->userAgent());
    // Relative URL — absolute asset(APP_URL) breaks when the TV opens the panel by IP/host mismatch.
    $smartTvEffectUrl = '/sond/EfeitoSonoroTV.mp3';
    $tvAudioDebug = (bool) config('app.debug');
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
        'isSmartTv' => $isSmartTv,
        'effectUrl' => $smartTvEffectUrl,
        'audioDebug' => $tvAudioDebug,
    ]))"
    x-init="init()"
>
    {{-- Stable audio host (wire:ignore). Audio is visually hidden but NOT display:none —
        some Smart TV browsers refuse to play media inside display:none. --}}
    <div
        wire:ignore
        aria-hidden="true"
        style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);opacity:0;pointer-events:none;"
        x-data
        x-init="$nextTick(() => window.__humanaTvCallAudio && window.__humanaTvCallAudio.ensure(@js($publicToken), @js([
            'chimeEnabled' => $chimeEnabled,
            'speechEnabled' => $speechEnabled,
            'chimeVolume' => $chimeVolume,
            'isSmartTv' => $isSmartTv,
            'effectUrl' => $smartTvEffectUrl,
            'audioDebug' => $tvAudioDebug,
        ])))"
    >
        <audio
            data-tv-call-effect
            preload="auto"
            playsinline
            src="{{ $smartTvEffectUrl }}"
        ></audio>
    </div>
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
            {{-- MEDIA: @island skips morph on TicketCall polls so Alpine/video/YouTube keep playing.
                 Playlist updates stay on the nested TvMediaPlayer poll + window event. --}}
            <section class="relative min-h-0 overflow-hidden rounded-xl border-2 border-primary bg-primary shadow-sm max-md:order-3 sm:rounded-2xl sm:border-[3px] md:order-none">
                @island(name: 'tv-media')
                    <livewire:tv-media-player
                        wire:key="tv-media-{{ $publicToken }}"
                        :public-token="$publicToken"
                        :clinic-name="$clinicName"
                    />
                @endisland
            </section>

            {{-- CALLS COLUMN --}}
            <aside class="flex min-h-0 flex-col gap-[var(--tv-gap)] max-md:contents md:order-none">
                {{-- CURRENT CALL --}}
                <section
                    class="flex min-h-0 flex-col overflow-hidden rounded-xl border-2 border-primary bg-surface max-md:order-1 sm:rounded-2xl sm:border-[3px] md:flex-[1.55] {{ $highlight ? 'tv-highlight' : '' }}"
                    wire:key="current-call-{{ $currentCall['id'] ?? 'none' }}"
                >
                    <div class="shrink-0 bg-primary px-3 py-1.5 text-center sm:px-4 sm:py-2.5 md:py-3">
                        <h2 class="text-xs font-bold uppercase tracking-[0.18em] text-white sm:text-sm sm:tracking-[0.22em] md:text-base lg:text-lg">Senha atual</h2>
                    </div>

                    <div class="flex min-h-0 flex-1 flex-col items-center justify-center gap-1.5 px-2 py-2 text-center sm:gap-2.5 sm:px-4 sm:py-4 md:gap-3 md:px-5 md:py-5">
                        @if ($currentCall)
                            <p class="tv-code {{ $highlight ? 'tv-code-pulse' : '' }} w-full shrink-0 font-bold tracking-tight text-danger">
                                {{ $currentCall['display_code'] }}
                            </p>
                            <div class="h-0.5 w-16 shrink-0 bg-danger/50 sm:w-24 md:w-32" aria-hidden="true"></div>
                            <p class="tv-desk shrink-0 font-bold uppercase tracking-wide text-primary">
                                {{ $currentCall['desk_name'] }}
                            </p>
                            @if ($showTicketType)
                                <p class="shrink-0 text-[0.7rem] font-medium leading-snug text-text-muted sm:text-sm md:text-base">
                                    {{ $currentCall['ticket_type_name'] }}
                                </p>
                            @endif
                        @else
                            <p class="tv-code w-full font-bold text-primary/30">—</p>
                            <div class="h-0.5 w-16 shrink-0 bg-border sm:w-24" aria-hidden="true"></div>
                            <p class="text-sm font-semibold uppercase tracking-wide text-text-muted sm:text-lg md:text-xl">Aguardando chamada</p>
                        @endif
                    </div>
                </section>

                {{-- RECENT CALLS --}}
                <section class="flex min-h-0 flex-col overflow-hidden rounded-xl border-2 border-primary bg-surface max-md:order-2 sm:rounded-2xl sm:border-[3px] md:flex-[0.85]">
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
        const DEFAULT_EFFECT_URL = '/sond/EfeitoSonoroTV.mp3';

        window.__humanaTvCallAudio = {
            panels: Object.create(null),
            debug: false,

            /**
             * Central Smart TV detection. Keep aligned with App\Support\SmartTvBrowser.
             * Does not treat mobile SamsungBrowser alone as a Smart TV.
             */
            isSmartTvBrowser(userAgent) {
                const ua = String(userAgent || (typeof navigator !== 'undefined' ? navigator.userAgent : '') || '');
                if (!ua) {
                    return false;
                }
                if (/\bTizen\b/i.test(ua)) {
                    return true;
                }
                if (/SMART[\s_-]?TV/i.test(ua)) {
                    return true;
                }
                if (/\bSmartTV\b/i.test(ua)) {
                    return true;
                }
                if (/\bHbbTV\b/i.test(ua)) {
                    return true;
                }
                if (/\bWeb0S\b/i.test(ua) || (/\bwebOS\b/i.test(ua) && /\bTV\b/i.test(ua))) {
                    return true;
                }
                if (/SamsungBrowser/i.test(ua)) {
                    return /\b(TV|Tizen|SMART[\s_-]?TV|SmartTV)\b/i.test(ua);
                }
                return false;
            },

            log(message, detail) {
                try {
                    if (this.debug) {
                        if (detail !== undefined) {
                            console.info('[TV Audio]', message, detail);
                        } else {
                            console.info('[TV Audio]', message);
                        }
                    }
                } catch (e) {}
            },

            warn(message, detail) {
                try {
                    if (detail !== undefined) {
                        console.warn('[TV Audio]', message, detail);
                    } else {
                        console.warn('[TV Audio]', message);
                    }
                } catch (e) {}
            },

            ensure(token, options = {}) {
                if (!token) {
                    return this.panels[token];
                }

                const hintSmartTv = options.isSmartTv === true
                    || this.isSmartTvBrowser(options.userAgent);
                const effectUrl = typeof options.effectUrl === 'string' && options.effectUrl !== ''
                    ? options.effectUrl
                    : DEFAULT_EFFECT_URL;

                if (options.audioDebug === true) {
                    this.debug = true;
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
                        isSmartTv: hintSmartTv || this.isSmartTvBrowser(),
                        effectUrl: effectUrl,
                        effectAudio: null,
                        effectUnlocked: false,
                    };

                    try {
                        this.panels[token].soundEnabled = sessionStorage.getItem(storageKey(token)) === '1';
                    } catch (e) {
                        this.panels[token].soundEnabled = false;
                    }

                    this.log('smartTv=' + String(this.panels[token].isSmartTv));
                    if (this.panels[token].isSmartTv) {
                        this.ensureSmartTvEffect(this.panels[token]);
                    }
                } else {
                    this.panels[token].clinicChimeEnabled = options.chimeEnabled !== false;
                    this.panels[token].clinicSpeechEnabled = options.speechEnabled !== false;
                    this.panels[token].chimeVolume = Math.max(0, Math.min(100, Number(options.chimeVolume ?? 70)));
                    this.panels[token].isSmartTv = hintSmartTv || this.panels[token].isSmartTv || this.isSmartTvBrowser();
                    this.panels[token].effectUrl = effectUrl;
                    if (this.panels[token].isSmartTv) {
                        this.ensureSmartTvEffect(this.panels[token]);
                    }
                }

                return this.panels[token];
            },

            isEnabled(token) {
                return Boolean(this.panels[token]?.soundEnabled);
            },

            ensureSmartTvEffect(panel) {
                if (panel.effectAudio) {
                    return panel.effectAudio;
                }

                const url = panel.effectUrl || DEFAULT_EFFECT_URL;
                let audio = null;
                try {
                    audio = document.querySelector('audio[data-tv-call-effect]');
                } catch (e) {
                    audio = null;
                }

                if (!audio) {
                    audio = document.createElement('audio');
                    audio.setAttribute('data-tv-call-effect', '1');
                    audio.preload = 'auto';
                    audio.playsInline = true;
                    audio.setAttribute('playsinline', '');
                    try {
                        document.body.appendChild(audio);
                    } catch (e) {}
                }

                try {
                    // Prefer relative path so host/IP mismatches do not break the TV.
                    if (!audio.getAttribute('src') || audio.getAttribute('src') !== url) {
                        audio.setAttribute('src', url);
                        audio.src = url;
                    }
                    audio.preload = 'auto';
                    audio.playsInline = true;
                    audio.load();
                } catch (e) {
                    this.warn('effect load failed', e && e.message ? e.message : e);
                }

                panel.effectAudio = audio;
                return audio;
            },

            /**
             * Must play UNMUTED inside the user gesture. Muted unlock does not authorize
             * later unmuted play() on many Samsung/Tizen browsers.
             */
            async unlockSmartTvEffect(panel) {
                const audio = this.ensureSmartTvEffect(panel);
                audio.muted = false;
                audio.volume = 1;

                try {
                    if (audio.readyState < 2) {
                        await new Promise((resolve) => {
                            let settled = false;
                            const finish = () => {
                                if (settled) {
                                    return;
                                }
                                settled = true;
                                audio.removeEventListener('canplay', finish);
                                audio.removeEventListener('loadeddata', finish);
                                resolve();
                            };
                            audio.addEventListener('canplay', finish);
                            audio.addEventListener('loadeddata', finish);
                            setTimeout(finish, 2500);
                            try {
                                audio.load();
                            } catch (e) {}
                        });
                    }
                } catch (e) {}

                try {
                    audio.pause();
                    try {
                        audio.currentTime = 0;
                    } catch (e) {}
                    const playPromise = audio.play();
                    if (playPromise && typeof playPromise.then === 'function') {
                        await playPromise;
                    }
                    panel.effectUnlocked = true;
                    this.log('effect unlocked (unmuted play on gesture)');
                    // Let a short burst confirm sound, then stop — full play happens on TicketCall.
                    await new Promise((resolve) => setTimeout(resolve, 450));
                    try {
                        audio.pause();
                        audio.currentTime = 0;
                    } catch (e) {}
                } catch (e) {
                    panel.effectUnlocked = false;
                    this.warn('effect unlock failed', e && e.message ? e.message : e);
                    // Last resort: Web Audio chime inside the same gesture.
                    try {
                        await this.ensureAudioContext(panel);
                        await this.playChime(panel);
                    } catch (err) {}
                }
            },

            async enable(token, options = {}) {
                const panel = this.ensure(token, options);
                panel.soundEnabled = true;

                try {
                    sessionStorage.setItem(storageKey(token), '1');
                } catch (e) {}

                if (panel.isSmartTv || this.isSmartTvBrowser()) {
                    panel.isSmartTv = true;
                    await this.unlockSmartTvEffect(panel);
                    return true;
                }

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
                    this.warn('announce skipped — sound not enabled');
                    return;
                }

                // Per-instance dedupe only — other TVs keep their own cursor.
                if (callId !== null && panel.lastPlayedCallId === callId) {
                    return;
                }
                if (callId !== null) {
                    panel.lastPlayedCallId = callId;
                }

                // One utterance / effect at a time for this panel tab.
                this.stopSpeech(panel);

                window.dispatchEvent(new CustomEvent('tv-call-audio-begin', { detail: { panelToken: token, callId } }));

                const finish = () => {
                    panel.speaking = false;
                    window.dispatchEvent(new CustomEvent('tv-call-audio-end', { detail: { panelToken: token, callId } }));
                };

                // Smart TV: MP3 effect only — no speechSynthesis / robotic TTS.
                if (panel.isSmartTv || this.isSmartTvBrowser()) {
                    panel.isSmartTv = true;
                    this.log('smart-tv effect selected');
                    this.playSmartTvEffect(panel, finish);
                    return;
                }

                const runSpeech = () => {
                    if (panel.clinicSpeechEnabled) {
                        this.speak(
                            panel,
                            payload.announcement || '',
                            finish,
                            payload.audioUrl || payload.audio_url || null
                        );
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

            playSmartTvEffect(panel, onEnd) {
                const safeEnd = () => {
                    panel.speaking = false;
                    if (typeof onEnd === 'function') {
                        onEnd();
                    }
                };

                let audio;
                try {
                    audio = this.ensureSmartTvEffect(panel);
                } catch (e) {
                    this.warn('effect error', 'ensure');
                    this.playChime(panel).finally(safeEnd);
                    return;
                }

                panel.speaking = true;
                audio.muted = false;
                audio.volume = 1;

                let settled = false;
                const done = (reason) => {
                    if (settled) {
                        return;
                    }
                    settled = true;
                    try {
                        audio.onended = null;
                        audio.onerror = null;
                    } catch (e) {}
                    if (reason === 'error') {
                        this.warn('effect error — falling back to chime');
                        this.playChime(panel).finally(safeEnd);
                        return;
                    }
                    this.log('effect ended');
                    safeEnd();
                };

                const startPlay = () => {
                    try {
                        audio.onended = () => done('ended');
                        audio.onerror = () => done('error');
                        try {
                            audio.pause();
                        } catch (e) {}
                        try {
                            if (audio.readyState >= 1) {
                                audio.currentTime = 0;
                            }
                        } catch (e) {}
                        this.log('effect playing');
                        const playPromise = audio.play();
                        if (playPromise && typeof playPromise.then === 'function') {
                            playPromise.then(() => {
                                panel.effectUnlocked = true;
                            }).catch((err) => {
                                this.warn('effect play rejected', err && err.message ? err.message : err);
                                done('error');
                            });
                        }
                    } catch (e) {
                        this.warn('effect error', 'play');
                        done('error');
                    }
                };

                if (audio.readyState >= 2) {
                    startPlay();
                    return;
                }

                const onReady = () => {
                    audio.removeEventListener('canplay', onReady);
                    audio.removeEventListener('loadeddata', onReady);
                    startPlay();
                };
                audio.addEventListener('canplay', onReady);
                audio.addEventListener('loadeddata', onReady);
                try {
                    audio.load();
                } catch (e) {}
                setTimeout(() => {
                    if (!settled) {
                        startPlay();
                    }
                }, 2000);
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

            stopSpeech(panel) {
                try {
                    if ('speechSynthesis' in window) {
                        window.speechSynthesis.cancel();
                    }
                } catch (e) {}
                this.stopFallbackAudio(panel);
                this.stopSmartTvEffect(panel);
                panel.speaking = false;
                panel._speechStarted = false;
                if (panel._speechProbeTimer) {
                    clearTimeout(panel._speechProbeTimer);
                    panel._speechProbeTimer = null;
                }
            },

            stopSmartTvEffect(panel) {
                if (!panel.effectAudio) {
                    return;
                }
                try {
                    panel.effectAudio.onended = null;
                    panel.effectAudio.onerror = null;
                    panel.effectAudio.pause();
                    panel.effectAudio.currentTime = 0;
                } catch (e) {}
            },

            stopFallbackAudio(panel) {
                if (panel.fallbackAudio) {
                    try {
                        panel.fallbackAudio.onended = null;
                        panel.fallbackAudio.onerror = null;
                        panel.fallbackAudio.pause();
                        panel.fallbackAudio.removeAttribute('src');
                        panel.fallbackAudio.load();
                    } catch (e) {}
                    panel.fallbackAudio = null;
                }
            },

            /**
             * Desktop only: prefer speechSynthesis when it actually starts speaking.
             * Smart TV must not reach this path (announce routes to MP3 effect).
             * Desktop fallback WAV is kept for browsers where speechSynthesis is a stub.
             */
            speak(panel, text, onEnd, audioUrl) {
                const safeEnd = () => {
                    panel.speaking = false;
                    if (typeof onEnd === 'function') {
                        onEnd();
                    }
                };

                if (panel.isSmartTv || this.isSmartTvBrowser()) {
                    // Hard guard: Smart TV must not use speech or robotic TTS.
                    safeEnd();
                    return;
                }

                if (!text) {
                    safeEnd();
                    return;
                }

                const playFallback = () => {
                    if (!audioUrl) {
                        safeEnd();
                        return;
                    }
                    this.playAudioUrl(panel, audioUrl, safeEnd);
                };

                if (!('speechSynthesis' in window)) {
                    playFallback();
                    return;
                }

                try {
                    window.speechSynthesis.cancel();
                } catch (e) {}

                this.stopFallbackAudio(panel);

                let settled = false;
                panel.speaking = true;
                panel._speechStarted = false;

                const utterance = new SpeechSynthesisUtterance(text);
                utterance.lang = 'pt-BR';
                utterance.rate = 0.95;

                const settleNative = () => {
                    if (settled) {
                        return;
                    }
                    settled = true;
                    if (panel._speechProbeTimer) {
                        clearTimeout(panel._speechProbeTimer);
                        panel._speechProbeTimer = null;
                    }
                    safeEnd();
                };

                const settleFallback = () => {
                    if (settled) {
                        return;
                    }
                    settled = true;
                    if (panel._speechProbeTimer) {
                        clearTimeout(panel._speechProbeTimer);
                        panel._speechProbeTimer = null;
                    }
                    try {
                        window.speechSynthesis.cancel();
                    } catch (e) {}
                    playFallback();
                };

                utterance.onstart = () => {
                    panel._speechStarted = true;
                    if (panel._speechProbeTimer) {
                        clearTimeout(panel._speechProbeTimer);
                        panel._speechProbeTimer = null;
                    }
                };

                utterance.onend = () => {
                    if (!panel._speechStarted) {
                        // Instant end without start → API is a stub.
                        settleFallback();
                        return;
                    }
                    settleNative();
                };

                utterance.onerror = () => {
                    settleFallback();
                };

                // If onstart does not fire, treat as non-functional TTS.
                panel._speechProbeTimer = setTimeout(() => {
                    if (!panel._speechStarted) {
                        settleFallback();
                    }
                }, 1200);

                try {
                    window.speechSynthesis.speak(utterance);
                } catch (e) {
                    settleFallback();
                }
            },

            playAudioUrl(panel, url, onEnd) {
                if (panel.isSmartTv || this.isSmartTvBrowser()) {
                    if (typeof onEnd === 'function') {
                        onEnd();
                    }
                    return;
                }

                this.stopFallbackAudio(panel);
                panel.speaking = true;

                const audio = new Audio();
                panel.fallbackAudio = audio;
                audio.preload = 'auto';

                const done = () => {
                    panel.speaking = false;
                    panel.fallbackAudio = null;
                    if (typeof onEnd === 'function') {
                        onEnd();
                    }
                };

                audio.onended = done;
                audio.onerror = done;

                try {
                    audio.src = url;
                    const playPromise = audio.play();
                    if (playPromise && typeof playPromise.catch === 'function') {
                        playPromise.catch(() => done());
                    }
                } catch (e) {
                    done();
                }
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
