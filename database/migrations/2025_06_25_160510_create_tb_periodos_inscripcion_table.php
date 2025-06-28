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
        Schema::create('tb_periodos_inscripcion', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre', 100);
            $table->string('codigo', 50)->unique('tb_periodos_inscripcion_codigo_key');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->text('descripcion')->nullable();
            $table->integer('cupos_total')->default(0);
            $table->integer('descuento')->default(0);
            $table->boolean('activo')->default(false);
            $table->boolean('visible')->default(true);
            $table->boolean('notificaciones')->default(true);
            $table->integer('inscritos_count')->default(0);
            $table->timestamp('fecha_creacion')->useCurrent();
            $table->timestamp('fecha_actualizacion')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_periodos_inscripcion');
    }
};
