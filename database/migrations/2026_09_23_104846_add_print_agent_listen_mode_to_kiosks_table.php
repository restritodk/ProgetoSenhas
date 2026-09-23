<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kiosks', function (Blueprint $table): void {
            $table->string('print_agent_listen_mode', 16)->default('local')->after('print_agent_port');
            $table->string('print_agent_host', 255)->nullable()->after('print_agent_listen_mode');
        });
    }

    public function down(): void
    {
        Schema::table('kiosks', function (Blueprint $table): void {
            $table->dropColumn(['print_agent_listen_mode', 'print_agent_host']);
        });
    }
};
