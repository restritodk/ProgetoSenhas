<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitTicketType extends Model
{
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'position' => 'integer',
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

    public function publicLabel(): string
    {
        $custom = trim((string) $this->display_name);

        if ($custom !== '') {
            return $custom;
        }

        return (string) ($this->ticketType?->name ?? 'Atendimento');
    }
}
