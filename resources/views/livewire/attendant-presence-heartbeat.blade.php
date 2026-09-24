<div
    wire:poll.{{ app(\App\Services\UserPresence::class)->heartbeatSeconds() }}s="beat"
    class="hidden"
    aria-hidden="true"
></div>
