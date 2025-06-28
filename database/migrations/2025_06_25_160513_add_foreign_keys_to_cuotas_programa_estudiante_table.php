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
        Schema::table('cuotas_programa_estudiante', function (Blueprint $table) {
            $table->foreign(['estudiante_programa_id'], 'cuotas_programa_estudiante_estudiante_programa_id_fkey')->references(['id'])->on('estudiante_programa')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cuotas_programa_estudiante', function (Blueprint $table) {
            $table->dropForeign('cuotas_programa_estudiante_estudiante_programa_id_fkey');
        });
    }
};
