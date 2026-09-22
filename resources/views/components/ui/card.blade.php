@props([
    'title' => null,
    'description' => null,
])

<section {{ $attributes->merge(['class' => 'rounded-2xl border border-border bg-surface p-5 shadow-sm']) }}>
    @if ($title || $description)
        <header class="mb-4">
            @if ($title)
                <h2 class="text-base font-semibold text-text">{{ $title }}</h2>
            @endif
            @if ($description)
                <p class="mt-1 text-sm text-text-muted">{{ $description }}</p>
            @endif
        </header>
    @endif
    {{ $slot }}
</section>
