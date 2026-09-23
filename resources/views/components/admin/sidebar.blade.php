@props([
    'sections' => [],
    'branding' => [],
])

@php
    $logoUrl = $branding['logo_url'] ?? null;
    $logoBackground = \App\Support\LogoSurface::normalizeMode($branding['logo_background'] ?? \App\Support\LogoSurface::NONE);
    $logoBackgroundColor = \App\Support\LogoSurface::normalizeColor($branding['logo_background_color'] ?? \App\Support\LogoSurface::DEFAULT_CUSTOM_COLOR);
    $logoSurfaceCss = \App\Support\LogoSurface::cssBackground($logoBackground, $logoBackgroundColor);
@endphp

<div class="flex h-full flex-col">
    <div class="border-b border-white/10 px-5 py-5">
        @if ($logoUrl)
            <div class="space-y-2">
                <div
                    @class([
                        'inline-flex max-w-full items-center leading-none',
                        'rounded-lg px-2.5 py-1.5' => $logoSurfaceCss !== null,
                    ])
                    @if ($logoSurfaceCss !== null)
                        style="background: {{ $logoSurfaceCss }};"
                    @endif
                >
                    <img
                        src="{{ $logoUrl }}"
                        alt=""
                        class="max-h-10 max-w-[12rem] bg-transparent object-contain"
                    >
                </div>
                <p class="text-xs text-white/70">Painel administrativo</p>
            </div>
        @else
            <div class="flex items-center gap-3">
                <span class="flex size-10 items-center justify-center rounded-xl bg-accent text-sm font-bold" aria-hidden="true">hC</span>
                <div>
                    <p class="text-base font-semibold tracking-tight">humanaClinica</p>
                    <p class="text-xs text-white/70">Painel administrativo</p>
                </div>
            </div>
        @endif
    </div>

    <nav class="flex-1 overflow-y-auto px-3 py-4" aria-label="Menu principal">
        @foreach ($sections as $section)
            <p class="mb-2 mt-4 px-3 text-[11px] font-semibold uppercase tracking-[0.16em] text-white/50 first:mt-0">{{ $section['title'] }}</p>
            <ul class="space-y-1">
                @foreach ($section['items'] as $item)
                    <li>
                        @if ($item['available'] && $item['route'])
                            <a
                                href="{{ route($item['route']) }}"
                                @class([
                                    'flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition duration-200',
                                    'bg-white/15 text-white ring-1 ring-accent/70' => request()->routeIs($item['route']),
                                    'text-white/80 hover:bg-white/10 hover:text-white' => ! request()->routeIs($item['route']),
                                ])
                                @if (request()->routeIs($item['route'])) aria-current="page" @endif
                            >
                                <x-admin.icon :name="$item['icon']" class="size-5 shrink-0" />
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @else
                            <span class="flex min-h-11 items-center justify-between gap-3 rounded-lg px-3 py-2 text-sm text-white/40" aria-disabled="true">
                                <span class="flex items-center gap-3">
                                    <x-admin.icon :name="$item['icon']" class="size-5 shrink-0" />
                                    <span>{{ $item['label'] }}</span>
                                </span>
                                <span class="rounded-full bg-white/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide">Em breve</span>
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endforeach
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
