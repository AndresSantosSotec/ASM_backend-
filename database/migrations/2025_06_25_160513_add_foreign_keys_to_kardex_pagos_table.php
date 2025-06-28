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
        Schema::table('kardex_pagos', function (Blueprint $table) {
            $table->foreign(['cuota_id'], 'kardex_pagos_cuota_id_fkey')->references(['id'])->on('cuotas_programa_estudiante')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['estudiante_programa_id'], 'kardex_pagos_estudiante_programa_id_fkey')->references(['id'])->on('estudiante_programa')->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kardex_pagos', function (Blueprint $table) {
            $table->dropForeign('kardex_pagos_cuota_id_fkey');
            $table->dropForeign('kardex_pagos_estudiante_programa_id_fkey');
        });
    }
};
