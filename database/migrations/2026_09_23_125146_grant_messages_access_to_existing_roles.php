<?php

use App\Actions\SyncPermissionCatalog;
use App\Models\ClinicRolePermission;
use App\Models\Permission;
use App\Services\ClinicPermissionResolver;
use App\UserRole;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(SyncPermissionCatalog::class)->handle();

        $messagesId = Permission::query()->where('key', 'messages.access')->value('id');
        $attendantId = Permission::query()->where('key', 'attendant.access')->value('id');

        if ($messagesId === null) {
            return;
        }

        $clinicRoles = ClinicRolePermission::query()
            ->select('clinic_id', 'role')
            ->distinct()
            ->get();

        $resolver = app(ClinicPermissionResolver::class);

        foreach ($clinicRoles as $row) {
            $already = ClinicRolePermission::query()
                ->where('clinic_id', $row->clinic_id)
                ->where('role', $row->role)
                ->where('permission_id', $messagesId)
                ->exists();

            if ($already) {
                continue;
            }

            $hasAttendant = $attendantId !== null && ClinicRolePermission::query()
                ->where('clinic_id', $row->clinic_id)
                ->where('role', $row->role)
                ->where('permission_id', $attendantId)
                ->exists();

            $isAdmin = $row->role === UserRole::ADMINISTRATOR->value
                || $row->role === UserRole::ADMINISTRATOR;

            if (! $hasAttendant && ! $isAdmin) {
                continue;
            }

            $grant = new ClinicRolePermission;
            $grant->forceFill([
                'clinic_id' => $row->clinic_id,
                'role' => $row->role,
                'permission_id' => $messagesId,
            ])->save();

            $role = $row->role instanceof UserRole
                ? $row->role
                : UserRole::from((string) $row->role);
            $resolver->forget((int) $row->clinic_id, $role);
        }
    }

    public function down(): void
    {
        $messagesId = Permission::query()->where('key', 'messages.access')->value('id');
        if ($messagesId === null) {
            return;
        }

        ClinicRolePermission::query()
            ->where('permission_id', $messagesId)
            ->delete();
    }
};
