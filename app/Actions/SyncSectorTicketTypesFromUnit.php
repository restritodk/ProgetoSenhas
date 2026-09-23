<?php

namespace App\Actions;

use App\Models\Sector;
use App\Models\SectorTicketType;
use App\Models\TicketType;
use App\Models\UnitTicketType;
use Illuminate\Support\Facades\DB;

/**
 * Copies unit-level ticket offers into a sector when the sector has none yet.
 * Does not overwrite existing sector offers.
 */
class SyncSectorTicketTypesFromUnit
{
    public function handle(Sector $sector): void
    {
        if (SectorTicketType::query()->where('sector_id', $sector->id)->exists()) {
            return;
        }

        DB::transaction(function () use ($sector): void {
            $unitOffers = UnitTicketType::query()
                ->where('clinic_id', $sector->clinic_id)
                ->where('unit_id', $sector->unit_id)
                ->orderBy('position')
                ->orderBy('id')
                ->get();

            if ($unitOffers->isNotEmpty()) {
                foreach ($unitOffers as $offer) {
                    $row = new SectorTicketType;
                    $row->forceFill([
                        'clinic_id' => $sector->clinic_id,
                        'sector_id' => $sector->id,
                        'ticket_type_id' => $offer->ticket_type_id,
                        'active' => $offer->active,
                        'display_name' => $offer->display_name,
                        'position' => $offer->position,
                    ])->save();
                }

                return;
            }

            $types = TicketType::query()
                ->where('clinic_id', $sector->clinic_id)
                ->where('active', true)
                ->orderByDesc('priority')
                ->orderBy('name')
                ->orderBy('id')
                ->get();

            $position = 0;
            foreach ($types as $type) {
                $row = new SectorTicketType;
                $row->forceFill([
                    'clinic_id' => $sector->clinic_id,
                    'sector_id' => $sector->id,
                    'ticket_type_id' => $type->id,
                    'active' => true,
                    'display_name' => null,
                    'position' => $position++,
                ])->save();
            }
        });
    }
}
