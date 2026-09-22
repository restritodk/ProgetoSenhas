<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class)->withPivot('clinic_id')->withTimestamps();
    }

    public function hasAccessToUnit(Unit $unit): bool
    {
        return $unit->clinic_id === $this->clinic_id
            && $this->units()->whereKey($unit->getKey())->exists();
    }

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
