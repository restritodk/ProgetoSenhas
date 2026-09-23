<?php

namespace App\Actions;

use App\Models\Clinic;
use App\Models\ClinicRolePermission;
use App\Models\Permission;
use App\Models\User;
use App\Services\ClinicPermissionResolver;
use App\Support\PermissionCatalog;
use App\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveClinicRolePermissions
{
    public function __construct(
        private ClinicPermissionResolver $resolver,
    ) {}

    /**
     * @param  list<string>  $permissionKeys
     * @return list<string>
     */
    public function handle(User $actor, Clinic $clinic, UserRole $role, array $permissionKeys): array
    {
        abort_if($clinic->id !== $actor->clinic_id, 404);
        Gate::forUser($actor)->authorize('roles.update');

        $validKeys = PermissionCatalog::keys();
        $permissionKeys = array_values(array_unique(array_filter(
            $permissionKeys,
            fn (mixed $key): bool => is_string($key) && in_array($key, $validKeys, true),
        )));

        $permissionKeys = PermissionCatalog::withDependencies($permissionKeys);

        if ($role === UserRole::ADMINISTRATOR) {
            foreach (PermissionCatalog::protectedAdministratorKeys() as $protected) {
                if (! in_array($protected, $permissionKeys, true)) {
                    throw ValidationException::withMessages([
                        'permissions' => 'Esta alteração deixaria a clínica sem acesso administrativo. A permissão "'.(PermissionCatalog::keyed()[$protected]['name'] ?? $protected).'" é obrigatória para administradores.',
                    ]);
                }
            }
        }

        $permissionIds = Permission::query()
            ->whereIn('key', $permissionKeys)
            ->pluck('id', 'key');

        return DB::transaction(function () use ($clinic, $role, $permissionKeys, $permissionIds): array {
            ClinicRolePermission::query()
                ->where('clinic_id', $clinic->id)
                ->where('role', $role->value)
                ->delete();

            foreach ($permissionKeys as $key) {
                $permissionId = $permissionIds[$key] ?? null;
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

            return $this->resolver->keysForRole($clinic->id, $role);
        });
    }
}
