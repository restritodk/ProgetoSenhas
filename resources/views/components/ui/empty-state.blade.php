@props([
    'title',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-dashed border-border bg-background px-6 py-10 text-center']) }}>
    <p class="text-base font-semibold text-text">{{ $title }}</p>
    @if ($description)
        <p class="mt-2 text-sm text-text-muted">{{ $description }}</p>
    @endif
    <div class="mt-4">{{ $slot }}</div>
</div>
