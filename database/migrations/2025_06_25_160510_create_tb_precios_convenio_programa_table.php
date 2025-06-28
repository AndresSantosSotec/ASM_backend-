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
        Schema::create('tb_precios_convenio_programa', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('convenio_id');
            $table->bigInteger('programa_id');
            $table->decimal('inscripcion', 12)->nullable();
            $table->decimal('cuota_mensual', 12);
            $table->integer('meses');

            $table->unique(['convenio_id', 'programa_id'], 'tb_precios_convenio_programa_convenio_id_programa_id_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_precios_convenio_programa');
    }
};
