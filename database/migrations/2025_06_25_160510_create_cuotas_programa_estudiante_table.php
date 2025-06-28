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
        Schema::create('cuotas_programa_estudiante', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('estudiante_programa_id');
            $table->integer('numero_cuota');
            $table->date('fecha_vencimiento');
            $table->decimal('monto', 12);
            $table->string('estado', 20)->default('pendiente');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('created_at')->default(DB::raw("now()"));
            $table->timestamp('updated_at')->default(DB::raw("now()"));
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cuotas_programa_estudiante');
    }
};
