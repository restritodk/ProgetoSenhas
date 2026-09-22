@props([
    'title',
    'open' => false,
])

@if ($open)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-primary-dark/50 p-4" role="dialog" aria-modal="true" aria-labelledby="{{ $attributes->get('id', 'modal-title') }}">
        <div class="w-full max-w-md rounded-2xl border border-border bg-surface p-6 shadow-xl">
            <h2 id="{{ $attributes->get('id', 'modal-title') }}" class="text-lg font-semibold text-text">{{ $title }}</h2>
            <div class="mt-3 text-sm text-text-muted">{{ $slot }}</div>
            @isset($actions)
                <div class="mt-6 flex justify-end gap-3">{{ $actions }}</div>
            @endisset
        </div>
    </div>
@endif
