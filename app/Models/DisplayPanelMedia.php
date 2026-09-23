<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisplayPanelMedia extends Model
{
    protected $table = 'display_panel_media';

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'duration_seconds' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function displayPanel(): BelongsTo
    {
        return $this->belongsTo(DisplayPanel::class);
    }

    public function mediaItem(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class);
    }
}
