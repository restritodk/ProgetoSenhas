<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Permission extends Model
{
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'sort' => 'integer',
        ];
    }

    public function clinicRolePermissions(): HasMany
    {
        return $this->hasMany(ClinicRolePermission::class);
    }

    public function clinics(): BelongsToMany
    {
        return $this->belongsToMany(Clinic::class, 'clinic_role_permissions')
            ->withPivot(['role'])
            ->withTimestamps();
    }
}
