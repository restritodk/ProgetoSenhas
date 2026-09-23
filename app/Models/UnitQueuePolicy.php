<?php

namespace App\Models;

use App\QueueCriticalMode;
use Database\Factories\UnitQueuePolicyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UnitQueuePolicy extends Model
{
    /** @use HasFactory<UnitQueuePolicyFactory> */
    use HasFactory;

    public const int DEFAULT_AGING_INTERVAL_SECONDS = 60;

    public const int DEFAULT_AGING_BONUS_PER_INTERVAL = 5;

    public const int DEFAULT_DISTRIBUTION_SOURCE_COUNT = 3;

    public const int DEFAULT_DISTRIBUTION_TARGET_COUNT = 1;

    public const int MIN_DISTRIBUTION_COUNT = 1;

    public const int MAX_DISTRIBUTION_COUNT = 50;

    public const int MIN_AGING_INTERVAL_SECONDS = 15;

    public const int MAX_AGING_INTERVAL_SECONDS = 3600;

    public const int MIN_AGING_BONUS = 1;

    public const int MAX_AGING_BONUS = 100;

    public const int MIN_RESCUE_WAIT_SECONDS = 60;

    public const int MAX_RESCUE_WAIT_SECONDS = 86400;

    /** Default rescue wait by relative priority band (seconds). */
    public const int DEFAULT_RESCUE_WAIT_LOW_PRIORITY = 900;

    public const int DEFAULT_RESCUE_WAIT_MID_PRIORITY = 1200;

    public const int DEFAULT_RESCUE_WAIT_HIGH_PRIORITY = 1800;

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'critical_mode' => QueueCriticalMode::class,
            'distribution_enabled' => 'boolean',
            'distribution_source_count' => 'integer',
            'distribution_target_count' => 'integer',
            'anti_starvation_enabled' => 'boolean',
            'aging_interval_seconds' => 'integer',
            'aging_bonus_per_interval' => 'integer',
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

    public function criticalTicketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class, 'critical_ticket_type_id');
    }

    public function distributionSourceTicketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class, 'distribution_source_ticket_type_id');
    }

    public function distributionTargetTicketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class, 'distribution_target_ticket_type_id');
    }

    public function progress(): HasOne
    {
        return $this->hasOne(UnitQueuePolicyProgress::class);
    }

    public function typeSettings(): HasMany
    {
        return $this->hasMany(UnitQueuePolicyTypeSetting::class);
    }

    public function isDistributionConfigured(): bool
    {
        return $this->distribution_enabled
            && $this->distribution_source_ticket_type_id !== null
            && $this->distribution_target_ticket_type_id !== null
            && (int) $this->distribution_source_ticket_type_id !== (int) $this->distribution_target_ticket_type_id
            && (int) $this->distribution_source_count >= self::MIN_DISTRIBUTION_COUNT
            && (int) $this->distribution_target_count >= self::MIN_DISTRIBUTION_COUNT;
    }

    public function usesAlwaysFirstCritical(): bool
    {
        return $this->critical_ticket_type_id !== null
            && $this->critical_mode === QueueCriticalMode::AlwaysFirst;
    }

    /**
     * @return array<int, int|null> ticket_type_id => rescue_wait_seconds
     */
    public function rescueWaitByTicketTypeId(): array
    {
        $map = [];

        foreach ($this->typeSettings as $setting) {
            $map[(int) $setting->ticket_type_id] = $setting->rescue_wait_seconds !== null
                ? (int) $setting->rescue_wait_seconds
                : null;
        }

        return $map;
    }
}
