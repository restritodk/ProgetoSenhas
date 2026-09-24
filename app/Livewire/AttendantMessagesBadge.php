<?php

namespace App\Livewire;

use App\Services\ClinicMessageInbox;
use App\Services\OperationalChatEligibility;
use App\Services\UserPresence;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class AttendantMessagesBadge extends Component
{
    public int $count = 0;

    public function mount(ClinicMessageInbox $inbox, UserPresence $presence, OperationalChatEligibility $eligibility): void
    {
        abort_unless($eligibility->canUseOperationalChat(auth()->user()), 403);
        $this->sync($inbox, $presence);
    }

    public function sync(ClinicMessageInbox $inbox, UserPresence $presence): void
    {
        $user = auth()->user();

        if ($user === null || ! app(OperationalChatEligibility::class)->canUseOperationalChat($user)) {
            $this->count = 0;

            return;
        }

        $presence->touch($user);
        $this->count = $inbox->unreadCountFor($user);
    }

    public function render(): View
    {
        return view('livewire.attendant-messages-badge');
    }
}
