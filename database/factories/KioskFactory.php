<?php

namespace Database\Factories;

use App\Actions\EnsureDefaultSectorForUnit;
use App\Models\Clinic;
use App\Models\Kiosk;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Kiosk> */
class KioskFactory extends Factory
{
    protected $model = Kiosk::class;

    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'unit_id' => null,
            'sector_id' => null,
            'name' => 'Totem '.$this->faker->unique()->numerify('##'),
            'code' => 'K'.$this->faker->unique()->numerify('##'),
            'public_token' => Kiosk::generatePublicToken(),
            'public_code' => Kiosk::generatePublicCode(),
            'active' => true,
            'print_enabled' => false,
            'print_method' => 'browser',
            'print_agent_port' => 17321,
            'print_agent_listen_mode' => 'local',
            'print_paper_width' => '80',
            'print_auto_cut' => true,
            'print_logo' => false,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Kiosk $kiosk): void {
            if ($kiosk->clinic_id === null) {
                $kiosk->clinic_id = Clinic::factory()->create()->id;
            }

            if ($kiosk->unit_id === null) {
                $kiosk->unit_id = Unit::factory()->create(['clinic_id' => $kiosk->clinic_id])->id;
            }

            if ($kiosk->sector_id === null && $kiosk->unit_id !== null) {
                $unit = Unit::query()->find($kiosk->unit_id);
                if ($unit !== null) {
                    $kiosk->sector_id = app(EnsureDefaultSectorForUnit::class)->handle($unit)->id;
                }
            }
        });
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['active' => false]);
    }
}
