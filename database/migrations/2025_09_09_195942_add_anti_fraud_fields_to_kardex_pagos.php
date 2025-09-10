<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('kardex_pagos', function (Blueprint $table) {
            $table->string('numero_boleta_normalizada', 120)->nullable()->after('numero_boleta')->index();
            $table->string('banco_normalizado', 120)->nullable()->after('banco')->index();
            $table->string('boleta_fingerprint', 128)->nullable()->after('banco')->unique();
            $table->string('archivo_hash', 128)->nullable()->after('archivo_comprobante')->unique();
        });
    }

    public function down(): void
    {
        Schema::table('kardex_pagos', function (Blueprint $table) {
            $table->dropUnique(['boleta_fingerprint']);
            $table->dropUnique(['archivo_hash']);
            $table->dropColumn(['numero_boleta_normalizada', 'banco_normalizado', 'boleta_fingerprint', 'archivo_hash']);
        });
    }
};
