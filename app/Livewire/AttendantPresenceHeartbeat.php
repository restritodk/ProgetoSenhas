<?php

namespace App\Livewire;

use App\Services\UserPresence;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class AttendantPresenceHeartbeat extends Component
{
    public function mount(UserPresence $presence): void
    {
        $this->beat($presence);
    }

    public function beat(UserPresence $presence): void
    {
        $user = auth()->user();

        if ($user === null || ! $user->active || $user->clinic_id === null) {
            return;
        }

        // Panel-wide: keeps desk lease (attendants) / heartbeat (supervisors) alive
        // on Dashboard, Mensagens, Histórico, Perfil — not only on /atendimento.
        $presence->touch($user);
    }

    public function render(): View
    {
        return view('livewire.attendant-presence-heartbeat');
    }
}
