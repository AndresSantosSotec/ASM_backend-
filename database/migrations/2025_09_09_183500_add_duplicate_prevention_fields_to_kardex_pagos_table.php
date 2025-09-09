<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('kardex_pagos', function (Blueprint $table) {
            // Add normalized fields for uniqueness checks
            $table->string('banco_norm')->nullable()->after('banco');
            $table->string('numero_boleta_norm')->nullable()->after('numero_boleta');
            
            // Add file hash for duplicate file detection
            $table->string('file_sha256', 64)->nullable()->after('archivo_comprobante');
            
            // Add approval fields that were mentioned in existing code
            $table->timestamp('fecha_aprobacion')->nullable()->after('estado_pago');
            $table->string('aprobado_por')->nullable()->after('fecha_aprobacion');
        });

        // Add unique indexes for duplicate prevention
        Schema::table('kardex_pagos', function (Blueprint $table) {
            // Global uniqueness for bank receipt combination
            $table->unique(['banco_norm', 'numero_boleta_norm'], 'unique_bank_receipt');
            
            // File hash uniqueness per student program
            $table->unique(['estudiante_programa_id', 'file_sha256'], 'unique_file_per_student');
        });
    }

    public function down()
    {
        Schema::table('kardex_pagos', function (Blueprint $table) {
            $table->dropIndex('unique_bank_receipt');
            $table->dropIndex('unique_file_per_student');
            $table->dropColumn(['banco_norm', 'numero_boleta_norm', 'file_sha256', 'fecha_aprobacion', 'aprobado_por']);
        });
    }
};