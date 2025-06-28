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
        Schema::create('tb_precios_programa', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('programa_id');
            $table->decimal('inscripcion', 12);
            $table->decimal('cuota_mensual', 12);
            $table->integer('meses');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_precios_programa');
    }
};
