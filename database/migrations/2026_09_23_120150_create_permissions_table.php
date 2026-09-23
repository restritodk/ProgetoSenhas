<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('module', 50);
            $table->string('name');
            $table->string('description', 500);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['module', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
