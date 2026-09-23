<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('kiosks', 'kiosks_clinic_id_id_unique')) {
            Schema::table('kiosks', function (Blueprint $table): void {
                $table->unique(['clinic_id', 'id'], 'kiosks_clinic_id_id_unique');
            });
        }

        Schema::table('tickets', function (Blueprint $table): void {
            if (! Schema::hasColumn('tickets', 'source')) {
                $table->string('source', 32)->default('admin')->after('status');
            }
            if (! Schema::hasColumn('tickets', 'kiosk_id')) {
                $table->foreignId('kiosk_id')->nullable()->after('called_by_user_id');
            }
        });

        DB::table('tickets')->whereNull('source')->orWhere('source', '')->update(['source' => 'admin']);

        Schema::table('tickets', function (Blueprint $table): void {
            if (! Schema::hasIndex('tickets', 'tickets_source_index')) {
                $table->index(['clinic_id', 'source', 'issued_at'], 'tickets_source_index');
            }

            $table->foreign(['clinic_id', 'kiosk_id'])
                ->references(['clinic_id', 'id'])
                ->on('kiosks')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropForeign(['clinic_id', 'kiosk_id']);
            if (Schema::hasIndex('tickets', 'tickets_source_index')) {
                $table->dropIndex('tickets_source_index');
            }
            $table->dropColumn(['source', 'kiosk_id']);
        });
    }
};
