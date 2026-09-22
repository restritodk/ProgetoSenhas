<?php

namespace App\Actions;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class SyncUserUnits
{
    /**
     * @param  list<int>  $unitIds
     */
    public function handle(User $user, array $unitIds, int $clinicId): void
    {
        $uniqueIds = array_values(array_unique(array_map(intval(...), $unitIds)));

        $validIds = Unit::query()
            ->where('clinic_id', $clinicId)
            ->whereIn('id', $uniqueIds)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if (count($validIds) !== count($uniqueIds)) {
            throw ValidationException::withMessages([
                'unitIds' => 'Uma ou mais unidades não pertencem à sua clínica.',
            ]);
        }

        $payload = [];
        foreach ($validIds as $unitId) {
            $payload[$unitId] = ['clinic_id' => $clinicId];
        }

        $user->units()->sync($payload);
    }
}
