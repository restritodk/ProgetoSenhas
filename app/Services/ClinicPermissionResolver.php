<?php

namespace App\Services;

use App\Actions\EnsureClinicRolePermissions;
use App\Models\Clinic;
use App\Models\ClinicRolePermission;
use App\Models\Permission;
use App\UserRole;
use Illuminate\Support\Facades\Cache;

class ClinicPermissionResolver
{
    private const int CACHE_SECONDS = 3600;

    /**
     * @return list<string>
     */
    public function keysForRole(int $clinicId, UserRole $role): array
    {
        $cacheKey = $this->cacheKey($clinicId, $role);

        /** @var list<string> $keys */
        $keys = Cache::remember($cacheKey, self::CACHE_SECONDS, function () use ($clinicId, $role): array {
            $hasRows = ClinicRolePermission::query()
                ->where('clinic_id', $clinicId)
                ->where('role', $role->value)
                ->exists();

            if (! $hasRows) {
                $clinic = Clinic::query()->find($clinicId);
                if ($clinic !== null) {
                    app(EnsureClinicRolePermissions::class)->handle($clinic);
                }
            }

            return ClinicRolePermission::query()
                ->where('clinic_role_permissions.clinic_id', $clinicId)
                ->where('clinic_role_permissions.role', $role->value)
                ->join('permissions', 'permissions.id', '=', 'clinic_role_permissions.permission_id')
                ->orderBy('permissions.sort')
                ->pluck('permissions.key')
                ->all();
        });

        return $keys;
    }

    public function roleHas(int $clinicId, UserRole $role, string $permissionKey): bool
    {
        return in_array($permissionKey, $this->keysForRole($clinicId, $role), true);
    }

    public function forget(int $clinicId, ?UserRole $role = null): void
    {
        if ($role !== null) {
            Cache::forget($this->cacheKey($clinicId, $role));

            return;
        }

        foreach (UserRole::cases() as $case) {
            Cache::forget($this->cacheKey($clinicId, $case));
        }
    }

    /**
     * @return array<string, Permission>
     */
    public function permissionModelsByKey(): array
    {
        return Permission::query()
            ->orderBy('sort')
            ->get()
            ->keyBy('key')
            ->all();
    }

    private function cacheKey(int $clinicId, UserRole $role): string
    {
        return 'clinic_role_permissions:'.$clinicId.':'.$role->value;
    }
}
