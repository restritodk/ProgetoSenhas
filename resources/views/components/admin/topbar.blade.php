@props([
    'title' => null,
    'clinic' => null,
    'unit' => null,
])

<header class="sticky top-0 z-20 border-b border-border bg-surface/95 backdrop-blur">
    <div class="flex min-h-16 items-center justify-between gap-4 px-4 sm:px-6">
        <div class="flex min-w-0 items-center gap-3">
            <button
                type="button"
                class="inline-flex size-11 cursor-pointer items-center justify-center rounded-lg border border-border text-primary lg:hidden"
                @click="sidebarOpen = true"
                aria-controls="navegacao-principal"
                :aria-expanded="sidebarOpen.toString()"
            >
                <span class="sr-only">Abrir menu</span>
                <x-admin.icon name="menu" class="size-5" />
            </button>
            <div class="min-w-0">
                <h1 class="truncate text-lg font-semibold text-text">{{ $title ?? View::getSection('heading') ?? 'Painel' }}</h1>
                <p class="truncate text-xs text-text-muted">
                    {{ $clinic?->name ?? 'Clínica não definida' }}
                    @if ($unit)
                        <span aria-hidden="true"> · </span>
                        <span>{{ $unit->name }}</span>
                    @endif
                </p>
            </div>
        </div>

        <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false">
            <button
                type="button"
                class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border border-border bg-background px-3 py-1.5 text-left"
                @click="open = !open"
                :aria-expanded="open.toString()"
                aria-haspopup="menu"
            >
                <span class="flex size-8 items-center justify-center rounded-full bg-primary text-xs font-semibold text-white" aria-hidden="true">
                    {{ \Illuminate\Support\Str::substr(auth()->user()->name, 0, 1) }}
                </span>
                <span class="hidden sm:block">
                    <span class="block text-sm font-medium text-text">{{ auth()->user()->name }}</span>
                    <span class="block text-xs text-text-muted">{{ auth()->user()->role->label() }}</span>
                </span>
            </button>
            <div
                x-cloak
                x-show="open"
                x-transition
                @click.outside="open = false"
                class="absolute right-0 mt-2 w-56 rounded-xl border border-border bg-surface p-2 shadow-lg"
                role="menu"
            >
                <p class="px-3 py-2 text-xs text-text-muted">{{ auth()->user()->email }}</p>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex min-h-11 w-full cursor-pointer items-center rounded-lg px-3 text-sm text-text hover:bg-background" role="menuitem">
                        Sair
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
