<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Painel') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-background text-text antialiased" x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false">
    <a href="#conteudo-principal" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-surface focus:px-4 focus:py-2 focus:shadow-lg">
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
            id="navegacao-principal"
            class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col bg-primary text-white shadow-xl transition-transform duration-200 ease-out lg:translate-x-0"
            :class="{ 'translate-x-0': sidebarOpen }"
            @keydown.escape.window="sidebarOpen = false"
        >
            <x-admin.sidebar :sections="$navigationSections" :branding="$adminBranding ?? []" />
        </aside>

        <div class="flex min-h-screen flex-col">
            <x-admin.topbar
                :title="$__env->yieldContent('heading') ?: 'Painel'"
                :clinic="$currentClinic"
                :unit="$activeUnit"
            />

            <main id="conteudo-principal" class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                @if (session('status'))
                    <x-ui.alert type="success" class="mb-6">{{ session('status') }}</x-ui.alert>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    @livewireScripts
</body>
</html>
