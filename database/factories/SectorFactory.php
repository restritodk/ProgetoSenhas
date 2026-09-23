<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Sector;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Sector> */
class SectorFactory extends Factory
{
    protected $model = Sector::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'clinic_id' => Clinic::factory(),
            'unit_id' => Unit::factory(),
            'name' => Str::title($name),
            'code' => Str::upper(Str::substr(Str::slug($name, ''), 0, 6)).fake()->numerify('##'),
            'description' => null,
            'active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Sector $sector): void {
            if ($sector->unit_id === null) {
                return;
            }

            $unit = Unit::query()->find($sector->unit_id);
            if ($unit !== null) {
                $sector->clinic_id = $unit->clinic_id;
            }
        });
    }
}
