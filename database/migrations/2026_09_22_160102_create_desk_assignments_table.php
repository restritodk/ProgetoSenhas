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

        if (! Schema::hasIndex('users', 'users_clinic_id_id_unique')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->unique(['clinic_id', 'id'], 'users_clinic_id_id_unique');
            });
        }

        Schema::create('desk_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id');
            $table->foreignId('desk_id');
            $table->foreignId('user_id');
            $table->dateTime('claimed_at');
            $table->dateTime('last_seen_at');
            $table->timestamps();

            $table->unique('desk_id');
            $table->unique('user_id');
            $table->index(['clinic_id', 'unit_id']);

            $table->foreign(['clinic_id', 'unit_id'])->references(['clinic_id', 'id'])->on('units')->cascadeOnDelete();
            $table->foreign(['clinic_id', 'desk_id'])->references(['clinic_id', 'id'])->on('desks')->cascadeOnDelete();
            $table->foreign(['clinic_id', 'user_id'])->references(['clinic_id', 'id'])->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('desk_assignments');
    }
};
