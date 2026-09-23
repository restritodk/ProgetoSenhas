<?php

namespace App\Actions;

use App\Models\Sector;
use App\Models\Unit;

class EnsureDefaultSectorForUnit
{
    public const DEFAULT_CODE = 'PADRAO';

    public function handle(Unit $unit): Sector
    {
        $existing = Sector::query()
            ->where('clinic_id', $unit->clinic_id)
            ->where('unit_id', $unit->id)
            ->where('code', self::DEFAULT_CODE)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $sector = new Sector;
        $sector->forceFill([
            'clinic_id' => $unit->clinic_id,
            'unit_id' => $unit->id,
            'name' => 'Geral',
            'code' => self::DEFAULT_CODE,
            'description' => 'Setor padrão operacional da unidade.',
            'active' => true,
        ])->save();

        app(SyncSectorTicketTypesFromUnit::class)->handle($sector);

        return $sector->refresh();
    }

    public function resolveForUnit(Unit $unit, ?int $sectorId = null): Sector
    {
        if ($sectorId === null) {
            return $this->handle($unit);
        }

        $sector = Sector::query()
            ->where('clinic_id', $unit->clinic_id)
            ->where('unit_id', $unit->id)
            ->whereKey($sectorId)
            ->first();

        if ($sector === null) {
            return $this->handle($unit);
        }

        return $sector;
    }
}
