<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unique(['clinic_id', 'id'], 'users_clinic_id_id_unique');
        });

        Schema::table('units', function (Blueprint $table): void {
            $table->unique(['clinic_id', 'id'], 'units_clinic_id_id_unique');
        });

        if (! Schema::hasTable('unit_user')) {
            Schema::create('unit_user', function (Blueprint $table): void {
                $table->unsignedBigInteger('clinic_id');
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['user_id', 'unit_id']);
                $table->index(['unit_id', 'user_id']);
                $table->foreign(['clinic_id', 'user_id'])->references(['clinic_id', 'id'])->on('users')->cascadeOnDelete();
                $table->foreign(['clinic_id', 'unit_id'])->references(['clinic_id', 'id'])->on('units')->cascadeOnDelete();
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'active')) {
                $table->boolean('active')->default(true)->after('password');
            }
        });

        if (Schema::hasColumn('users', 'unit_id') && Schema::getConnection()->getDriverName() === 'mysql') {
            $foreignKey = DB::selectOne(
                'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1',
                ['users', 'unit_id'],
            );

            if ($foreignKey !== null) {
                Schema::table('users', function (Blueprint $table): void {
                    $table->dropForeign(['unit_id']);
                });
            }

            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('unit_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'unit_id')) {
                $table->foreignId('unit_id')->nullable()->after('clinic_id')->constrained()->nullOnDelete();
                $table->index(['clinic_id', 'unit_id']);
            }
        });

        Schema::dropIfExists('unit_user');
    }
};
