<?php

namespace App\Actions;

use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class UpdateTicketType
{
    /**
     * @param  array{name: string, prefix: string, priority: int, active: bool}  $attributes
     */
    public function handle(User $actor, TicketType $ticketType, array $attributes): TicketType
    {
        abort_if($ticketType->clinic_id !== $actor->clinic_id, 404);
        Gate::forUser($actor)->authorize('update', $ticketType);

        $ticketType->forceFill([
            'name' => $attributes['name'],
            'prefix' => Str::upper($attributes['prefix']),
            'priority' => $attributes['priority'],
            'active' => $attributes['active'],
        ])->save();

        return $ticketType->refresh();
    }
}
