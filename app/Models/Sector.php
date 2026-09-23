<?php

namespace App\Models;

use Database\Factories\SectorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sector extends Model
{
    /** @use HasFactory<SectorFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
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

    public function desks(): HasMany
    {
        return $this->hasMany(Desk::class);
    }

    public function kiosks(): HasMany
    {
        return $this->hasMany(Kiosk::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function sectorTicketTypes(): HasMany
    {
        return $this->hasMany(SectorTicketType::class);
    }

    public function ticketTypes(): BelongsToMany
    {
        return $this->belongsToMany(TicketType::class, 'sector_ticket_types')
            ->withPivot(['id', 'clinic_id', 'active', 'display_name', 'position'])
            ->withTimestamps()
            ->orderByPivot('position');
    }

    public function displayPanels(): BelongsToMany
    {
        return $this->belongsToMany(DisplayPanel::class, 'display_panel_sector')
            ->withPivot(['id', 'clinic_id'])
            ->withTimestamps();
    }

    public function belongsToUnit(Unit $unit): bool
    {
        return (int) $this->clinic_id === (int) $unit->clinic_id
            && (int) $this->unit_id === (int) $unit->id;
    }
}
