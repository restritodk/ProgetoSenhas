<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeskAssignment extends Model
{
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'claimed_at' => 'datetime',
            'last_seen_at' => 'datetime',
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

    public function desk(): BelongsTo
    {
        return $this->belongsTo(Desk::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
