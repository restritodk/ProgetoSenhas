<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Atendimento') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body
    class="min-h-screen bg-background text-text antialiased"
    x-data="{ sidebarOpen: false }"
    @keydown.escape.window="sidebarOpen = false"
>
    <a href="#conteudo-atendimento" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-surface focus:px-4 focus:py-2 focus:shadow-lg">
        Ir para o conteúdo
    </a>

    <div class="min-h-screen lg:pl-72">
        <div
            x-cloak
            x-show="sidebarOpen"
            x-transition.opacity
            class="fixed inset-0 z-30 bg-primary-dark/50 lg:hidden"
            @click="sidebarOpen = false"
            aria-hidden="true"
        ></div>

        <aside
            id="navegacao-atendente"
            class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col bg-primary text-white shadow-xl transition-transform duration-200 ease-out lg:translate-x-0"
            :class="{ 'translate-x-0': sidebarOpen }"
            @keydown.escape.window="if (sidebarOpen) sidebarOpen = false"
            role="navigation"
            aria-label="Menu do atendente"
        >
            <div class="flex h-full flex-col">
                <div class="border-b border-white/10 px-5 py-5">
                    @if (! empty($attendantBranding['logo_url']))
                        <div
                            class="flex min-h-12 items-center justify-center rounded-xl px-3 py-2"
                            @if (($attendantBranding['logo_background'] ?? 'transparent') !== 'transparent')
                                style="background: {{ \App\Support\LogoSurface::cssBackground($attendantBranding['logo_background'], $attendantBranding['logo_background_color'] ?? '#FFFFFF') }};"
                            @endif
                        >
                            <img
                                src="{{ $attendantBranding['logo_url'] }}"
                                alt="{{ $currentClinic?->name ?? 'Logo da clínica' }}"
                                class="max-h-10 max-w-full object-contain"
                            >
                        </div>
                    @else
                        <div class="flex items-center gap-3">
                            <span class="flex size-10 items-center justify-center rounded-xl bg-accent text-sm font-bold" aria-hidden="true">hC</span>
                            <div>
                                <p class="text-base font-semibold tracking-tight">humanaClinica</p>
                                <p class="text-xs text-white/70">Painel do atendente</p>
                            </div>
                        </div>
                    @endif
                </div>

                <nav class="flex-1 overflow-y-auto px-3 py-4">
                    <p class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-[0.16em] text-white/50">Operação</p>
                    <ul class="space-y-1">
                        @foreach ($attendantNavItems ?? [] as $item)
                            @php
                                $active = request()->routeIs($item['route'])
                                    || ($item['route'] === 'attendant.messages' && request()->routeIs('attendant.messages*'));
                            @endphp
                            <li>
                                <a
                                    href="{{ route($item['route']) }}"
                                    @click="sidebarOpen = false"
                                    @class([
                                        'flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition duration-200',
                                        'bg-white/15 text-white ring-1 ring-accent/70' => $active,
                                        'text-white/80 hover:bg-white/10 hover:text-white' => ! $active,
                                    ])
                                    @if ($active) aria-current="page" @endif
                                >
                                    <x-admin.icon :name="$item['icon']" class="size-5 shrink-0" />
                                    <span class="flex-1">{{ $item['label'] }}</span>
                                    @if (($item['badge'] ?? null) !== null)
                                        <span class="inline-flex min-w-5 items-center justify-center rounded-full bg-danger px-1.5 py-0.5 text-[11px] font-bold text-white">
                                            {{ $item['badge'] > 99 ? '99+' : $item['badge'] }}
                                        </span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>

                    @if (! empty($showAdminShortcut))
                        <div class="mt-6 border-t border-white/10 pt-4">
                            <p class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-[0.16em] text-white/50">Administração</p>
                            <a
                                href="{{ route('dashboard') }}"
                                @click="sidebarOpen = false"
                                class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-white/80 transition duration-200 hover:bg-white/10 hover:text-white"
                            >
                                <x-admin.icon name="home" class="size-5 shrink-0" />
                                <span>Painel administrativo</span>
                            </a>
                        </div>
                    @endif
                </nav>

                <div class="border-t border-white/10 p-3">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex min-h-11 w-full cursor-pointer items-center gap-3 rounded-lg px-3 py-2 text-left text-sm font-medium text-white/80 transition duration-200 hover:bg-white/10 hover:text-white">
                            <x-admin.icon name="logout" class="size-5 shrink-0" />
                            Sair
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <div class="flex min-h-screen flex-col">
            <header class="sticky top-0 z-20 border-b border-border bg-primary text-white shadow-sm">
                <div class="flex min-h-16 items-center justify-between gap-4 px-4 sm:px-6">
                    <div class="flex min-w-0 items-center gap-3">
                        <button
                            type="button"
                            class="inline-flex size-11 cursor-pointer items-center justify-center rounded-lg border border-white/20 bg-white/5 text-white lg:hidden"
                            @click="sidebarOpen = true"
                            aria-controls="navegacao-atendente"
                            :aria-expanded="sidebarOpen.toString()"
                        >
                            <span class="sr-only">Abrir menu</span>
                            <x-admin.icon name="menu" class="size-5" />
                        </button>
                        <div class="min-w-0 lg:hidden">
                            <h1 class="truncate text-base font-semibold">@yield('heading', 'Atendimento')</h1>
                        </div>
                    </div>

                    <div class="flex items-center gap-4 sm:gap-5">
                        <div
                            class="hidden text-right sm:block"
                            x-data="{
                                now: new Date(),
                                init() {
                                    setInterval(() => { this.now = new Date() }, 1000)
                                },
                                dateLabel() {
                                    return this.now.toLocaleDateString('pt-BR', { day: 'numeric', month: 'long', year: 'numeric' })
                                },
                                timeLabel() {
                                    return this.now.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
                                }
                            }"
                        >
                            <p class="text-xs text-white/70" x-text="dateLabel()"></p>
                            <p class="text-lg font-semibold tabular-nums leading-tight" x-text="timeLabel()"></p>
                        </div>

                        <div class="flex items-center gap-3 border-l border-white/15 pl-4">
                            @php
                                $authUser = auth()->user();
                                $avatarUrl = $authUser?->avatarUrl();
                            @endphp
                            @if ($avatarUrl)
                                <img src="{{ $avatarUrl }}" alt="" class="size-10 rounded-full object-cover ring-2 ring-white/30">
                            @else
                                <span class="flex size-10 items-center justify-center rounded-full bg-accent text-sm font-bold text-white ring-2 ring-white/30" aria-hidden="true">
                                    {{ $authUser?->initials() ?? 'U' }}
                                </span>
                            @endif
                            <div class="min-w-0 text-left">
                                <p class="truncate text-sm font-semibold">{{ $authUser?->name }}</p>
                                <p class="truncate text-xs text-white/70">{{ $authUser?->role?->label() }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main id="conteudo-atendimento" class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                @hasSection('heading')
                    <div class="mb-5 hidden lg:block">
                        <h1 class="text-2xl font-semibold tracking-tight text-text">@yield('heading')</h1>
                        @hasSection('subheading')
                            <p class="mt-1 text-sm text-text-muted">@yield('subheading')</p>
                        @endif
                    </div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>

    @livewire(\App\Livewire\AttendantPresenceHeartbeat::class)

    @livewireScripts
</body>
</html>
