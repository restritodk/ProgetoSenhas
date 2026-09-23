<?php

namespace App\Actions;

use App\Models\Clinic;
use App\Models\ClinicRolePermission;
use App\Models\Permission;
use App\Services\ClinicPermissionResolver;
use App\Support\PermissionCatalog;
use App\UserRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EnsureClinicRolePermissions
{
    public function __construct(
        private ClinicPermissionResolver $resolver,
    ) {}

    /**
     * Ensure each system role has default grants for the clinic.
     * Does not overwrite roles that already have at least one permission row,
     * except Administrator which receives missing catalog defaults additively
     * so new modules (e.g. Setores) appear without wiping customizations.
     */
    public function handle(Clinic $clinic, bool $force = false): void
    {
        app(SyncPermissionCatalog::class)->handle();

        $permissionsByKey = Permission::query()->pluck('id', 'key');

        DB::transaction(function () use ($clinic, $force, $permissionsByKey): void {
            foreach (UserRole::cases() as $role) {
                $exists = ClinicRolePermission::query()
                    ->where('clinic_id', $clinic->id)
                    ->where('role', $role->value)
                    ->exists();

                if ($exists && ! $force) {
                    if ($role === UserRole::ADMINISTRATOR) {
                        $this->grantMissingDefaults($clinic, $role, $permissionsByKey);
                    }

                    continue;
                }

                if ($force) {
                    ClinicRolePermission::query()
                        ->where('clinic_id', $clinic->id)
                        ->where('role', $role->value)
                        ->delete();
                }

                foreach (PermissionCatalog::defaultsFor($role) as $key) {
                    $permissionId = $permissionsByKey[$key] ?? null;
                    if ($permissionId === null) {
                        continue;
                    }

                    $row = new ClinicRolePermission;
                    $row->forceFill([
                        'clinic_id' => $clinic->id,
                        'role' => $role,
                        'permission_id' => $permissionId,
                    ])->save();
                }

                $this->resolver->forget($clinic->id, $role);
            }
        });
    }

    /**
     * @param  Collection<string, int|string>  $permissionsByKey
     */
    private function grantMissingDefaults(Clinic $clinic, UserRole $role, $permissionsByKey): void
    {
        $grantedIds = ClinicRolePermission::query()
            ->where('clinic_id', $clinic->id)
            ->where('role', $role->value)
            ->pluck('permission_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $added = false;

        foreach (PermissionCatalog::defaultsFor($role) as $key) {
            $permissionId = isset($permissionsByKey[$key]) ? (int) $permissionsByKey[$key] : null;
            if ($permissionId === null || in_array($permissionId, $grantedIds, true)) {
                continue;
            }

            $row = new ClinicRolePermission;
            $row->forceFill([
                'clinic_id' => $clinic->id,
                'role' => $role,
                'permission_id' => $permissionId,
            ])->save();

            $grantedIds[] = $permissionId;
            $added = true;
        }

        if ($added) {
            $this->resolver->forget($clinic->id, $role);
        }
    }
}
