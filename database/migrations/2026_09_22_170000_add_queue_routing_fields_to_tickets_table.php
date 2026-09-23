<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            if (! Schema::hasColumn('tickets', 'queued_at')) {
                $table->timestamp('queued_at')->nullable()->after('issued_at');
            }

            if (! Schema::hasColumn('tickets', 'target_desk_id')) {
                $table->foreignId('target_desk_id')->nullable()->after('current_desk_id');
            }
        });

        DB::table('tickets')
            ->whereNull('queued_at')
            ->update(['queued_at' => DB::raw('issued_at')]);

        /*
         * Keep queued_at nullable at the schema level for MariaDB TIMESTAMP default quirks
         * and SQLite RefreshDatabase compatibility. Application code always sets queued_at
         * on issue and transfer; existing rows are backfilled above.
         */

        Schema::table('tickets', function (Blueprint $table): void {
            if (! Schema::hasIndex('tickets', 'tickets_queue_routing_index')) {
                $table->index(
                    ['clinic_id', 'unit_id', 'status', 'target_desk_id', 'queued_at'],
                    'tickets_queue_routing_index',
                );
            }

            if (! Schema::hasIndex('tickets', 'tickets_target_desk_status_index')) {
                $table->index(
                    ['clinic_id', 'target_desk_id', 'status'],
                    'tickets_target_desk_status_index',
                );
            }
        });

        $sm = Schema::getConnection()->getSchemaBuilder();
        $foreignKeys = collect($sm->getForeignKeys('tickets'))->pluck('name');

        if (! $foreignKeys->contains('tickets_clinic_id_target_desk_id_foreign')) {
            Schema::table('tickets', function (Blueprint $table): void {
                $table->foreign(['clinic_id', 'target_desk_id'])
                    ->references(['clinic_id', 'id'])
                    ->on('desks')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $sm = Schema::getConnection()->getSchemaBuilder();
            $foreignKeys = collect($sm->getForeignKeys('tickets'))->pluck('name');

            if ($foreignKeys->contains('tickets_clinic_id_target_desk_id_foreign')) {
                $table->dropForeign(['clinic_id', 'target_desk_id']);
            }

            if (Schema::hasIndex('tickets', 'tickets_queue_routing_index')) {
                $table->dropIndex('tickets_queue_routing_index');
            }

            if (Schema::hasIndex('tickets', 'tickets_target_desk_status_index')) {
                $table->dropIndex('tickets_target_desk_status_index');
            }

            $columns = [];
            if (Schema::hasColumn('tickets', 'queued_at')) {
                $columns[] = 'queued_at';
            }
            if (Schema::hasColumn('tickets', 'target_desk_id')) {
                $columns[] = 'target_desk_id';
            }
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
