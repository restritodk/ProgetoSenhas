<?php

namespace App\Actions;

use App\Models\DisplayPanel;
use App\Models\Sector;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateDisplayPanel
{
    /**
     * @param  array{name: string, code: string, unit_id: int, sector_ids: list<int>, active: bool}  $attributes
     */
    public function handle(User $actor, array $attributes): DisplayPanel
    {
        Gate::forUser($actor)->authorize('create', DisplayPanel::class);
        abort_if($actor->clinic_id === null, 404);

        $unit = $this->unitForActorClinic($actor, $attributes['unit_id']);
        $sectorIds = $attributes['sector_ids'] ?? [];
        if ($sectorIds === []) {
            $sectorIds = [app(EnsureDefaultSectorForUnit::class)->handle($unit)->id];
        }
        $sectorIds = $this->validatedSectorIds($actor, $unit, $sectorIds);

        return DB::transaction(function () use ($actor, $unit, $sectorIds, $attributes): DisplayPanel {
            $panel = new DisplayPanel;
            $panel->forceFill([
                'clinic_id' => $actor->clinic_id,
                'unit_id' => $unit->id,
                'name' => $attributes['name'],
                'code' => Str::upper($attributes['code']),
                'public_token' => DisplayPanel::generatePublicToken(),
                'public_code' => DisplayPanel::generatePublicCode(),
                'active' => $attributes['active'],
            ])->save();

            $this->syncSectors($panel, $unit, $sectorIds);

            return $panel->refresh()->load('sectors');
        });
    }

    /**
     * @param  list<int>  $sectorIds
     * @return list<int>
     */
    private function validatedSectorIds(User $actor, Unit $unit, array $sectorIds): array
    {
        $sectorIds = array_values(array_unique(array_map(intval(...), $sectorIds)));

        if ($sectorIds === []) {
            throw ValidationException::withMessages([
                'sectorId' => 'Selecione o setor do painel.',
            ]);
        }

        if (count($sectorIds) !== 1) {
            throw ValidationException::withMessages([
                'sectorId' => 'Cada painel/TV deve atender exatamente um setor nesta fase.',
            ]);
        }

        $validCount = Sector::query()
            ->where('clinic_id', $actor->clinic_id)
            ->where('unit_id', $unit->id)
            ->whereIn('id', $sectorIds)
            ->count();

        if ($validCount !== count($sectorIds)) {
            throw ValidationException::withMessages([
                'sectorId' => 'O setor selecionado não pertence à unidade informada.',
            ]);
        }

        return $sectorIds;
    }

    /**
     * @param  list<int>  $sectorIds
     */
    private function syncSectors(DisplayPanel $panel, Unit $unit, array $sectorIds): void
    {
        $sync = [];

        foreach ($sectorIds as $sectorId) {
            $sync[$sectorId] = [
                'clinic_id' => $panel->clinic_id,
            ];
        }

        $panel->sectors()->sync($sync);
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
