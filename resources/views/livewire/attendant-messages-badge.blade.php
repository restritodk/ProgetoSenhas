<span
    wire:poll.{{ app(\App\Services\UserPresence::class)->unreadPollSeconds() }}s="sync"
    @class([
        'inline-flex min-w-5 items-center justify-center rounded-full bg-danger px-1.5 py-0.5 text-[11px] font-bold text-white',
        'hidden' => $count < 1,
    ])
    @if ($count > 0)
        aria-label="{{ $count }} {{ $count === 1 ? 'mensagem não lida' : 'mensagens não lidas' }}"
    @else
        aria-hidden="true"
    @endif
>
    {{ $count > 99 ? '99+' : $count }}
</span>
