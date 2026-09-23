<?php

namespace App\Actions;

use App\Models\Desk;
use App\Models\DisplayPanel;
use App\Models\Kiosk;
use App\Models\Sector;
use App\Models\Ticket;
use App\Models\TicketCall;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeleteSector
{
    public function handle(User $actor, Sector $sector): void
    {
        Gate::forUser($actor)->authorize('delete', $sector);
        abort_unless($sector->clinic_id === $actor->clinic_id, 403);

        if ($this->hasOperationalHistory($sector)) {
            throw ValidationException::withMessages([
                'sector' => 'Este setor possui histórico operacional. Desative-o em vez de excluir.',
            ]);
        }

        DB::transaction(function () use ($sector): void {
            $sector->ticketTypes()->detach();
            $sector->displayPanels()->detach();
            $sector->delete();
        });
    }

    private function hasOperationalHistory(Sector $sector): bool
    {
        if (Desk::query()->where('sector_id', $sector->id)->exists()) {
            return true;
        }

        if (Kiosk::query()->where('sector_id', $sector->id)->exists()) {
            return true;
        }

        if (Ticket::query()->where('sector_id', $sector->id)->exists()) {
            return true;
        }

        if (TicketCall::query()->where('sector_id', $sector->id)->exists()) {
            return true;
        }

        if (DisplayPanel::query()
            ->whereHas('sectors', fn ($query) => $query->where('sectors.id', $sector->id))
            ->exists()) {
            return true;
        }

        return false;
    }
}
