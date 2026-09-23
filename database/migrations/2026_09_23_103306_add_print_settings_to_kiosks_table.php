<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kiosks', function (Blueprint $table): void {
            $table->boolean('print_enabled')->default(false)->after('active');
            $table->unsignedSmallInteger('print_agent_port')->default(17321)->after('print_enabled');
            $table->text('print_agent_secret_encrypted')->nullable()->after('print_agent_port');
            $table->string('print_printer_name', 255)->nullable()->after('print_agent_secret_encrypted');
            $table->string('print_paper_width', 16)->default('80')->after('print_printer_name');
            $table->boolean('print_auto_cut')->default(true)->after('print_paper_width');
            $table->boolean('print_logo')->default(false)->after('print_auto_cut');
            $table->timestamp('print_paired_at')->nullable()->after('print_logo');
        });
    }

    public function down(): void
    {
        Schema::table('kiosks', function (Blueprint $table): void {
            $table->dropColumn([
                'print_enabled',
                'print_agent_port',
                'print_agent_secret_encrypted',
                'print_printer_name',
                'print_paper_width',
                'print_auto_cut',
                'print_logo',
                'print_paired_at',
            ]);
        });
    }
};
