<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_items', function (Blueprint $table): void {
            $table->string('external_id', 32)->nullable()->after('external_url');
        });

        // Legacy unused EXTERNAL type → explicit YOUTUBE semantics.
        DB::table('media_items')
            ->where('type', 'external')
            ->update(['type' => 'youtube']);
    }

    public function down(): void
    {
        DB::table('media_items')
            ->where('type', 'youtube')
            ->update(['type' => 'external']);

        Schema::table('media_items', function (Blueprint $table): void {
            $table->dropColumn('external_id');
        });
    }
};
