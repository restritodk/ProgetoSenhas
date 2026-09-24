@php
    use App\Support\KioskOfferPresentation;
    use App\Support\LogoSurface;

    $kiosk = $this->kiosk;
    $p = $presentation ?? [];
    $clinicName = $p['display_name'] ?? ($kiosk?->clinic?->name ?? config('app.name'));
    $unitName = $kiosk?->unit?->name ?? '';
    $slogan = trim((string) ($p['slogan'] ?? ''));
    $logoUrl = $p['logo_url'] ?? null;
    $logoBackground = LogoSurface::normalizeMode($p['logo_background'] ?? LogoSurface::NONE);
    $logoBackgroundColor = LogoSurface::normalizeColor($p['logo_background_color'] ?? LogoSurface::DEFAULT_CUSTOM_COLOR);
    $logoSurfaceCss = LogoSurface::cssBackground($logoBackground, $logoBackgroundColor);
    $title = $p['title'] ?? 'Retire sua senha';
    $subtitle = $p['subtitle'] ?? 'Selecione o tipo de atendimento';
    $instruction = $p['instruction_text'] ?? 'Toque para retirar';
    $issuedMessage = $p['issued_message'] ?? 'Aguarde sua chamada no painel.';
    $finishText = $p['finish_button_text'] ?? 'Finalizar';
    $autoReturnSeconds = max(3, min(60, (int) ($p['auto_return_seconds'] ?? 3)));
    $autoReturnMs = $autoReturnSeconds * 1000;
    $primaryColor = $p['primary_color'] ?? '#1e3a5f';
    $accentColor = $p['accent_color'] ?? '#2563eb';
    $onPrimary = $p['on_primary_color'] ?? '#ffffff';
    $offers = $this->offeredTypes;
    $offerCount = $offers->count();
    $offersFingerprint = $offers->map(
        fn ($offer) => $offer->id.'-'.$offer->ticket_type_id.'-'.(int) $offer->active.'-'.(int) ($offer->ticketType?->active ?? 0).'-'.$offer->position.'-'.$offer->publicLabel()
    )->implode('|');
    $gridClass = match (true) {
        $offerCount <= 1 => 'kiosk-cards kiosk-cards--one',
        $offerCount === 2 => 'kiosk-cards kiosk-cards--two',
        $offerCount === 3 => 'kiosk-cards kiosk-cards--three',
        $offerCount === 4 => 'kiosk-cards kiosk-cards--four',
        default => 'kiosk-cards kiosk-cards--many',
    };
    $decorativeLines = $slogan !== ''
        ? array_values(array_filter(array_map('trim', preg_split('/[\n,]+/', mb_strtoupper($slogan)) ?: [])))
        : ['CUIDANDO', 'DE PESSOAS,', 'SEMPRE.'];
    $printDispatch = $printDispatch ?? null;
    $browserPrintPayload = $browserPrintPayload ?? null;
    $resultUnitLabel = $browserPrintPayload['unitName']
        ?? (($unitName !== '' && ($kiosk?->sector?->name ?? '') !== '')
            ? $unitName.' · '.$kiosk->sector->name
            : $unitName);
@endphp

<div
    class="kiosk-shell"
    style="--color-primary: {{ $primaryColor }}; --color-accent: {{ $accentColor }}; --color-primary-dark: {{ $primaryColor }}; --color-on-primary: {{ $onPrimary }}; --kiosk-priority: #e8a317;"
    @if ($screen !== 'result' && ! $issuing) wire:poll.3s="refreshAvailability" @endif
    x-data="{
        extractPrintDetail(detail) {
            const payload = detail || {}
            return {
                agentUrl: payload.agentUrl || (payload[0] && payload[0].agentUrl) || null,
                grant: payload.grant || (payload[0] && payload[0].grant) || null,
                signature: payload.signature || (payload[0] && payload[0].signature) || null,
            }
        },
        extractBrowserDetail(detail) {
            const payload = detail || {}
            const first = payload[0] || {}
            return {
                clinicName: payload.clinicName || first.clinicName || '',
                unitName: payload.unitName || first.unitName || '',
                displayCode: payload.displayCode || first.displayCode || '',
                typeLabel: payload.typeLabel || first.typeLabel || '',
                issuedAtLabel: payload.issuedAtLabel || first.issuedAtLabel || '',
                message: payload.message || first.message || '',
                paperWidth: payload.paperWidth || first.paperWidth || '80',
            }
        },
        fillBrowserReceipt(payload) {
            const root = this.$refs.browserReceipt
            if (!root || !payload) return
            root.dataset.width = payload.paperWidth === '58' ? '58' : '80'
            const set = (ref, value) => {
                if (this.$refs[ref]) this.$refs[ref].textContent = value || ''
            }
            set('receiptClinic', payload.clinicName)
            set('receiptUnit', payload.unitName)
            set('receiptCode', payload.displayCode)
            set('receiptType', payload.typeLabel)
            set('receiptAt', payload.issuedAtLabel)
            set('receiptMessage', payload.message)
        },
        runBrowserPrint(payload) {
            if (!payload || !payload.displayCode) return false

            window.__humanaBrowserPrintKeys = window.__humanaBrowserPrintKeys || {}
            const key = String(payload.displayCode) + '|' + String(payload.issuedAtLabel || '')
            if (window.__humanaBrowserPrintKeys[key]) {
                return false
            }
            window.__humanaBrowserPrintKeys[key] = true

            this.fillBrowserReceipt(payload)
            window.print()

            return true
        },
        sendPrint(payload) {
            const agentUrl = payload?.agentUrl
            const grant = payload?.grant
            const signature = payload?.signature
            if (!agentUrl || !grant || !signature) {
                return
            }

            window.__humanaKioskPrintJobs = window.__humanaKioskPrintJobs || {}
            const jobId = grant.jobId || ''
            if (jobId && window.__humanaKioskPrintJobs[jobId]) {
                return
            }
            if (jobId) {
                window.__humanaKioskPrintJobs[jobId] = true
            }

            const maxAttempts = 2
            const post = (attempt) => {
                const controller = new AbortController()
                const timeoutId = window.setTimeout(() => controller.abort(), 4000)
                fetch(agentUrl + '/print/ticket', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ grant, signature }),
                    signal: controller.signal,
                })
                    .then((response) => {
                        if (!response.ok && attempt < maxAttempts) {
                            post(attempt + 1)
                        }
                    })
                    .catch(() => {
                        if (attempt < maxAttempts) {
                            post(attempt + 1)
                        }
                    })
                    .finally(() => window.clearTimeout(timeoutId))
            }
            post(1)
        }
    }"
    @kiosk-print-ticket.window="sendPrint(extractPrintDetail($event.detail))"
    @kiosk-browser-print.window="runBrowserPrint(extractBrowserDetail($event.detail))"
>
    {{-- Comprovante dedicado: oculto na tela, único conteúdo em @media print --}}
    <div
        class="kiosk-print-receipt"
        x-ref="browserReceipt"
        data-width="{{ $browserPrintPayload['paperWidth'] ?? '80' }}"
        aria-hidden="true"
    >
        <p class="kiosk-print-receipt__clinic" x-ref="receiptClinic">{{ $browserPrintPayload['clinicName'] ?? $clinicName }}</p>
        <p class="kiosk-print-receipt__unit" x-ref="receiptUnit">{{ $browserPrintPayload['unitName'] ?? $resultUnitLabel }}</p>
        <p class="kiosk-print-receipt__label">SENHA</p>
        <p class="kiosk-print-receipt__code" x-ref="receiptCode">{{ $browserPrintPayload['displayCode'] ?? $issuedDisplayCode }}</p>
        <p class="kiosk-print-receipt__type" x-ref="receiptType">{{ $browserPrintPayload['typeLabel'] ?? $issuedTypeLabel }}</p>
        <p class="kiosk-print-receipt__at" x-ref="receiptAt">{{ $browserPrintPayload['issuedAtLabel'] ?? $issuedAtLabel }}</p>
        <p class="kiosk-print-receipt__message" x-ref="receiptMessage">{{ $browserPrintPayload['message'] ?? $issuedMessage }}</p>
    </div>

    <div class="kiosk-main kiosk-no-print">
        <header class="kiosk-header">
            <div class="kiosk-header__side kiosk-header__side--left">
                <p class="kiosk-quote" aria-hidden="true">
                    @foreach ($decorativeLines as $line)
                        <span>{{ $line }}</span>
                    @endforeach
                </p>
            </div>

            <div class="kiosk-brand">
                @if ($logoUrl)
                    <div
                        @class([
                            'kiosk-logo-wrap',
                            'kiosk-logo-wrap--surface' => $logoSurfaceCss !== null,
                        ])
                        @if ($logoSurfaceCss !== null)
                            style="background: {{ $logoSurfaceCss }};"
                        @endif
                    >
                        <img src="{{ $logoUrl }}" alt="{{ $clinicName }}" class="kiosk-logo">
                    </div>
                @else
                    <div class="kiosk-brand-fallback" aria-label="{{ $clinicName }}">
                        <span class="kiosk-brand-mark">hC</span>
                        <div>
                            <p class="kiosk-brand-name">{{ $clinicName }}</p>
                            @if ($unitName !== '')
                                <p class="kiosk-brand-unit">{{ $unitName }}</p>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            <div
                class="kiosk-header__side kiosk-header__side--right kiosk-clock"
                x-data="{
                    now: new Date(),
                    init() {
                        this.tick()
                        this._timer = setInterval(() => this.tick(), 1000)
                    },
                    destroy() {
                        if (this._timer) clearInterval(this._timer)
                    },
                    tick() { this.now = new Date() },
                    dateLabel() {
                        const raw = this.now.toLocaleDateString('pt-BR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
                        return raw.charAt(0).toUpperCase() + raw.slice(1)
                    },
                    timeLabel() {
                        return this.now.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
                    }
                }"
                wire:ignore
            >
                <p class="kiosk-clock__date" x-text="dateLabel()"></p>
                <p class="kiosk-clock__time" x-text="timeLabel()"></p>
            </div>
        </header>

        @if ($screen === 'unavailable')
            <section class="kiosk-state" aria-live="polite">
                <div class="kiosk-state-card">
                    <p class="kiosk-state-title">Totem temporariamente indisponível</p>
                    <p class="kiosk-state-text">Procure a recepção ou tente novamente em instantes.</p>
                </div>
            </section>
        @elseif ($screen === 'result')
            <section
                class="kiosk-state kiosk-state--result"
                aria-live="polite"
                aria-atomic="true"
                wire:key="kiosk-result-{{ $issuedTicketId }}"
                x-data="{
                    timerStarted: false,
                    printStarted: false,
                    returnMs: {{ (int) $autoReturnMs }},
                    printPayload: @js($printDispatch),
                    browserPayload: @js($browserPrintPayload),
                    init() {
                        this.armAfterPrint()
                        this.startPrintFlow()
                    },
                    armAfterPrint() {
                        const onAfter = () => this.startTimer()
                        window.addEventListener('afterprint', onAfter, { once: true })
                    },
                    startTimer() {
                        if (this.timerStarted) return
                        this.timerStarted = true
                        window.setTimeout(() => $wire.finish(), this.returnMs)
                    },
                    startPrintFlow() {
                        if (this.printStarted) return
                        this.printStarted = true

                        if (this.browserPayload && this.browserPayload.displayCode) {
                            // Ensure receipt DOM reflects the issued ticket, then window.print().
                            // Chromium pauses timers while the dialog is open; afterprint is a fallback.
                            window.requestAnimationFrame(() => {
                                window.requestAnimationFrame(() => {
                                    $dispatch('kiosk-browser-print', this.browserPayload)
                                    $wire.clearBrowserPrintPayload()
                                    this.startTimer()
                                })
                            })
                            return
                        }

                        // Agent or no print: keep previous behaviour (timer + optional agent POST).
                        this.startTimer()
                        const payload = this.printPayload
                        if (!payload || !payload.agentUrl || !payload.grant || !payload.signature) {
                            $wire.clearPrintDispatch()
                            return
                        }
                        $dispatch('kiosk-print-ticket', payload)
                        $wire.clearPrintDispatch()
                    }
                }"
            >
                <div class="kiosk-result">
                    <div class="kiosk-result__check" aria-hidden="true">
                        <x-kiosk.icon name="check" class="size-16 text-success sm:size-20" />
                    </div>
                    <p class="kiosk-result__eyebrow">Senha emitida</p>
                    <p class="kiosk-result__code">{{ $issuedDisplayCode }}</p>
                    <p class="kiosk-result__type">{{ $issuedTypeLabel }}</p>
                    <p class="kiosk-result__hint">{{ $issuedMessage }}</p>
                    @if ($unitName !== '')
                        <p class="kiosk-result__meta">{{ $unitName }} · {{ $issuedAtLabel }}</p>
                    @endif
                    <button
                        type="button"
                        wire:click="finish"
                        class="kiosk-result__btn"
                    >
                        {{ $finishText }}
                    </button>
                    <p class="kiosk-result__auto">Esta tela volta automaticamente em alguns segundos.</p>
                </div>
            </section>
        @elseif ($errorMessage !== '')
            <section class="kiosk-state" role="alert" aria-live="assertive">
                <div class="kiosk-state-card kiosk-state-card--error">
                    <p class="kiosk-state-title">Não foi possível emitir sua senha</p>
                    <p class="kiosk-state-text">{{ $errorMessage }}</p>
                    <button type="button" wire:click="clearError" class="kiosk-result__btn mt-8">
                        Tentar novamente
                    </button>
                </div>
            </section>
        @else
            <section class="kiosk-hero">
                <h1 class="kiosk-hero__title">{{ $title }}</h1>
                <p class="kiosk-hero__subtitle">{{ $subtitle }}</p>
            </section>

            <section class="kiosk-offers" aria-label="Tipos de atendimento" wire:key="kiosk-offers-{{ md5($offersFingerprint) }}">
                @if ($offers->isEmpty())
                    <div class="kiosk-state-card">
                        <p class="kiosk-state-title">Nenhum atendimento disponível</p>
                        <p class="kiosk-state-text">Procure a recepção para mais informações.</p>
                    </div>
                @else
                    <div class="{{ $gridClass }}" wire:key="kiosk-grid-{{ $offerCount }}-{{ md5($offersFingerprint) }}">
                        @foreach ($offers as $offer)
                            @php
                                $variant = KioskOfferPresentation::variant($offer);
                                $emphasized = KioskOfferPresentation::isEmphasized($offer);
                                $isThisIssuing = $issuing && $issuingTicketTypeId === $offer->ticket_type_id;
                            @endphp
                            <button
                                type="button"
                                wire:key="offer-{{ $offer->id }}-{{ $requestToken }}"
                                wire:click="issue({{ $offer->ticket_type_id }})"
                                wire:loading.attr="disabled"
                                wire:target="issue"
                                @disabled($issuing)
                                @class([
                                    'kiosk-card',
                                    'kiosk-card--priority' => $variant === KioskOfferPresentation::VARIANT_PRIORITY,
                                    'kiosk-card--urgent' => $variant === KioskOfferPresentation::VARIANT_URGENT,
                                    'kiosk-card--busy' => $isThisIssuing,
                                ])
                                aria-label="{{ $offer->publicLabel() }}. {{ $instruction }}"
                            >
                                <span class="kiosk-card__icon" aria-hidden="true">
                                    <x-kiosk.icon :name="KioskOfferPresentation::icon($offer)" class="size-[clamp(2.75rem,6vw,3.75rem)]" />
                                </span>
                                <span class="kiosk-card__title">{{ $offer->publicLabel() }}</span>
                                <span class="kiosk-card__desc">{{ KioskOfferPresentation::description($offer) }}</span>
                                <span @class([
                                    'kiosk-card__cta',
                                    'kiosk-card__cta--priority' => $emphasized,
                                ])>
                                    <span wire:loading.remove wire:target="issue({{ $offer->ticket_type_id }})">
                                        {{ $instruction }}
                                    </span>
                                    <span wire:loading.flex wire:target="issue({{ $offer->ticket_type_id }})" class="items-center gap-2">
                                        Emitindo…
                                    </span>
                                    <x-kiosk.icon name="arrow" class="size-5" />
                                </span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </section>

            <aside class="kiosk-tips" aria-label="Como funciona">
                <div class="kiosk-tip">
                    <x-kiosk.icon name="ticket" class="size-8 text-accent" />
                    <div>
                        <p class="kiosk-tip__title">Retire sua senha</p>
                        <p class="kiosk-tip__text">É rápido e fácil</p>
                    </div>
                </div>
                <div class="kiosk-tip">
                    <x-kiosk.icon name="monitor" class="size-8 text-accent" />
                    <div>
                        <p class="kiosk-tip__title">Aguarde ser chamado</p>
                        <p class="kiosk-tip__text">Acompanhe no painel</p>
                    </div>
                </div>
                <div class="kiosk-tip">
                    <x-kiosk.icon name="heart" class="size-8 text-accent" />
                    <div>
                        <p class="kiosk-tip__title">Conte sempre com a gente</p>
                        <p class="kiosk-tip__text">Saúde é prioridade</p>
                    </div>
                </div>
            </aside>
        @endif
    </div>

    <footer class="kiosk-footer kiosk-no-print">
        <div class="kiosk-footer__waves" aria-hidden="true">
            <svg class="kiosk-footer__wave kiosk-footer__wave--back" viewBox="0 0 1440 90" preserveAspectRatio="none">
                <path d="M0,40 C240,80 480,10 720,35 C960,60 1200,80 1440,30 L1440,90 L0,90 Z"></path>
            </svg>
            <svg class="kiosk-footer__wave kiosk-footer__wave--front" viewBox="0 0 1440 70" preserveAspectRatio="none">
                <path d="M0,28 C200,60 420,8 700,28 C980,48 1220,62 1440,22 L1440,70 L0,70 Z"></path>
            </svg>
        </div>
        <div class="kiosk-footer__content">
            <p class="kiosk-footer__left">
                <x-kiosk.icon name="heart" class="size-4 text-white/90" />
                <span>Humanização em cada atendimento.</span>
            </p>
            <div class="kiosk-footer__right">
                <p class="kiosk-footer__brand">{{ mb_strtoupper($clinicName) }}</p>
                @if ($slogan !== '')
                    <p class="kiosk-footer__tag">{{ mb_strtoupper($slogan) }}</p>
                @elseif ($unitName !== '')
                    <p class="kiosk-footer__tag">{{ mb_strtoupper($unitName) }}</p>
                @endif
            </div>
        </div>
    </footer>
</div>
