<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\DisplayPanel;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DisplayPanel> */
class DisplayPanelFactory extends Factory
{
    protected $model = DisplayPanel::class;

    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'unit_id' => null,
            'name' => 'TV '.fake()->unique()->words(2, true),
            'code' => 'TV'.fake()->unique()->numerify('##'),
            'public_token' => DisplayPanel::generatePublicToken(),
            'public_code' => DisplayPanel::generatePublicCode(),
            'active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (DisplayPanel $panel): void {
            if ($panel->clinic_id === null && $panel->unit_id !== null) {
                $panel->clinic_id = Unit::query()->whereKey($panel->unit_id)->value('clinic_id');
            }

            if ($panel->unit_id === null && $panel->clinic_id !== null) {
                $panel->unit_id = Unit::factory()->create(['clinic_id' => $panel->clinic_id])->id;
            }

            if ($panel->clinic_id === null && $panel->unit_id === null) {
                $clinic = Clinic::factory()->create();
                $panel->clinic_id = $clinic->id;
                $panel->unit_id = Unit::factory()->create(['clinic_id' => $clinic->id])->id;
            }
        });
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['active' => false]);
    }
}
