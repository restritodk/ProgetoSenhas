<?php

namespace App\Actions;

use App\Models\Desk;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateDesk
{
    /**
     * @param  array{name: string, code: string, unit_id: int, active: bool}  $attributes
     */
    public function handle(User $actor, array $attributes): Desk
    {
        Gate::forUser($actor)->authorize('create', Desk::class);
        abort_if($actor->clinic_id === null, 404);

        $unit = $this->unitForActorClinic($actor, $attributes['unit_id']);

        $desk = new Desk;
        $desk->forceFill([
            'clinic_id' => $actor->clinic_id,
            'unit_id' => $unit->id,
            'name' => $attributes['name'],
            'code' => Str::upper($attributes['code']),
            'active' => $attributes['active'],
        ])->save();

        return $desk->refresh();
    }

    private function unitForActorClinic(User $actor, int $unitId): Unit
    {
        $unit = Unit::query()
            ->where('clinic_id', $actor->clinic_id)
            ->whereKey($unitId)
            ->first();

        if ($unit === null) {
            throw ValidationException::withMessages([
                'unitId' => 'A unidade selecionada não pertence à sua clínica.',
            ]);
        }

        return $unit;
    }
}
