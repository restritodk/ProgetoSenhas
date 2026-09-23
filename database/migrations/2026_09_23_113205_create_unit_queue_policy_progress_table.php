<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_queue_policy_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id');
            $table->foreignId('unit_queue_policy_id')->constrained('unit_queue_policies')->cascadeOnDelete();
            $table->unsignedInteger('source_calls_in_cycle')->default(0);
            $table->unsignedInteger('target_calls_in_cycle')->default(0);
            $table->unsignedInteger('cycle_version')->default(1);
            $table->timestamps();

            $table->unique(['unit_id']);
            $table->unique(['clinic_id', 'unit_id'], 'unit_queue_policy_progress_clinic_unit_unique');

            $table->foreign(['clinic_id', 'unit_id'])
                ->references(['clinic_id', 'id'])
                ->on('units')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_queue_policy_progress');
    }
};
