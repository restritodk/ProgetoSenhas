<?php

namespace App\Models;

use App\TicketTransferType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketTransfer extends Model
{
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'transfer_type' => TicketTransferType::class,
            'transferred_at' => 'datetime',
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

    public function fromDesk(): BelongsTo
    {
        return $this->belongsTo(Desk::class, 'from_desk_id');
    }

    public function toDesk(): BelongsTo
    {
        return $this->belongsTo(Desk::class, 'to_desk_id');
    }

    public function transferredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by_user_id');
    }
}
