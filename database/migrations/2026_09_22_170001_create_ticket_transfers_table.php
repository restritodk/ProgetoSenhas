<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('tickets', 'tickets_clinic_id_id_unique')) {
            Schema::table('tickets', function (Blueprint $table): void {
                $table->unique(['clinic_id', 'id'], 'tickets_clinic_id_id_unique');
            });
        }

        if (! Schema::hasIndex('desks', 'desks_clinic_id_id_unique')) {
            Schema::table('desks', function (Blueprint $table): void {
                $table->unique(['clinic_id', 'id'], 'desks_clinic_id_id_unique');
            });
        }

        if (! Schema::hasIndex('users', 'users_clinic_id_id_unique')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->unique(['clinic_id', 'id'], 'users_clinic_id_id_unique');
            });
        }

        Schema::create('ticket_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id');
            $table->foreignId('ticket_id');
            $table->foreignId('from_desk_id');
            $table->foreignId('to_desk_id')->nullable();
            $table->foreignId('transferred_by_user_id');
            $table->string('transfer_type', 32);
            $table->string('reason', 255)->nullable();
            $table->dateTime('transferred_at');
            $table->timestamps();

            $table->index(['clinic_id', 'unit_id', 'transferred_at'], 'ticket_transfers_history_index');
            $table->index(['clinic_id', 'ticket_id', 'transferred_at'], 'ticket_transfers_ticket_index');

            $table->foreign(['clinic_id', 'unit_id'])
                ->references(['clinic_id', 'id'])
                ->on('units')
                ->restrictOnDelete();
            $table->foreign(['clinic_id', 'ticket_id'])
                ->references(['clinic_id', 'id'])
                ->on('tickets')
                ->restrictOnDelete();
            $table->foreign(['clinic_id', 'from_desk_id'])
                ->references(['clinic_id', 'id'])
                ->on('desks')
                ->restrictOnDelete();
            $table->foreign(['clinic_id', 'to_desk_id'])
                ->references(['clinic_id', 'id'])
                ->on('desks')
                ->restrictOnDelete();
            $table->foreign(['clinic_id', 'transferred_by_user_id'])
                ->references(['clinic_id', 'id'])
                ->on('users')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_transfers');
    }
};
