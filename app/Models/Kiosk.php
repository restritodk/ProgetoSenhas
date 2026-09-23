<?php

namespace App\Models;

use Database\Factories\KioskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Kiosk extends Model
{
    /** @use HasFactory<KioskFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'active',
    ];

    protected $hidden = [
        'print_agent_secret_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'print_enabled' => 'boolean',
            'print_auto_cut' => 'boolean',
            'print_logo' => 'boolean',
            'print_agent_port' => 'integer',
            'print_paired_at' => 'datetime',
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

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public static function generatePublicToken(): string
    {
        return Str::lower(Str::random(64));
    }

    public function publicUrl(): string
    {
        return route('kiosk.panel', ['publicToken' => $this->public_token]);
    }

    public function isOperationallyAvailable(): bool
    {
        return $this->active
            && $this->clinic?->active === true
            && $this->unit?->active === true;
    }

    public function isPrintReady(): bool
    {
        if (! $this->print_enabled
            || ! filled($this->print_agent_secret_encrypted)
            || ! filled($this->print_printer_name)) {
            return false;
        }

        $mode = strtolower((string) ($this->print_agent_listen_mode ?? 'local'));
        if ($mode === 'lan' && ! filled($this->print_agent_host)) {
            return false;
        }

        return true;
    }
}
