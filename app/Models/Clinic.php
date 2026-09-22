<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Clinic extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
