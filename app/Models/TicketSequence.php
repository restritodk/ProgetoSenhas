<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketSequence extends Model
{
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'sequence_date' => 'date',
            'last_number' => 'integer',
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

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }
}
