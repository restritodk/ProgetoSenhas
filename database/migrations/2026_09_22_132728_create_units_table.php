<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
            $table->unique(['clinic_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
