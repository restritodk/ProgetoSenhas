<?php

namespace App\Models;

use App\UserRole;
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

    /**
     * Operational access: Attendant/Supervisor need unit_user; Administrator may operate any unit of own clinic.
     */
    public function canOperateUnit(Unit $unit): bool
    {
        if (! $this->active || $unit->clinic_id !== $this->clinic_id || ! $unit->active) {
            return false;
        }

        if ($this->isAdministrator()) {
            return true;
        }

        return $this->hasAccessToUnit($unit);
    }

    public function canAccessAttendantPanel(): bool
    {
        return $this->active
            && $this->clinic_id !== null
            && ($this->isAdministrator() || $this->isSupervisor() || $this->isAttendant());
    }

    public function isAdministrator(): bool
    {
        return $this->role === UserRole::ADMINISTRATOR;
    }

    public function isSupervisor(): bool
    {
        return $this->role === UserRole::SUPERVISOR;
    }

    public function isAttendant(): bool
    {
        return $this->role === UserRole::ATTENDANT;
    }

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }
}
