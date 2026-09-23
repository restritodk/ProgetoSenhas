<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sector_ticket_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sector_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->constrained()->cascadeOnDelete();
            $table->boolean('active')->default(true);
            $table->string('display_name')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['sector_id', 'ticket_type_id'], 'sector_ticket_types_unique');
            $table->index(['clinic_id', 'sector_id', 'active', 'position'], 'sector_ticket_types_offer_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sector_ticket_types');
    }
};
