<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Unit> */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        $name = fake()->unique()->city().' Unit';

        return [
            'clinic_id' => Clinic::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'active' => true,
        ];
    }
}
