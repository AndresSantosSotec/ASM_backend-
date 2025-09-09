<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Update the enum to include all required states mentioned in requirements
        DB::statement("ALTER TABLE kardex_pagos MODIFY COLUMN estado_pago ENUM('pendiente_revision', 'en_revision', 'aprobado', 'rechazado', 'anulado') DEFAULT 'pendiente_revision'");
    }

    public function down()
    {
        // Revert to original enum values
        DB::statement("ALTER TABLE kardex_pagos MODIFY COLUMN estado_pago ENUM('pendiente_revision', 'aprobado', 'rechazado') DEFAULT 'pendiente_revision'");
    }
};