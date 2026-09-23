<?php

namespace App\Models;

use Database\Factories\DisplayPanelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class DisplayPanel extends Model
{
    /** @use HasFactory<DisplayPanelFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
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

    public function sectors(): BelongsToMany
    {
        return $this->belongsToMany(Sector::class, 'display_panel_sector')
            ->withPivot(['id', 'clinic_id'])
            ->withTimestamps();
    }

    public function mediaItems(): BelongsToMany
    {
        return $this->belongsToMany(MediaItem::class, 'display_panel_media')
            ->withPivot(['id', 'clinic_id', 'position', 'duration_seconds', 'active'])
            ->withTimestamps()
            ->orderByPivot('position');
    }

    /**
     * @return list<int>
     */
    public function sectorIds(): array
    {
        if ($this->relationLoaded('sectors')) {
            return $this->sectors->pluck('id')->map(fn ($id): int => (int) $id)->all();
        }

        return $this->sectors()->pluck('sectors.id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * Operational primary sector for this panel (UI rule: one sector per panel).
     * Legacy multi-sector rows still return the first linked sector.
     */
    public function primarySector(): ?Sector
    {
        if ($this->relationLoaded('sectors')) {
            return $this->sectors->first();
        }

        return $this->sectors()->orderBy('sectors.id')->first();
    }

    public static function generatePublicToken(): string
    {
        return Str::lower(Str::random(64));
    }

    public function publicUrl(): string
    {
        return route('tv.panel', ['publicToken' => $this->public_token]);
    }

    public function isOperationallyAvailable(): bool
    {
        return $this->active
            && $this->clinic?->active === true
            && $this->unit?->active === true;
    }
}
