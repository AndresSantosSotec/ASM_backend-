<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('kardex_pagos', function (Blueprint $table) {
            $table->string('numero_boleta')->nullable()->after('metodo_pago');
            $table->string('banco')->nullable()->after('numero_boleta');
            $table->string('archivo_comprobante')->nullable()->after('banco');
            $table->enum('estado_pago', ['pendiente_revision', 'aprobado', 'rechazado'])->default('pendiente_revision')->after('archivo_comprobante');
        });
    }

    public function down()
    {
        Schema::table('kardex_pagos', function (Blueprint $table) {
            $table->dropColumn(['numero_boleta', 'banco', 'archivo_comprobante', 'estado_pago']);
        });
    }
};
