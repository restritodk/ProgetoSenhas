<?php

use App\Support\PublicAccessCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('display_panels', function (Blueprint $table): void {
            $table->string('public_code', 16)->nullable()->after('public_token');
        });

        Schema::table('kiosks', function (Blueprint $table): void {
            $table->string('public_code', 16)->nullable()->after('public_token');
        });

        $this->backfill('display_panels', PublicAccessCode::PANEL_PREFIX);
        $this->backfill('kiosks', PublicAccessCode::KIOSK_PREFIX);

        Schema::table('display_panels', function (Blueprint $table): void {
            $table->unique('public_code');
        });

        Schema::table('kiosks', function (Blueprint $table): void {
            $table->unique('public_code');
        });

        $this->enforceNotNull('display_panels');
        $this->enforceNotNull('kiosks');
    }

    public function down(): void
    {
        Schema::table('display_panels', function (Blueprint $table): void {
            $table->dropUnique(['public_code']);
            $table->dropColumn('public_code');
        });

        Schema::table('kiosks', function (Blueprint $table): void {
            $table->dropUnique(['public_code']);
            $table->dropColumn('public_code');
        });
    }

    private function backfill(string $table, string $prefix): void
    {
        $ids = DB::table($table)
            ->whereNull('public_code')
            ->orderBy('id')
            ->pluck('id');

        foreach ($ids as $id) {
            $code = PublicAccessCode::generateUniqueForTable($prefix, $table);
            DB::table($table)->where('id', $id)->update(['public_code' => $code]);
        }
    }

    private function enforceNotNull(string $table): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE {$table} MODIFY public_code VARCHAR(16) NOT NULL");

            return;
        }

        // SQLite (tests): values already backfilled; unique index enforces uniqueness.
        // Native NOT NULL rewrite is unnecessary for application correctness here.
    }
};
