<?php

namespace App\Actions;

use App\Models\Sector;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UpdateSector
{
    /**
     * @param  array{name?: string, code?: string, unit_id?: int, description?: ?string, active?: bool}  $attributes
     */
    public function handle(User $actor, Sector $sector, array $attributes): Sector
    {
        Gate::forUser($actor)->authorize('update', $sector);
        abort_unless($sector->clinic_id === $actor->clinic_id, 403);

        if (array_key_exists('unit_id', $attributes)) {
            $unit = Unit::query()
                ->where('clinic_id', $actor->clinic_id)
                ->whereKey($attributes['unit_id'])
                ->first();

            if ($unit === null) {
                throw ValidationException::withMessages([
                    'unitId' => 'A unidade selecionada não pertence à sua clínica.',
                ]);
            }

            $sector->unit_id = $unit->id;
        }

        if (array_key_exists('name', $attributes)) {
            $sector->name = $attributes['name'];
        }

        if (array_key_exists('code', $attributes)) {
            $code = Str::upper($attributes['code']);
            $exists = Sector::query()
                ->where('clinic_id', $actor->clinic_id)
                ->where('unit_id', $sector->unit_id)
                ->where('code', $code)
                ->whereKeyNot($sector->id)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'code' => 'Já existe um setor com este código nesta unidade.',
                ]);
            }

            $sector->code = $code;
        }

        if (array_key_exists('description', $attributes)) {
            $sector->description = $attributes['description'];
        }

        if (array_key_exists('active', $attributes)) {
            $sector->active = (bool) $attributes['active'];
        }

        $sector->save();

        return $sector->refresh()->load('unit');
    }
}
