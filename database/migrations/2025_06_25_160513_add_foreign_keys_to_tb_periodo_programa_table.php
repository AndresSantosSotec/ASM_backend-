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
        Schema::table('tb_periodo_programa', function (Blueprint $table) {
            $table->foreign(['periodo_id'], 'fk_pp_periodo')->references(['id'])->on('tb_periodos_inscripcion')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['programa_id'], 'fk_pp_programa')->references(['id'])->on('tb_programas')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tb_periodo_programa', function (Blueprint $table) {
            $table->dropForeign('fk_pp_periodo');
            $table->dropForeign('fk_pp_programa');
        });
    }
};
