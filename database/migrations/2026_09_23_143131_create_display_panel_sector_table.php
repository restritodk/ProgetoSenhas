<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('display_panel_sector', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('display_panel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sector_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['display_panel_id', 'sector_id'], 'display_panel_sector_unique');
            $table->index(['clinic_id', 'display_panel_id'], 'display_panel_sector_clinic_panel_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('display_panel_sector');
    }
};
