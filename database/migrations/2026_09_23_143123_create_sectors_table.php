<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sectors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 32);
            $table->string('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['clinic_id', 'unit_id', 'code'], 'sectors_clinic_unit_code_unique');
            $table->index(['clinic_id', 'unit_id', 'active'], 'sectors_clinic_unit_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sectors');
    }
};
