<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('estudiante_programa', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('prospecto_id');
            $table->bigInteger('programa_id');
            $table->bigInteger('convenio_id')->nullable();
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->integer('duracion_meses');
            $table->decimal('inscripcion', 12);
            $table->decimal('cuota_mensual', 12);
            $table->decimal('inversion_total', 14);
            $table->timestamp('created_at')->default(DB::raw("now()"));
            $table->timestamp('updated_at')->default(DB::raw("now()"));
            $table->softDeletes();
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->integer('deleted_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estudiante_programa');
    }
};
