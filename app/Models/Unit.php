<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
}
