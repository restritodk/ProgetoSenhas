<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            if (! Schema::hasColumn('tickets', 'started_by_user_id')) {
                $table->foreignId('started_by_user_id')->nullable()->after('called_by_user_id');
            }
            if (! Schema::hasColumn('tickets', 'completed_by_user_id')) {
                $table->foreignId('completed_by_user_id')->nullable()->after('started_by_user_id');
            }
            if (! Schema::hasColumn('tickets', 'no_show_by_user_id')) {
                $table->foreignId('no_show_by_user_id')->nullable()->after('completed_by_user_id');
            }
        });

        Schema::table('tickets', function (Blueprint $table): void {
            $table->foreign(['clinic_id', 'started_by_user_id'])
                ->references(['clinic_id', 'id'])
                ->on('users')
                ->restrictOnDelete();
            $table->foreign(['clinic_id', 'completed_by_user_id'])
                ->references(['clinic_id', 'id'])
                ->on('users')
                ->restrictOnDelete();
            $table->foreign(['clinic_id', 'no_show_by_user_id'])
                ->references(['clinic_id', 'id'])
                ->on('users')
                ->restrictOnDelete();

            $table->index(
                ['clinic_id', 'completed_by_user_id', 'completed_at'],
                'tickets_completed_by_completed_at_index',
            );
            $table->index(
                ['clinic_id', 'no_show_by_user_id', 'completed_at'],
                'tickets_no_show_by_completed_at_index',
            );
            $table->index(
                ['clinic_id', 'started_by_user_id', 'service_started_at'],
                'tickets_started_by_started_at_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropForeign(['clinic_id', 'started_by_user_id']);
            $table->dropForeign(['clinic_id', 'completed_by_user_id']);
            $table->dropForeign(['clinic_id', 'no_show_by_user_id']);
            $table->dropIndex('tickets_completed_by_completed_at_index');
            $table->dropIndex('tickets_no_show_by_completed_at_index');
            $table->dropIndex('tickets_started_by_started_at_index');
            $table->dropColumn(['started_by_user_id', 'completed_by_user_id', 'no_show_by_user_id']);
        });
    }
};
