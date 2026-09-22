<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('users', 'users_clinic_id_id_unique')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->unique(['clinic_id', 'id'], 'users_clinic_id_id_unique');
            });
        }

        if (! Schema::hasIndex('units', 'units_clinic_id_id_unique')) {
            Schema::table('units', function (Blueprint $table): void {
                $table->unique(['clinic_id', 'id'], 'units_clinic_id_id_unique');
            });
        }

        if (! Schema::hasTable('unit_user') || Schema::hasColumn('unit_user', 'clinic_id')) {
            return;
        }

        Schema::table('unit_user', function (Blueprint $table): void {
            $table->unsignedBigInteger('clinic_id')->nullable()->after('user_id');
        });

        DB::table('unit_user')
            ->join('users', 'users.id', '=', 'unit_user.user_id')
            ->update(['unit_user.clinic_id' => DB::raw('users.clinic_id')]);

        Schema::table('unit_user', function (Blueprint $table): void {
            $table->unsignedBigInteger('clinic_id')->nullable(false)->change();
            $table->foreign(['clinic_id', 'user_id'])->references(['clinic_id', 'id'])->on('users')->cascadeOnDelete();
            $table->foreign(['clinic_id', 'unit_id'])->references(['clinic_id', 'id'])->on('units')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Catch-up only: a fresh install already has these constraints from earlier migrations.
    }
};
