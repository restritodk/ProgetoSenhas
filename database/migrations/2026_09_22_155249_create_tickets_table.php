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

        if (! Schema::hasIndex('ticket_types', 'ticket_types_clinic_id_id_unique')) {
            Schema::table('ticket_types', function (Blueprint $table): void {
                $table->unique(['clinic_id', 'id'], 'ticket_types_clinic_id_id_unique');
            });
        }

        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id');
            $table->foreignId('ticket_type_id');
            $table->unsignedInteger('sequence_number');
            $table->date('sequence_date');
            $table->string('status', 32);
            $table->timestamp('issued_at');
            $table->timestamp('called_at')->nullable();
            $table->timestamp('service_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['clinic_id', 'unit_id', 'ticket_type_id', 'sequence_date', 'sequence_number'],
                'tickets_sequence_unique',
            );
            $table->index(['clinic_id', 'unit_id', 'status', 'issued_at'], 'tickets_queue_lookup_index');
            $table->index(['clinic_id', 'unit_id', 'ticket_type_id', 'sequence_date'], 'tickets_sequence_lookup_index');

            $table->foreign(['clinic_id', 'unit_id'])
                ->references(['clinic_id', 'id'])
                ->on('units')
                ->cascadeOnDelete();
            $table->foreign(['clinic_id', 'ticket_type_id'])
                ->references(['clinic_id', 'id'])
                ->on('ticket_types')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
