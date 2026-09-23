<?php

namespace App\Actions;

use App\Models\Clinic;
use App\Models\TicketType;
use App\Models\Unit;
use App\Models\UnitTicketType;
use Illuminate\Support\Facades\DB;

/**
 * Ensures default ticket types are offered on a unit (Totem), without overwriting
 * an existing offer that was intentionally configured.
 */
class EnsureDefaultUnitTicketOffers
{
    /**
     * @param  list<string>|null  $prefixes  Defaults to TicketType::DEFAULT_TYPES prefixes.
     */
    public function handle(Clinic $clinic, Unit $unit, ?array $prefixes = null): void
    {
        abort_if($unit->clinic_id !== $clinic->id, 404);

        $prefixes ??= array_column(TicketType::DEFAULT_TYPES, 'prefix');

        $types = TicketType::query()
            ->where('clinic_id', $clinic->id)
            ->whereIn('prefix', $prefixes)
            ->orderByDesc('priority')
            ->get();

        DB::transaction(function () use ($clinic, $unit, $types): void {
            foreach ($types->values() as $index => $type) {
                $existing = UnitTicketType::query()
                    ->where('clinic_id', $clinic->id)
                    ->where('unit_id', $unit->id)
                    ->where('ticket_type_id', $type->id)
                    ->first();

                if ($existing !== null) {
                    continue;
                }

                $displayName = match ($type->prefix) {
                    'N' => 'Atendimento Normal',
                    'P' => 'Atendimento Preferencial',
                    'E' => 'Atendimento Emergencial',
                    default => null,
                };

                $offer = new UnitTicketType;
                $offer->forceFill([
                    'clinic_id' => $clinic->id,
                    'unit_id' => $unit->id,
                    'ticket_type_id' => $type->id,
                    'active' => true,
                    'display_name' => $displayName,
                    'position' => ($index + 1) * 10,
                ])->save();
            }
        });
    }
}
