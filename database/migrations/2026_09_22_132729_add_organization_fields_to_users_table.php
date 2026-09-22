<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('clinic_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->after('clinic_id')->constrained()->nullOnDelete();
            $table->index(['clinic_id', 'unit_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['unit_id']);
            $table->dropForeign(['clinic_id']);
            $table->dropIndex(['clinic_id', 'unit_id']);
            $table->dropColumn(['clinic_id', 'unit_id']);
        });
    }
};
