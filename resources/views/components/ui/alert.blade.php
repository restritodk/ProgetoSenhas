@props([
    'type' => 'info',
])

@php
    $classes = match ($type) {
        'success' => 'border-success/20 bg-green-50 text-success',
        'danger' => 'border-danger/20 bg-red-50 text-danger',
        'warning' => 'border-warning/20 bg-amber-50 text-warning',
        default => 'border-accent/20 bg-blue-50 text-primary',
    };
@endphp

<div role="status" {{ $attributes->merge(['class' => 'rounded-xl border px-4 py-3 text-sm '.$classes]) }}>
    {{ $slot }}
</div>
