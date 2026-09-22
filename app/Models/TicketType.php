<?php

namespace App\Models;

use Database\Factories\TicketTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketType extends Model
{
    /** @use HasFactory<TicketTypeFactory> */
    use HasFactory;

    /**
     * Priority semantics: higher number = higher priority.
     * Queue aging/anti-starvation is applied by NextTicketSelector at selection time.
     */
    public const DEFAULT_TYPES = [
        ['name' => 'Normal', 'prefix' => 'N', 'priority' => 10],
        ['name' => 'Preferencial', 'prefix' => 'P', 'priority' => 20],
        ['name' => 'Emergencial', 'prefix' => 'E', 'priority' => 30],
    ];

    protected $fillable = [
        'name',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'priority' => 'integer',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
