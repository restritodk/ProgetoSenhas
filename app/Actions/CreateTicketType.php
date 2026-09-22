<?php

namespace App\Actions;

use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CreateTicketType
{
    /**
     * @param  array{name: string, prefix: string, priority: int, active: bool}  $attributes
     */
    public function handle(User $actor, array $attributes): TicketType
    {
        Gate::forUser($actor)->authorize('create', TicketType::class);
        abort_if($actor->clinic_id === null, 404);

        $ticketType = new TicketType;
        $ticketType->forceFill([
            'clinic_id' => $actor->clinic_id,
            'name' => $attributes['name'],
            'prefix' => Str::upper($attributes['prefix']),
            'priority' => $attributes['priority'],
            'active' => $attributes['active'],
        ])->save();

        return $ticketType->refresh();
    }
}
