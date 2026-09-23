<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('desks', function (Blueprint $table): void {
            if (! Schema::hasColumn('desks', 'sector_id')) {
                $table->foreignId('sector_id')->nullable()->after('unit_id')->constrained()->nullOnDelete();
                $table->index(['clinic_id', 'sector_id', 'active'], 'desks_clinic_sector_active_index');
            }
        });

        Schema::table('kiosks', function (Blueprint $table): void {
            if (! Schema::hasColumn('kiosks', 'sector_id')) {
                $table->foreignId('sector_id')->nullable()->after('unit_id')->constrained()->nullOnDelete();
                $table->index(['clinic_id', 'sector_id', 'active'], 'kiosks_clinic_sector_active_index');
            }
        });

        Schema::table('tickets', function (Blueprint $table): void {
            if (! Schema::hasColumn('tickets', 'sector_id')) {
                $table->foreignId('sector_id')->nullable()->after('unit_id')->constrained()->nullOnDelete();
                $table->index(['clinic_id', 'unit_id', 'sector_id', 'status'], 'tickets_sector_queue_index');
            }
        });

        Schema::table('ticket_calls', function (Blueprint $table): void {
            if (! Schema::hasColumn('ticket_calls', 'sector_id')) {
                $table->foreignId('sector_id')->nullable()->after('unit_id')->constrained()->nullOnDelete();
                $table->index(['clinic_id', 'unit_id', 'sector_id', 'called_at'], 'ticket_calls_sector_history_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ticket_calls', function (Blueprint $table): void {
            if (Schema::hasColumn('ticket_calls', 'sector_id')) {
                $table->dropIndex('ticket_calls_sector_history_index');
                $table->dropConstrainedForeignId('sector_id');
            }
        });

        Schema::table('tickets', function (Blueprint $table): void {
            if (Schema::hasColumn('tickets', 'sector_id')) {
                $table->dropIndex('tickets_sector_queue_index');
                $table->dropConstrainedForeignId('sector_id');
            }
        });

        Schema::table('kiosks', function (Blueprint $table): void {
            if (Schema::hasColumn('kiosks', 'sector_id')) {
                $table->dropIndex('kiosks_clinic_sector_active_index');
                $table->dropConstrainedForeignId('sector_id');
            }
        });

        Schema::table('desks', function (Blueprint $table): void {
            if (Schema::hasColumn('desks', 'sector_id')) {
                $table->dropIndex('desks_clinic_sector_active_index');
                $table->dropConstrainedForeignId('sector_id');
            }
        });
    }
};
