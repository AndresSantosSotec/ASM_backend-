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
        Schema::create('kardex_pagos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('estudiante_programa_id');
            $table->bigInteger('cuota_id')->nullable();
            $table->date('fecha_pago');
            $table->decimal('monto_pagado', 12);
            $table->string('metodo_pago', 50)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamp('created_at')->default(DB::raw("now()"));
            $table->timestamp('updated_at')->default(DB::raw("now()"));
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kardex_pagos');
    }
};
