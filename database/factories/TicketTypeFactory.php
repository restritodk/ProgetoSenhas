<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TicketType> */
class TicketTypeFactory extends Factory
{
    protected $model = TicketType::class;

    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'name' => fake()->unique()->words(2, true),
            'prefix' => strtoupper(fake()->unique()->lexify('??')),
            'priority' => fake()->numberBetween(1, 100),
            'active' => true,
        ];
    }
}
