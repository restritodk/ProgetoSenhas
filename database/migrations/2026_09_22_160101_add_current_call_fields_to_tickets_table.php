<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('desks', 'desks_clinic_id_id_unique')) {
            Schema::table('desks', function (Blueprint $table): void {
                $table->unique(['clinic_id', 'id'], 'desks_clinic_id_id_unique');
            });
        }

        if (! Schema::hasIndex('users', 'users_clinic_id_id_unique')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->unique(['clinic_id', 'id'], 'users_clinic_id_id_unique');
            });
        }

        Schema::table('tickets', function (Blueprint $table): void {
            if (! Schema::hasColumn('tickets', 'current_desk_id')) {
                $table->foreignId('current_desk_id')->nullable()->after('ticket_type_id');
            }
            if (! Schema::hasColumn('tickets', 'called_by_user_id')) {
                $table->foreignId('called_by_user_id')->nullable()->after('current_desk_id');
            }
        });

        Schema::table('tickets', function (Blueprint $table): void {
            if (! Schema::hasIndex('tickets', 'tickets_current_desk_status_index')) {
                $table->index(['clinic_id', 'current_desk_id', 'status'], 'tickets_current_desk_status_index');
            }

            // Composite tenant FK: ON DELETE SET NULL is invalid because clinic_id is NOT NULL.
            $table->foreign(['clinic_id', 'current_desk_id'])
                ->references(['clinic_id', 'id'])
                ->on('desks')
                ->restrictOnDelete();
            $table->foreign(['clinic_id', 'called_by_user_id'])
                ->references(['clinic_id', 'id'])
                ->on('users')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropForeign(['clinic_id', 'current_desk_id']);
            $table->dropForeign(['clinic_id', 'called_by_user_id']);
            $table->dropIndex('tickets_current_desk_status_index');
            $table->dropColumn(['current_desk_id', 'called_by_user_id']);
        });
    }
};
