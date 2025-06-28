<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tb_programas')) {
            Schema::create('tb_programas', function (Blueprint $table) {
                $table->id();
                
                // Campos basados en la referencia
                $table->string('abreviatura', 50)->nullable();
                $table->string('nombre_del_programa', 255);
                $table->integer('meses')->default(0);

                // Asumiendo que los siguientes son "conteos" de cursos. Ajusta a tu necesidad:
                $table->integer('area_comun')->default(0);
                $table->integer('cursos_de_bba')->default(0);
                $table->integer('area_de_especialidad')->default(0);
                $table->integer('seminario_de_gerencia')->default(0);
                $table->integer('capstone_project')->default(0);
                $table->integer('escritura_de_casos')->default(0);
                $table->integer('certificacion_internacional')->default(0);
                $table->integer('total_cursos')->default(0);

                // Campos adicionales
                $table->dateTime('fecha_creacion')->default(DB::raw('CURRENT_TIMESTAMP'));
                $table->boolean('activo')->default(true);

                // Si deseas created_at / updated_at:
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('tb_programas');
    }
};
