@props([
    'title',
    'open' => false,
    'maxWidth' => 'md',
    'closeMethod' => null,
])

@php
    $maxWidthClass = match ($maxWidth) {
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
        '2xl' => 'max-w-2xl',
        '3xl' => 'max-w-3xl',
        default => 'max-w-md',
    };
@endphp

@if ($open)
    <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-primary-dark/50 p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="{{ $attributes->get('id', 'modal-title') }}"
        @if ($closeMethod)
            x-data
            x-on:keydown.escape.window="$wire.{{ $closeMethod }}()"
            x-on:click.self="$wire.{{ $closeMethod }}()"
        @endif
    >
        <div class="flex max-h-[min(90vh,52rem)] w-full {{ $maxWidthClass }} flex-col overflow-hidden rounded-2xl border border-border bg-surface shadow-xl">
            <div class="shrink-0 border-b border-border px-6 py-4">
                <h2 id="{{ $attributes->get('id', 'modal-title') }}" class="text-lg font-semibold text-text">{{ $title }}</h2>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto px-6 py-4 text-sm text-text-muted">
                {{ $slot }}
            </div>
            @isset($actions)
                <div class="flex shrink-0 flex-wrap justify-end gap-3 border-t border-border px-6 py-4">{{ $actions }}</div>
            @endisset
        </div>
    </div>
@endif
