@props([
    'messages' => [],
])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'mt-2 space-y-1 text-sm text-danger']) }} role="alert">
        @foreach (\Illuminate\Support\Arr::flatten((array) $messages) as $message)
            @if (is_string($message) && $message !== '')
                <li>{{ $message }}</li>
            @endif
        @endforeach
    </ul>
@endif
