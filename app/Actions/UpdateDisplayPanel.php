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

class UpdateDisplayPanel
{
    /**
     * @param  array{name: string, code: string, unit_id: int, sector_ids?: list<int>, active: bool}  $attributes
     */
    public function handle(User $actor, DisplayPanel $panel, array $attributes): DisplayPanel
    {
        abort_if($panel->clinic_id !== $actor->clinic_id, 404);
        Gate::forUser($actor)->authorize('update', $panel);

        $unit = $this->unitForActorClinic($actor, $attributes['unit_id']);
        $hasSectorIds = array_key_exists('sector_ids', $attributes);
        $sectorIds = $hasSectorIds
            ? $this->validatedSectorIds($actor, $unit, $attributes['sector_ids'] ?? [])
            : null;

        return DB::transaction(function () use ($actor, $panel, $unit, $sectorIds, $hasSectorIds, $attributes): DisplayPanel {
            $panel->forceFill([
                'clinic_id' => $actor->clinic_id,
                'unit_id' => $unit->id,
                'name' => $attributes['name'],
                'code' => Str::upper($attributes['code']),
                'active' => $attributes['active'],
            ])->save();

            if ($hasSectorIds && $sectorIds !== null) {
                $this->syncSectors($panel, $unit, $sectorIds);
            }

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

        // UI/cadastro novo: 1 setor. Ativar/desativar preserva vínculos legados N:N na pivot.
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
