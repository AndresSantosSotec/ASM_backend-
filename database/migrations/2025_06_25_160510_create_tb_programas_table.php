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
        Schema::create('tb_programas', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('abreviatura', 50)->nullable();
            $table->string('nombre_del_programa');
            $table->integer('meses')->default(0);
            $table->integer('area_comun')->default(0);
            $table->integer('cursos_de_bba')->default(0);
            $table->integer('area_de_especialidad')->default(0);
            $table->integer('seminario_de_gerencia')->default(0);
            $table->integer('capstone_project')->default(0);
            $table->integer('escritura_de_casos')->default(0);
            $table->integer('certificacion_internacional')->default(0);
            $table->integer('total_cursos')->default(0);
            $table->timestamp('fecha_creacion')->useCurrent();
            $table->boolean('activo')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_programas');
    }
};
