@props([
    'label',
    'name',
    'id' => null,
    'type' => 'text',
])

@php
    $id = $id ?? $name;
@endphp

<div>
    <label for="{{ $id }}" class="mb-1.5 block text-sm font-medium text-text">{{ $label }}</label>
    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="{{ $type }}"
        {{ $attributes->merge(['class' => 'min-h-12 w-full rounded-xl border border-border bg-surface px-3.5 py-2.5 text-sm text-text shadow-sm transition duration-200 placeholder:text-text-muted focus:border-accent focus:ring-2 focus:ring-accent/25']) }}
    >
    {{ $slot }}
</div>
