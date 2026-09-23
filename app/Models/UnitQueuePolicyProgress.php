<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitQueuePolicyProgress extends Model
{
    protected $table = 'unit_queue_policy_progress';

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'source_calls_in_cycle' => 'integer',
            'target_calls_in_cycle' => 'integer',
            'cycle_version' => 'integer',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(UnitQueuePolicy::class, 'unit_queue_policy_id');
    }

    public function isDueForTarget(UnitQueuePolicy $policy): bool
    {
        if (! $policy->isDistributionConfigured()) {
            return false;
        }

        return (int) $this->source_calls_in_cycle >= (int) $policy->distribution_source_count;
    }
}
