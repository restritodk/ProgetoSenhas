<?php

namespace App\Actions;

use App\Models\Sector;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateSector
{
    /**
     * @param  array{name: string, code: string, unit_id: int, description?: ?string, active: bool}  $attributes
     */
    public function handle(User $actor, array $attributes): Sector
    {
        Gate::forUser($actor)->authorize('create', Sector::class);
        abort_if($actor->clinic_id === null, 404);

        $unit = Unit::query()
            ->where('clinic_id', $actor->clinic_id)
            ->whereKey($attributes['unit_id'])
            ->first();

        if ($unit === null) {
            throw ValidationException::withMessages([
                'unitId' => 'A unidade selecionada não pertence à sua clínica.',
            ]);
        }

        $code = Str::upper($attributes['code']);

        $exists = Sector::query()
            ->where('clinic_id', $actor->clinic_id)
            ->where('unit_id', $unit->id)
            ->where('code', $code)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'code' => 'Já existe um setor com este código nesta unidade.',
            ]);
        }

        $sector = new Sector;
        $sector->forceFill([
            'clinic_id' => $actor->clinic_id,
            'unit_id' => $unit->id,
            'name' => $attributes['name'],
            'code' => $code,
            'description' => $attributes['description'] ?? null,
            'active' => $attributes['active'],
        ])->save();

        app(SyncSectorTicketTypesFromUnit::class)->handle($sector);

        return $sector->refresh()->load('unit');
    }
}
