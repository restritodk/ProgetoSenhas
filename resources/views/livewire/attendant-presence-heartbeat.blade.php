<div
    wire:poll.{{ \App\Services\UserPresence::HEARTBEAT_SECONDS }}s="beat"
    class="hidden"
    aria-hidden="true"
></div>
