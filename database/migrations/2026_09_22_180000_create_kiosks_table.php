<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('units', 'units_clinic_id_id_unique')) {
            Schema::table('units', function (Blueprint $table): void {
                $table->unique(['clinic_id', 'id'], 'units_clinic_id_id_unique');
            });
        }

        Schema::create('kiosks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id');
            $table->string('name');
            $table->string('code', 64);
            $table->string('public_token', 64);
            $table->boolean('active')->default(true)->index();
            $table->timestamps();

            $table->unique('public_token');
            $table->unique(['clinic_id', 'code']);
            $table->index(['clinic_id', 'unit_id', 'active'], 'kiosks_scope_index');

            $table->foreign(['clinic_id', 'unit_id'])
                ->references(['clinic_id', 'id'])
                ->on('units')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosks');
    }
};
