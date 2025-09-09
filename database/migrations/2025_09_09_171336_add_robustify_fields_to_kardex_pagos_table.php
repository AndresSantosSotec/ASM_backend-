<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('kardex_pagos', function (Blueprint $table) {
            // Normalized fields for duplicate detection
            $table->string('numero_boleta_norm', 120)->nullable()->after('numero_boleta');
            $table->string('banco_norm', 80)->nullable()->after('banco');
            $table->char('file_sha256', 64)->nullable()->after('archivo_comprobante');
            
            // Audit fields
            $table->timestamp('fecha_aprobacion')->nullable()->after('estado_pago');
            $table->string('aprobado_por', 100)->nullable()->after('fecha_aprobacion');
            $table->string('ip_address', 45)->nullable()->after('aprobado_por');
            $table->text('user_agent')->nullable()->after('ip_address');
            
            // Add indexes for performance
            $table->index(['numero_boleta_norm']);
            $table->index(['banco_norm']);
            $table->index(['file_sha256']);
        });
    }

    public function down()
    {
        Schema::table('kardex_pagos', function (Blueprint $table) {
            $table->dropIndex(['numero_boleta_norm']);
            $table->dropIndex(['banco_norm']);
            $table->dropIndex(['file_sha256']);
            
            $table->dropColumn([
                'numero_boleta_norm',
                'banco_norm', 
                'file_sha256',
                'fecha_aprobacion',
                'aprobado_por',
                'ip_address',
                'user_agent'
            ]);
        });
    }
};