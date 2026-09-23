<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = ['clinic_id', 'name', 'slug', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('clinic_id')->withTimestamps();
    }

    public function desks(): HasMany
    {
        return $this->hasMany(Desk::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function kiosks(): HasMany
    {
        return $this->hasMany(Kiosk::class);
    }

    public function unitTicketTypes(): HasMany
    {
        return $this->hasMany(UnitTicketType::class);
    }

    public function ticketTypes(): BelongsToMany
    {
        return $this->belongsToMany(TicketType::class, 'unit_ticket_types')
            ->withPivot(['id', 'clinic_id', 'active', 'display_name', 'position'])
            ->withTimestamps()
            ->orderByPivot('position');
    }
}
