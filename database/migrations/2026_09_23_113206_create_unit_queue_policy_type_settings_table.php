<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_queue_policy_type_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_queue_policy_id')->constrained('unit_queue_policies')->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->constrained('ticket_types')->cascadeOnDelete();
            $table->unsignedInteger('rescue_wait_seconds')->nullable();
            $table->timestamps();

            $table->unique(
                ['unit_queue_policy_id', 'ticket_type_id'],
                'unit_queue_policy_type_settings_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_queue_policy_type_settings');
    }
};
