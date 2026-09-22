<?php

namespace Database\Factories;

use App\Models\Clinic;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Clinic> */
class ClinicFactory extends Factory
{
    protected $model = Clinic::class;

    public function definition(): array
    {
        $name = fake()->unique()->company().' Clinic';

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'active' => true,
        ];
    }
}
