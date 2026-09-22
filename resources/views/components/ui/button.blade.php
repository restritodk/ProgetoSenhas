@props([
    'variant' => 'primary',
    'type' => 'button',
])

@php
    $classes = match ($variant) {
        'secondary' => 'border border-border bg-surface text-text hover:bg-background',
        'danger' => 'bg-danger text-white hover:bg-red-700',
        'ghost' => 'text-text hover:bg-background',
        default => 'bg-accent text-white hover:bg-blue-700',
    };
@endphp

<button
    type="{{ $type }}"
    {{ $attributes->merge(['class' => 'inline-flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition duration-200 disabled:cursor-not-allowed disabled:opacity-60 '.$classes]) }}
>
    {{ $slot }}
</button>
