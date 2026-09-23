<?php

namespace App\Models;

use App\TicketSource;
use App\TicketStatus;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'sequence_number' => 'integer',
            'sequence_date' => 'date',
            'status' => TicketStatus::class,
            'source' => TicketSource::class,
            'issued_at' => 'datetime',
            'queued_at' => 'datetime',
            'called_at' => 'datetime',
            'service_started_at' => 'datetime',
            'completed_at' => 'datetime',
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

    public function currentDesk(): BelongsTo
    {
        return $this->belongsTo(Desk::class, 'current_desk_id');
    }

    public function targetDesk(): BelongsTo
    {
        return $this->belongsTo(Desk::class, 'target_desk_id');
    }

    public function calledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'called_by_user_id');
    }

    public function kiosk(): BelongsTo
    {
        return $this->belongsTo(Kiosk::class);
    }

    public function calls(): HasMany
    {
        return $this->hasMany(TicketCall::class);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(TicketTransfer::class);
    }

    /**
     * Visual code such as N001 — derived, never the primary identity.
     */
    protected function displayCode(): Attribute
    {
        return Attribute::get(function (): string {
            $prefix = $this->ticketType?->prefix ?? '';

            return $prefix.str_pad((string) $this->sequence_number, 3, '0', STR_PAD_LEFT);
        });
    }

    public function isActiveOnDesk(): bool
    {
        return in_array($this->status, [TicketStatus::CALLED, TicketStatus::IN_SERVICE], true);
    }
}
