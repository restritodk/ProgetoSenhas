<?php

use App\Actions\SyncPermissionCatalog;
use App\Models\Clinic;
use App\Models\ClinicRolePermission;
use App\Models\Permission;
use App\Services\ClinicPermissionResolver;
use App\Support\PermissionCatalog;
use App\UserRole;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Sector permissions were added to the catalog after clinics already had role
     * grants. EnsureClinicRolePermissions does not overwrite existing roles, so
     * Administrator never received sectors.* — hiding the sidebar item.
     */
    public function up(): void
    {
        app(SyncPermissionCatalog::class)->handle();

        $sectorKeys = [
            'sectors.view',
            'sectors.create',
            'sectors.update',
            'sectors.manage_status',
        ];

        $permissionIds = Permission::query()
            ->whereIn('key', $sectorKeys)
            ->pluck('id', 'key');

        if ($permissionIds->isEmpty()) {
            return;
        }

        $resolver = app(ClinicPermissionResolver::class);
        $clinicIds = Clinic::query()->pluck('id');

        foreach ($clinicIds as $clinicId) {
            foreach ($permissionIds as $permissionId) {
                $exists = ClinicRolePermission::query()
                    ->where('clinic_id', $clinicId)
                    ->where('role', UserRole::ADMINISTRATOR->value)
                    ->where('permission_id', $permissionId)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $grant = new ClinicRolePermission;
                $grant->forceFill([
                    'clinic_id' => $clinicId,
                    'role' => UserRole::ADMINISTRATOR,
                    'permission_id' => $permissionId,
                ])->save();
            }

            $resolver->forget((int) $clinicId, UserRole::ADMINISTRATOR);
        }

        // Also grant any other missing Administrator catalog defaults introduced later.
        $allPermissionIds = Permission::query()->pluck('id', 'key');
        foreach ($clinicIds as $clinicId) {
            foreach (PermissionCatalog::defaultsFor(UserRole::ADMINISTRATOR) as $key) {
                $permissionId = $allPermissionIds[$key] ?? null;
                if ($permissionId === null) {
                    continue;
                }

                $exists = ClinicRolePermission::query()
                    ->where('clinic_id', $clinicId)
                    ->where('role', UserRole::ADMINISTRATOR->value)
                    ->where('permission_id', $permissionId)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $grant = new ClinicRolePermission;
                $grant->forceFill([
                    'clinic_id' => $clinicId,
                    'role' => UserRole::ADMINISTRATOR,
                    'permission_id' => $permissionId,
                ])->save();
            }

            $resolver->forget((int) $clinicId, UserRole::ADMINISTRATOR);
        }
    }

    public function down(): void
    {
        $permissionIds = Permission::query()
            ->whereIn('key', [
                'sectors.view',
                'sectors.create',
                'sectors.update',
                'sectors.manage_status',
            ])
            ->pluck('id');

        if ($permissionIds->isEmpty()) {
            return;
        }

        ClinicRolePermission::query()
            ->where('role', UserRole::ADMINISTRATOR->value)
            ->whereIn('permission_id', $permissionIds)
            ->delete();
    }
};
