<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estudiante_programa', function (Blueprint $table) {
            $table->dropForeign('estudiante_programa_programa_id_fkey');
            $table->foreign('programa_id', 'estudiante_programa_programa_id_fkey')
                ->references('id')->on('tb_programas')
                ->onUpdate('no action')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('estudiante_programa', function (Blueprint $table) {
            $table->dropForeign('estudiante_programa_programa_id_fkey');
            $table->foreign('programa_id', 'estudiante_programa_programa_id_fkey')
                ->references('id')->on('tb_programas')
                ->onUpdate('no action')->onDelete('no action');
        });
    }
};
