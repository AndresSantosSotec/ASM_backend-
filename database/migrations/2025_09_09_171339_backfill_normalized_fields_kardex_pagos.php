<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Backfill normalized fields for existing records
        DB::statement("
            UPDATE kardex_pagos 
            SET 
                numero_boleta_norm = UPPER(REGEXP_REPLACE(COALESCE(numero_boleta, ''), '[^A-Za-z0-9]', '')),
                banco_norm = UPPER(TRIM(COALESCE(banco, '')))
            WHERE numero_boleta_norm IS NULL OR banco_norm IS NULL
        ");
    }

    public function down()
    {
        // Reset normalized fields
        DB::statement("
            UPDATE kardex_pagos 
            SET 
                numero_boleta_norm = NULL,
                banco_norm = NULL
        ");
    }
};