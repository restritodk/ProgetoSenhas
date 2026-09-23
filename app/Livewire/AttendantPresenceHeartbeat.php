<?php

namespace App\Livewire;

use App\Services\UserPresence;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class AttendantPresenceHeartbeat extends Component
{
    public function mount(UserPresence $presence): void
    {
        abort_unless(auth()->user()?->canAccessAttendantPanel(), 403);
        $this->beat($presence);
    }

    public function beat(UserPresence $presence): void
    {
        $user = auth()->user();

        if ($user === null || ! $user->canAccessAttendantPanel()) {
            return;
        }

        $presence->touch($user);
    }

    public function render(): View
    {
        return view('livewire.attendant-presence-heartbeat');
    }
}
