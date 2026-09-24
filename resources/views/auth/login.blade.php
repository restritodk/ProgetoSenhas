<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar · {{ $loginBranding->productName }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-page min-h-screen bg-background font-sans text-text antialiased">
    @php
        $logoSurfaceCss = \App\Support\LogoSurface::cssBackground(
            $loginBranding->logoBackground,
            $loginBranding->logoBackgroundColor,
        );
        $formErrorId = 'login-form-error';
        $hasEmailError = $errors->has('email');
        $hasPasswordError = $errors->has('password');
        $hasFormError = $hasEmailError || $hasPasswordError;
        $inputClass = 'login-field min-h-12 w-full rounded-xl border border-border bg-surface py-2.5 pl-11 pr-3.5 text-sm text-text shadow-sm transition duration-200 placeholder:text-text-muted focus:border-accent focus:ring-2 focus:ring-accent/25';
    @endphp

    <main class="relative mx-auto flex min-h-screen max-w-5xl items-center justify-center px-4 py-8 sm:px-6 sm:py-12 lg:max-w-6xl">
        <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
            <div class="absolute -left-24 top-10 h-64 w-64 rounded-full bg-accent/5 blur-3xl"></div>
            <div class="absolute -right-16 bottom-8 h-72 w-72 rounded-full bg-primary/5 blur-3xl"></div>
        </div>

        <div class="login-shell relative grid w-full overflow-hidden rounded-[1.75rem] border border-border bg-surface shadow-[0_28px_70px_-32px_rgba(16,35,58,0.38)] md:min-h-[34rem] md:grid-cols-2 lg:min-h-[38rem] lg:grid-cols-[1.12fr_0.88fr]">
            <section
                class="relative hidden overflow-hidden bg-primary px-8 py-10 text-white md:flex md:flex-col lg:px-12 lg:py-12"
                aria-label="Identidade institucional"
            >
                <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                    {{-- Foto institucional (direita do painel, como na referência) --}}
                    <div class="login-hero-photo absolute inset-y-0 right-0 w-[62%] lg:w-[58%]">
                        <img
                            src="{{ asset('images/telaLogin.png') }}"
                            alt=""
                            class="h-full w-full object-cover object-[62%_center]"
                            decoding="async"
                        >
                    </div>

                    {{-- Lavagens navy para legibilidade + integração com a foto --}}
                    <div class="absolute inset-0 bg-gradient-to-r from-primary from-0% via-primary/92 via-42% to-primary/35 to-100%"></div>
                    <div class="absolute inset-0 bg-gradient-to-t from-primary via-primary/55 to-transparent"></div>
                    <div class="absolute inset-y-0 right-0 w-1/3 bg-gradient-to-l from-primary/25 to-transparent"></div>

                    {{-- Ondas/curvas translúcidas sobre a foto --}}
                    <svg class="absolute inset-0 h-full w-full" viewBox="0 0 640 720" fill="none" preserveAspectRatio="xMidYMid slice">
                        <path d="M220 0C310 110 250 210 360 320C470 430 420 530 520 720H640V0H220Z" fill="url(#loginWaveFill)" opacity="0.35" />
                        <path d="M180 0C290 140 230 250 350 370C460 480 430 580 510 720" stroke="rgba(147,197,253,0.35)" stroke-width="1.25" />
                        <path d="M120 0C250 160 200 280 330 400C450 510 420 600 490 720" stroke="rgba(191,219,254,0.22)" stroke-width="1" />
                        <circle cx="540" cy="140" r="170" stroke="rgba(255,255,255,0.12)" stroke-width="1.1" />
                        <circle cx="580" cy="180" r="230" stroke="rgba(255,255,255,0.08)" stroke-width="1" />
                        <defs>
                            <linearGradient id="loginWaveFill" x1="220" y1="0" x2="640" y2="720" gradientUnits="userSpaceOnUse">
                                <stop stop-color="#1e3a5f" stop-opacity="0.55" />
                                <stop offset="0.55" stop-color="#2b4a73" stop-opacity="0.2" />
                                <stop offset="1" stop-color="#2563eb" stop-opacity="0.08" />
                            </linearGradient>
                        </defs>
                    </svg>
                </div>

                <div class="relative z-10 flex min-h-full flex-1 flex-col">
                    <div class="shrink-0">
                        @if ($loginBranding->hasConfiguredLogo)
                            <div
                                @class([
                                    'inline-flex max-w-full items-center leading-none',
                                    'rounded-xl px-3 py-2' => $logoSurfaceCss !== null,
                                ])
                                @if ($logoSurfaceCss !== null)
                                    style="background: {{ $logoSurfaceCss }};"
                                @endif
                            >
                                <img
                                    src="{{ $loginBranding->logoUrl }}"
                                    alt="{{ $loginBranding->productName }}"
                                    class="max-h-12 max-w-[13rem] bg-transparent object-contain"
                                >
                            </div>
                        @else
                            <div class="flex items-center gap-3">
                                <span class="flex size-11 items-center justify-center rounded-xl bg-accent text-sm font-bold tracking-tight text-white" aria-hidden="true">hC</span>
                                <div>
                                    <p class="text-base font-semibold tracking-tight">{{ $loginBranding->productName }}</p>
                                    <p class="text-xs text-white/70">Gestão de Atendimento</p>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="mt-auto max-w-md pt-14 lg:max-w-lg lg:pt-16">
                        <h1 class="text-[1.85rem] font-semibold leading-[1.2] tracking-tight lg:text-[2.2rem]">
                            Atendimento organizado.<br>
                            <span class="text-sky-300">Cuidado mais eficiente.</span>
                        </h1>
                        <p class="mt-5 max-w-md text-[0.95rem] leading-7 text-white/80">
                            Gerencie filas, atendimentos e unidades em um único ambiente.
                        </p>

                        <ul class="mt-8 grid gap-3 text-sm text-white/90 sm:grid-cols-3 sm:gap-3">
                            <li class="flex items-start gap-2.5">
                                <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full border border-white/20 bg-white/10 text-sky-200" aria-hidden="true">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11a4 4 0 1 0-8 0M4 20a7 7 0 0 1 16 0M18 8a3 3 0 1 0-2-5.2" /></svg>
                                </span>
                                <span class="leading-snug">Filas e atendimentos</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full border border-white/20 bg-white/10 text-sky-200" aria-hidden="true">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V8l7-4 7 4v13M9 21v-6h6v6" /></svg>
                                </span>
                                <span class="leading-snug">Unidades e guichês</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full border border-white/20 bg-white/10 text-sky-200" aria-hidden="true">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M4 19V9M10 19V5M16 19v-7M22 19H2" /></svg>
                                </span>
                                <span class="leading-snug">Gestão com eficiência</span>
                            </li>
                        </ul>
                    </div>

                    <div class="relative z-10 mt-10 border-t border-white/10 pt-5">
                        <p class="text-sm font-semibold tracking-tight">Micro Hard Center</p>
                        <p class="mt-0.5 text-xs font-normal text-white/65">Tecnologia &amp; Desenvolvimento</p>
                    </div>
                </div>
            </section>

            <section class="login-auth flex flex-col justify-center px-6 py-8 sm:px-10 sm:py-11 lg:px-12">
                <div class="mb-8 md:hidden">
                    @if ($loginBranding->hasConfiguredLogo)
                        <div
                            @class([
                                'inline-flex max-w-full items-center leading-none',
                                'rounded-xl border border-border px-3 py-2' => $logoSurfaceCss !== null,
                            ])
                            @if ($logoSurfaceCss !== null)
                                style="background: {{ $logoSurfaceCss }};"
                            @endif
                        >
                            <img
                                src="{{ $loginBranding->logoUrl }}"
                                alt="{{ $loginBranding->productName }}"
                                class="max-h-10 max-w-[11rem] bg-transparent object-contain"
                            >
                        </div>
                    @else
                        <div class="flex items-center gap-3">
                            <span class="flex size-10 items-center justify-center rounded-xl bg-primary text-sm font-bold text-white" aria-hidden="true">hC</span>
                            <div>
                                <p class="text-sm font-semibold text-primary">{{ $loginBranding->productName }}</p>
                                <p class="text-xs text-text-muted">Gestão de Atendimento</p>
                            </div>
                        </div>
                    @endif
                </div>

                <div>
                    <h2 class="text-2xl font-semibold tracking-tight text-text sm:text-[1.75rem]">Acesse sua conta</h2>
                    <p class="mt-2 text-sm leading-6 text-text-muted sm:text-[0.95rem]">
                        Entre com suas credenciais para acessar o sistema.
                    </p>
                </div>

                @if ($hasFormError)
                    <div class="mt-6" id="{{ $formErrorId }}">
                        <x-ui.alert type="danger">
                            {{ $errors->first('email') ?: $errors->first('password') }}
                        </x-ui.alert>
                    </div>
                @endif

                <form
                    id="login-form"
                    method="POST"
                    action="{{ route('login.store') }}"
                    class="mt-8 space-y-5"
                    data-login-form
                >
                    @csrf

                    <div>
                        <label for="email" class="mb-1.5 block text-sm font-medium text-text">E-mail</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex w-11 items-center justify-center text-text-muted" aria-hidden="true">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16v12H4z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4 7 8 6 8-6" />
                                </svg>
                            </span>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                value="{{ old('email') }}"
                                required
                                autofocus
                                autocomplete="username"
                                inputmode="email"
                                spellcheck="false"
                                placeholder="seu@email.com"
                                aria-invalid="{{ $hasEmailError ? 'true' : 'false' }}"
                                @if ($hasFormError) aria-describedby="{{ $formErrorId }}" @endif
                                class="{{ $inputClass }}"
                            >
                        </div>
                    </div>

                    <div>
                        <label for="password" class="mb-1.5 block text-sm font-medium text-text">Senha</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex w-11 items-center justify-center text-text-muted" aria-hidden="true">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                    <rect x="5" y="11" width="14" height="9" rx="2" />
                                    <path stroke-linecap="round" d="M8 11V8a4 4 0 0 1 8 0v3" />
                                </svg>
                            </span>
                            <input
                                id="password"
                                name="password"
                                type="password"
                                required
                                autocomplete="current-password"
                                aria-invalid="{{ $hasPasswordError ? 'true' : 'false' }}"
                                @if ($hasFormError) aria-describedby="{{ $formErrorId }}" @endif
                                class="{{ $inputClass }} !pr-12"
                            >
                            <button
                                type="button"
                                id="toggle-password"
                                class="absolute inset-y-0 right-0 flex min-w-11 cursor-pointer items-center justify-center rounded-r-xl px-3 text-text-muted transition duration-150 hover:text-text focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-accent"
                                aria-label="Mostrar senha"
                                aria-controls="password"
                                aria-pressed="false"
                                data-password-toggle
                            >
                                <svg data-icon-show class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12s-3.75 6.75-9.75 6.75S2.25 12 2.25 12Z" />
                                    <circle cx="12" cy="12" r="2.75" />
                                </svg>
                                <svg data-icon-hide class="hidden size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75l16.5 16.5M9.88 9.88A2.75 2.75 0 0 0 12 14.75c.5 0 .97-.13 1.38-.36M6.53 6.53C4.5 7.9 2.9 10.1 2.25 12c0 0 3.75 6.75 9.75 6.75 1.7 0 3.22-.36 4.53-.94M14.12 9.88A2.74 2.74 0 0 1 17 12c0 .4-.09.79-.24 1.13M17.47 17.47C19.5 16.1 21.1 13.9 21.75 12c0 0-1.2-2.16-3.28-3.9" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <x-ui.button
                        type="submit"
                        class="mt-2 min-h-12 w-full text-[0.95rem] shadow-sm shadow-accent/20"
                        data-login-submit
                        data-submitting="false"
                        aria-busy="false"
                    >
                        <span data-login-label class="login-submit-label inline-flex items-center gap-2">
                            Entrar
                            <span aria-hidden="true">→</span>
                        </span>
                        <span data-login-loading class="login-submit-loading items-center gap-2" aria-live="polite">
                            <svg class="size-4 shrink-0 animate-spin motion-reduce:animate-none" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                                <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v3a5 5 0 0 0-5 5H4z"></path>
                            </svg>
                            Entrando...
                        </span>
                    </x-ui.button>
                </form>

                <p class="mt-8 flex items-center justify-center gap-2 text-xs text-text-muted">
                    <svg class="size-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3l8 3v6c0 5-3.5 8.5-8 9-4.5-.5-8-4-8-9V6l8-3z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="m9 12 2 2 4-4" />
                    </svg>
                    Acesso seguro e protegido
                </p>
            </section>
        </div>
    </main>

    <script>
        (function () {
            var form = document.querySelector('[data-login-form]');
            var toggle = document.querySelector('[data-password-toggle]');
            var password = document.getElementById('password');
            var submit = document.querySelector('[data-login-submit]');
            var showIcon = toggle ? toggle.querySelector('[data-icon-show]') : null;
            var hideIcon = toggle ? toggle.querySelector('[data-icon-hide]') : null;
            var isSubmitting = false;

            function setSubmitting(next) {
                isSubmitting = next;

                if (!submit) {
                    return;
                }

                submit.setAttribute('data-submitting', next ? 'true' : 'false');
                submit.setAttribute('aria-busy', next ? 'true' : 'false');

                if (!next) {
                    submit.disabled = false;
                }
            }

            if (toggle && password) {
                toggle.addEventListener('click', function () {
                    var revealing = password.getAttribute('type') === 'password';
                    password.setAttribute('type', revealing ? 'text' : 'password');
                    toggle.setAttribute('aria-pressed', revealing ? 'true' : 'false');
                    toggle.setAttribute('aria-label', revealing ? 'Ocultar senha' : 'Mostrar senha');
                    if (showIcon && hideIcon) {
                        showIcon.classList.toggle('hidden', revealing);
                        hideIcon.classList.toggle('hidden', !revealing);
                    }
                });
            }

            if (form && submit) {
                form.addEventListener('submit', function (event) {
                    if (isSubmitting) {
                        event.preventDefault();
                        return;
                    }

                    setSubmitting(true);

                    // Desabilita após o submit iniciar, para não cancelar o POST do formulário.
                    window.setTimeout(function () {
                        if (isSubmitting && submit) {
                            submit.disabled = true;
                        }
                    }, 0);
                });
            }
        })();
    </script>
</body>
</html>
