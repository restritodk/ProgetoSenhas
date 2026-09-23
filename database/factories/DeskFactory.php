<?php

namespace Database\Factories;

use App\Actions\EnsureDefaultSectorForUnit;
use App\Models\Clinic;
use App\Models\Desk;
use App\Models\Sector;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Desk> */
class DeskFactory extends Factory
{
    protected $model = Desk::class;

    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'unit_id' => null,
            'sector_id' => null,
            'name' => 'Mesa '.fake()->unique()->numerify('##'),
            'code' => 'M'.fake()->unique()->numerify('##'),
            'active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Desk $desk): void {
            if ($desk->clinic_id === null && $desk->unit_id !== null) {
                $desk->clinic_id = Unit::query()->whereKey($desk->unit_id)->value('clinic_id');
            }

            if ($desk->unit_id === null && $desk->clinic_id !== null) {
                $desk->unit_id = Unit::factory()->create(['clinic_id' => $desk->clinic_id])->id;
            }

            if ($desk->clinic_id === null && $desk->unit_id === null) {
                $clinic = Clinic::factory()->create();
                $desk->clinic_id = $clinic->id;
                $desk->unit_id = Unit::factory()->create(['clinic_id' => $clinic->id])->id;
            }

            if ($desk->sector_id === null && $desk->unit_id !== null) {
                $unit = Unit::query()->find($desk->unit_id);
                if ($unit !== null) {
                    $desk->sector_id = app(EnsureDefaultSectorForUnit::class)->handle($unit)->id;
                }
            }

            if ($desk->sector_id !== null && $desk->clinic_id !== null) {
                $sectorClinicId = Sector::query()->whereKey($desk->sector_id)->value('clinic_id');
                if ($sectorClinicId !== null) {
                    $desk->clinic_id = $sectorClinicId;
                }
            }
        });
    }
}
