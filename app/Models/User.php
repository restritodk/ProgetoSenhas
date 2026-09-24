<?php

namespace App\Models;

use App\Services\ClinicPermissionResolver;
use App\UserRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'email', 'password', 'avatar_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const AVATAR_DISK = 'public';

    public const AVATAR_DIRECTORY = 'user-avatars';

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class)->withPivot('clinic_id')->withTimestamps();
    }

    public function deskAssignment(): HasOne
    {
        return $this->hasOne(DeskAssignment::class);
    }

    public function avatarUrl(): ?string
    {
        if ($this->avatar_path === null || $this->avatar_path === '') {
            return null;
        }

        return Storage::disk(self::AVATAR_DISK)->url($this->avatar_path);
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $first = mb_substr($parts[0] ?? 'U', 0, 1);
        $last = mb_substr($parts[count($parts) - 1] ?? '', 0, 1);
        if (count($parts) < 2) {
            return mb_strtoupper($first);
        }

        return mb_strtoupper($first.$last);
    }

    public function firstName(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];

        return $parts[0] ?? $this->name;
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
            && $this->hasPermission('attendant.access');
    }

    public function hasPermission(string $permissionKey): bool
    {
        if (! $this->active || $this->clinic_id === null || $this->role === null) {
            return false;
        }

        return app(ClinicPermissionResolver::class)
            ->roleHas((int) $this->clinic_id, $this->role, $permissionKey);
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
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }
}
