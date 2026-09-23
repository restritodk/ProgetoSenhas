<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('kiosks', 'kiosks_clinic_id_id_unique')) {
            Schema::table('kiosks', function (Blueprint $table): void {
                $table->unique(['clinic_id', 'id'], 'kiosks_clinic_id_id_unique');
            });
        }

        if (! Schema::hasIndex('tickets', 'tickets_clinic_id_id_unique')) {
            Schema::table('tickets', function (Blueprint $table): void {
                $table->unique(['clinic_id', 'id'], 'tickets_clinic_id_id_unique');
            });
        }

        Schema::create('kiosk_issuance_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kiosk_id');
            $table->string('request_token', 64);
            $table->foreignId('ticket_type_id');
            $table->foreignId('ticket_id')->nullable();
            $table->timestamps();

            $table->unique(['kiosk_id', 'request_token'], 'kiosk_issuance_attempts_token_unique');
            $table->index(['clinic_id', 'kiosk_id', 'created_at'], 'kiosk_issuance_attempts_lookup');

            $table->foreign(['clinic_id', 'kiosk_id'])
                ->references(['clinic_id', 'id'])
                ->on('kiosks')
                ->cascadeOnDelete();
            $table->foreign(['clinic_id', 'ticket_type_id'])
                ->references(['clinic_id', 'id'])
                ->on('ticket_types')
                ->cascadeOnDelete();
            $table->foreign('ticket_id')
                ->references('id')
                ->on('tickets')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosk_issuance_attempts');
    }
};
