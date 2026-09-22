<?php

namespace App\Models;

use App\TicketCallType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketCall extends Model
{
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'call_type' => TicketCallType::class,
            'called_at' => 'datetime',
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

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function desk(): BelongsTo
    {
        return $this->belongsTo(Desk::class);
    }

    public function calledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'called_by_user_id');
    }
}
