<?php

namespace App\Actions;

use App\Models\Kiosk;
use App\Models\Sector;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UpdateKiosk
{
    /**
     * @param  array{name: string, code: string, unit_id: int, sector_id?: int|null, active: bool}  $attributes
     */
    public function handle(User $actor, Kiosk $kiosk, array $attributes): Kiosk
    {
        abort_if($kiosk->clinic_id !== $actor->clinic_id, 404);
        Gate::forUser($actor)->authorize('update', $kiosk);

        $unit = $this->unitForActorClinic($actor, $attributes['unit_id']);
        $sectorId = $attributes['sector_id'] ?? $kiosk->sector_id;
        if ($sectorId === null) {
            $sectorId = app(EnsureDefaultSectorForUnit::class)->handle($unit)->id;
        }
        $sector = $this->sectorForUnit($actor, $unit, (int) $sectorId);

        $kiosk->forceFill([
            'clinic_id' => $actor->clinic_id,
            'unit_id' => $unit->id,
            'sector_id' => $sector->id,
            'name' => $attributes['name'],
            'code' => Str::upper($attributes['code']),
            'active' => $attributes['active'],
        ])->save();

        return $kiosk->refresh();
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

    private function sectorForUnit(User $actor, Unit $unit, int $sectorId): Sector
    {
        $sector = Sector::query()
            ->where('clinic_id', $actor->clinic_id)
            ->where('unit_id', $unit->id)
            ->whereKey($sectorId)
            ->first();

        if ($sector === null) {
            throw ValidationException::withMessages([
                'sectorId' => 'O setor selecionado não pertence à unidade informada.',
            ]);
        }

        return $sector;
    }
}
