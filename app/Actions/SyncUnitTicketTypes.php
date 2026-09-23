<?php

namespace App\Actions;

use App\Models\TicketType;
use App\Models\Unit;
use App\Models\UnitTicketType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SyncUnitTicketTypes
{
    /**
     * @param  list<array{ticket_type_id: int, active: bool, display_name: ?string, position: int}>  $rows
     */
    public function handle(User $actor, Unit $unit, array $rows): void
    {
        abort_if($unit->clinic_id !== $actor->clinic_id, 404);
        Gate::forUser($actor)->authorize('manageTicketTypes', $unit);

        $typeIds = collect($rows)->pluck('ticket_type_id')->map(fn ($id): int => (int) $id)->unique()->values();

        $validTypeIds = TicketType::query()
            ->where('clinic_id', $actor->clinic_id)
            ->whereIn('id', $typeIds)
            ->pluck('id')
            ->all();

        if (count($validTypeIds) !== $typeIds->count()) {
            throw ValidationException::withMessages([
                'rows' => 'Um ou mais tipos de senha não pertencem à sua clínica.',
            ]);
        }

        DB::transaction(function () use ($actor, $unit, $rows): void {
            foreach ($rows as $row) {
                $displayName = isset($row['display_name']) ? trim((string) $row['display_name']) : '';
                $displayName = $displayName === '' ? null : mb_substr($displayName, 0, 255);

                $record = UnitTicketType::query()
                    ->where('clinic_id', $actor->clinic_id)
                    ->where('unit_id', $unit->id)
                    ->where('ticket_type_id', $row['ticket_type_id'])
                    ->first();

                if ($record === null) {
                    $record = new UnitTicketType;
                    $record->forceFill([
                        'clinic_id' => $actor->clinic_id,
                        'unit_id' => $unit->id,
                        'ticket_type_id' => $row['ticket_type_id'],
                    ]);
                }

                $record->forceFill([
                    'active' => (bool) $row['active'],
                    'display_name' => $displayName,
                    'position' => max(0, (int) $row['position']),
                ])->save();
            }
        });
    }
}
