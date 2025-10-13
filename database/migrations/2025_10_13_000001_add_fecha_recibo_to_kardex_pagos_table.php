<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds fecha_recibo column to kardex_pagos table
     */
    public function up(): void
    {
        Schema::table('kardex_pagos', function (Blueprint $table) {
            // Check if column doesn't exist to avoid errors
            if (!Schema::hasColumn('kardex_pagos', 'fecha_recibo')) {
                $table->date('fecha_recibo')->nullable()->after('fecha_pago');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kardex_pagos', function (Blueprint $table) {
            if (Schema::hasColumn('kardex_pagos', 'fecha_recibo')) {
                $table->dropColumn('fecha_recibo');
            }
        });
    }
};
