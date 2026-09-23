<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('display_panels', 'display_panels_clinic_id_id_unique')) {
            Schema::table('display_panels', function (Blueprint $table): void {
                $table->unique(['clinic_id', 'id'], 'display_panels_clinic_id_id_unique');
            });
        }

        if (! Schema::hasIndex('media_items', 'media_items_clinic_id_id_unique')) {
            Schema::table('media_items', function (Blueprint $table): void {
                $table->unique(['clinic_id', 'id'], 'media_items_clinic_id_id_unique');
            });
        }

        Schema::create('display_panel_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('display_panel_id');
            $table->foreignId('media_item_id');
            $table->unsignedInteger('position')->default(1);
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['display_panel_id', 'media_item_id'], 'display_panel_media_unique');
            $table->index(['display_panel_id', 'active', 'position'], 'display_panel_media_playback_index');

            $table->foreign(['clinic_id', 'display_panel_id'])
                ->references(['clinic_id', 'id'])
                ->on('display_panels')
                ->cascadeOnDelete();
            $table->foreign(['clinic_id', 'media_item_id'])
                ->references(['clinic_id', 'id'])
                ->on('media_items')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('display_panel_media');
    }
};
