<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('prefix', 8);
            $table->unsignedSmallInteger('priority');
            $table->boolean('active')->default(true)->index();
            $table->timestamps();

            $table->unique(['clinic_id', 'prefix']);
            $table->index(['clinic_id', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_types');
    }
};
