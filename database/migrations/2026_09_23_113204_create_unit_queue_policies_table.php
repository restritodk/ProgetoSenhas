<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_queue_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id');
            $table->foreignId('critical_ticket_type_id')->nullable()->constrained('ticket_types')->nullOnDelete();
            $table->string('critical_mode', 32)->default('always_first');
            $table->boolean('distribution_enabled')->default(true);
            $table->foreignId('distribution_source_ticket_type_id')->nullable()->constrained('ticket_types')->nullOnDelete();
            $table->unsignedSmallInteger('distribution_source_count')->default(3);
            $table->foreignId('distribution_target_ticket_type_id')->nullable()->constrained('ticket_types')->nullOnDelete();
            $table->unsignedSmallInteger('distribution_target_count')->default(1);
            $table->boolean('anti_starvation_enabled')->default(true);
            $table->unsignedInteger('aging_interval_seconds')->default(60);
            $table->unsignedInteger('aging_bonus_per_interval')->default(5);
            $table->timestamps();

            $table->unique(['unit_id']);
            $table->unique(['clinic_id', 'unit_id'], 'unit_queue_policies_clinic_unit_unique');

            $table->foreign(['clinic_id', 'unit_id'])
                ->references(['clinic_id', 'id'])
                ->on('units')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_queue_policies');
    }
};
