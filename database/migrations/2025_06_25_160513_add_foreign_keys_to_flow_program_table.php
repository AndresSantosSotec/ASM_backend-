<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('flow_program', function (Blueprint $table) {
            $table->foreign(['flow_id'], 'flow_program_flow_id_fkey')->references(['id'])->on('approval_flows')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['program_id'], 'flow_program_program_id_fkey')->references(['id'])->on('tb_programas')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('flow_program', function (Blueprint $table) {
            $table->dropForeign('flow_program_flow_id_fkey');
            $table->dropForeign('flow_program_program_id_fkey');
        });
    }
};
