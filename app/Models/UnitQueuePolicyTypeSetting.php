<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitQueuePolicyTypeSetting extends Model
{
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'rescue_wait_seconds' => 'integer',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(UnitQueuePolicy::class, 'unit_queue_policy_id');
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }
}
