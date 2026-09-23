<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Unit;
use App\Models\UnitQueuePolicy;
use App\QueueCriticalMode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitQueuePolicy>
 */
class UnitQueuePolicyFactory extends Factory
{
    protected $model = UnitQueuePolicy::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'unit_id' => Unit::factory(),
            'critical_ticket_type_id' => null,
            'critical_mode' => QueueCriticalMode::AlwaysFirst,
            'distribution_enabled' => true,
            'distribution_source_ticket_type_id' => null,
            'distribution_source_count' => UnitQueuePolicy::DEFAULT_DISTRIBUTION_SOURCE_COUNT,
            'distribution_target_ticket_type_id' => null,
            'distribution_target_count' => UnitQueuePolicy::DEFAULT_DISTRIBUTION_TARGET_COUNT,
            'anti_starvation_enabled' => true,
            'aging_interval_seconds' => UnitQueuePolicy::DEFAULT_AGING_INTERVAL_SECONDS,
            'aging_bonus_per_interval' => UnitQueuePolicy::DEFAULT_AGING_BONUS_PER_INTERVAL,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (UnitQueuePolicy $policy): void {
            if ($policy->clinic_id && $policy->unit_id === null) {
                $unit = Unit::factory()->create(['clinic_id' => $policy->clinic_id]);
                $policy->unit_id = $unit->id;
            }

            if ($policy->unit_id && $policy->clinic_id === null) {
                $unit = Unit::query()->find($policy->unit_id);
                $policy->clinic_id = $unit?->clinic_id;
            }
        });
    }
}
