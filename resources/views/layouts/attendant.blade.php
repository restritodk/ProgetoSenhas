<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Atendimento') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-background text-text antialiased" x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false">
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
        >
            <div class="flex h-full flex-col">
                <div class="flex items-center gap-3 border-b border-white/10 px-5 py-5">
                    <span class="flex size-10 items-center justify-center rounded-xl bg-accent text-sm font-bold" aria-hidden="true">hC</span>
                    <div>
                        <p class="text-base font-semibold tracking-tight">humanaClinica</p>
                        <p class="text-xs text-white/70">Painel do atendente</p>
                    </div>
                </div>

                <nav class="flex-1 overflow-y-auto px-3 py-4" aria-label="Menu do atendente">
                    <p class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-[0.16em] text-white/50">Operação</p>
                    <ul class="space-y-1">
                        <li>
                            <a href="{{ route('attendant.panel') }}" class="flex min-h-11 items-center gap-3 rounded-lg bg-white/15 px-3 py-2 text-sm font-medium ring-1 ring-accent/70" aria-current="page">
                                <x-admin.icon name="heart" class="size-5 shrink-0" />
                                <span>Atendimento</span>
                            </a>
                        </li>
                        @if (auth()->user()?->isAdministrator())
                            <li>
                                <a href="{{ route('dashboard') }}" class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-white/80 transition duration-200 hover:bg-white/10 hover:text-white">
                                    <x-admin.icon name="home" class="size-5 shrink-0" />
                                    <span>Painel administrativo</span>
                                </a>
                            </li>
                        @endif
                    </ul>
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
            <header class="sticky top-0 z-20 border-b border-border bg-surface/95 backdrop-blur">
                <div class="flex min-h-16 items-center justify-between gap-4 px-4 sm:px-6">
                    <div class="flex min-w-0 items-center gap-3">
                        <button
                            type="button"
                            class="inline-flex size-11 cursor-pointer items-center justify-center rounded-lg border border-border text-primary lg:hidden"
                            @click="sidebarOpen = true"
                            aria-controls="navegacao-atendente"
                            :aria-expanded="sidebarOpen.toString()"
                        >
                            <span class="sr-only">Abrir menu</span>
                            <x-admin.icon name="menu" class="size-5" />
                        </button>
                        <div class="min-w-0">
                            <h1 class="truncate text-lg font-semibold text-text">@yield('heading', 'Atendimento')</h1>
                            <p class="truncate text-xs text-text-muted">
                                {{ $currentClinic?->name ?? 'Clínica' }}
                                @if ($activeUnit)
                                    <span aria-hidden="true"> · </span>{{ $activeUnit->name }}
                                @endif
                                @if ($activeDesk ?? null)
                                    <span aria-hidden="true"> · </span>{{ $activeDesk->name }}
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-medium text-text" x-data x-text="new Date().toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'medium' })"></p>
                        <p class="text-xs text-text-muted">{{ auth()->user()->name }} · {{ auth()->user()->role->label() }}</p>
                    </div>
                </div>
            </header>

            <main id="conteudo-atendimento" class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                @yield('content')
            </main>
        </div>
    </div>

    @livewireScripts
</body>
</html>
