<?php

use App\Actions\EnsureClinicRolePermissions;
use App\Actions\SyncPermissionCatalog;
use App\Models\Clinic;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_role_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('role', 32);
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['clinic_id', 'role', 'permission_id'], 'clinic_role_permissions_unique');
            $table->index(['clinic_id', 'role'], 'clinic_role_permissions_lookup_index');
        });

        // Seed catalog + compatible defaults for every existing clinic (idempotent).
        app(SyncPermissionCatalog::class)->handle();

        Clinic::query()->orderBy('id')->each(function (Clinic $clinic): void {
            app(EnsureClinicRolePermissions::class)->handle($clinic);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_role_permissions');
    }
};
