@props([
    'tone' => 'neutral',
])

@php
    $classes = match ($tone) {
        'success' => 'bg-green-50 text-success',
        'danger' => 'bg-red-50 text-danger',
        'warning' => 'bg-amber-50 text-warning',
        'accent' => 'bg-blue-50 text-accent',
        default => 'bg-background text-text-muted',
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold '.$classes]) }}>
    {{ $slot }}
</span>
