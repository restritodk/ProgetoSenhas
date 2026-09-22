<?php

namespace App\Actions;

use App\Models\Clinic;
use App\Models\TicketType;
use Illuminate\Support\Collection;

class EnsureDefaultTicketTypes
{
    /**
     * Idempotently ensures Normal/Preferencial/Emergencial exist for the clinic.
     * Does not overwrite existing types with the same prefix.
     *
     * @return Collection<int, TicketType>
     */
    public function handle(Clinic $clinic): Collection
    {
        $types = collect();

        foreach (TicketType::DEFAULT_TYPES as $defaults) {
            $existing = TicketType::query()
                ->where('clinic_id', $clinic->id)
                ->where('prefix', $defaults['prefix'])
                ->first();

            if ($existing !== null) {
                $types->push($existing);

                continue;
            }

            $ticketType = new TicketType;
            $ticketType->forceFill([
                'clinic_id' => $clinic->id,
                'prefix' => $defaults['prefix'],
                'name' => $defaults['name'],
                'priority' => $defaults['priority'],
                'active' => true,
            ])->save();

            $types->push($ticketType->refresh());
        }

        return $types;
    }
}
