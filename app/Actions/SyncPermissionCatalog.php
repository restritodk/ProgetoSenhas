<?php

namespace App\Actions;

use App\Models\Permission;
use App\Support\PermissionCatalog;

class SyncPermissionCatalog
{
    /**
     * Idempotently upsert the global permission catalog from PermissionCatalog.
     */
    public function handle(): void
    {
        foreach (PermissionCatalog::definitions() as $definition) {
            $permission = Permission::query()->where('key', $definition['key'])->first();

            if ($permission === null) {
                $permission = new Permission;
                $permission->forceFill(['key' => $definition['key']]);
            }

            $permission->forceFill([
                'module' => $definition['module'],
                'name' => $definition['name'],
                'description' => $definition['description'],
                'sort' => $definition['sort'],
            ])->save();
        }

        $keys = PermissionCatalog::keys();

        Permission::query()
            ->whereNotIn('key', $keys)
            ->delete();
    }
}
