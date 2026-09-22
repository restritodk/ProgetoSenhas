<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('desks', 'desks_clinic_id_id_unique')) {
            Schema::table('desks', function (Blueprint $table): void {
                $table->unique(['clinic_id', 'id'], 'desks_clinic_id_id_unique');
            });
        }

        if (! Schema::hasIndex('tickets', 'tickets_clinic_id_id_unique')) {
            Schema::table('tickets', function (Blueprint $table): void {
                $table->unique(['clinic_id', 'id'], 'tickets_clinic_id_id_unique');
            });
        }

        if (! Schema::hasIndex('users', 'users_clinic_id_id_unique')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->unique(['clinic_id', 'id'], 'users_clinic_id_id_unique');
            });
        }

        Schema::create('ticket_calls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id');
            $table->foreignId('ticket_id');
            $table->foreignId('desk_id');
            $table->foreignId('called_by_user_id');
            $table->string('call_type', 32);
            $table->timestamp('called_at');
            $table->timestamps();

            $table->index(['clinic_id', 'unit_id', 'called_at'], 'ticket_calls_history_index');
            $table->index(['clinic_id', 'ticket_id', 'called_at'], 'ticket_calls_ticket_index');

            $table->foreign(['clinic_id', 'unit_id'])->references(['clinic_id', 'id'])->on('units')->cascadeOnDelete();
            $table->foreign(['clinic_id', 'ticket_id'])->references(['clinic_id', 'id'])->on('tickets')->cascadeOnDelete();
            $table->foreign(['clinic_id', 'desk_id'])->references(['clinic_id', 'id'])->on('desks')->cascadeOnDelete();
            $table->foreign(['clinic_id', 'called_by_user_id'])->references(['clinic_id', 'id'])->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_calls');
    }
};
