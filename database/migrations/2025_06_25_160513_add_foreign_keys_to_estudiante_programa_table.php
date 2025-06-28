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
        Schema::table('estudiante_programa', function (Blueprint $table) {
            $table->foreign(['convenio_id'], 'estudiante_programa_convenio_id_fkey')->references(['id'])->on('tb_convenio')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['programa_id'], 'estudiante_programa_programa_id_fkey')->references(['id'])->on('tb_programas')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['prospecto_id'], 'estudiante_programa_prospecto_id_fkey')->references(['id'])->on('prospectos')->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('estudiante_programa', function (Blueprint $table) {
            $table->dropForeign('estudiante_programa_convenio_id_fkey');
            $table->dropForeign('estudiante_programa_programa_id_fkey');
            $table->dropForeign('estudiante_programa_prospecto_id_fkey');
        });
    }
};
