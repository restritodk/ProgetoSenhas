<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index('clinic_id');
        });

        Schema::create('conversation_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('clinic_id');
            $table->foreignId('user_id');
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id'], 'conversation_participants_unique');
            $table->index(['clinic_id', 'user_id'], 'conversation_participants_user_index');
            $table->foreign(['clinic_id', 'user_id'])
                ->references(['clinic_id', 'id'])
                ->on('users')
                ->cascadeOnDelete();
        });

        Schema::create('messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('clinic_id');
            $table->foreignId('sender_id');
            $table->text('body');
            $table->timestamps();

            $table->index(['conversation_id', 'id'], 'messages_conversation_id_index');
            $table->index(['clinic_id', 'sender_id'], 'messages_sender_index');
            $table->foreign(['clinic_id', 'sender_id'])
                ->references(['clinic_id', 'id'])
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
    }
};
