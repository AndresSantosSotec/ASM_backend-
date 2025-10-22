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
        Schema::create('prospectos_adicionales', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('id_estudiante');
            $table->unsignedBigInteger('id_estudiante_programa')->nullable();
            $table->text('notas_pago')->nullable();
            $table->text('nomenclatura')->nullable();
            $table->string('status_actual', 50)->default('Activo');
            $table->timestamps();

            $table->unique('id_estudiante');
            $table->foreign('id_estudiante')
                ->references('id')
                ->on('prospectos')
                ->onDelete('cascade');

            $table->foreign('id_estudiante_programa')
                ->references('id')
                ->on('estudiante_programa')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prospectos_adicionales');
    }
};
