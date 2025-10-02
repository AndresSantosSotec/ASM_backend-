<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('kardex_pagos', function (Blueprint $table) {
            // Add fecha_recibo column if it doesn't exist
            if (!Schema::hasColumn('kardex_pagos', 'fecha_recibo')) {
                $table->date('fecha_recibo')->nullable()->after('fecha_pago');
            }
            
            // Add created_by column if it doesn't exist (from previous migration that might have been skipped)
            if (!Schema::hasColumn('kardex_pagos', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('observaciones');
                $table->foreign('created_by')->references('id')->on('users');
            }
            
            // Add uploaded_by column if it doesn't exist
            if (!Schema::hasColumn('kardex_pagos', 'uploaded_by')) {
                $table->unsignedBigInteger('uploaded_by')->nullable()->after('observaciones');
                $table->foreign('uploaded_by')->references('id')->on('users');
            }
            
            // Add updated_by column if it doesn't exist
            if (!Schema::hasColumn('kardex_pagos', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->after('observaciones');
                $table->foreign('updated_by')->references('id')->on('users');
            }
        });
    }

    public function down()
    {
        Schema::table('kardex_pagos', function (Blueprint $table) {
            // Drop foreign keys first
            if (Schema::hasColumn('kardex_pagos', 'uploaded_by')) {
                $table->dropForeign(['uploaded_by']);
            }
            if (Schema::hasColumn('kardex_pagos', 'updated_by')) {
                $table->dropForeign(['updated_by']);
            }
            if (Schema::hasColumn('kardex_pagos', 'created_by')) {
                $table->dropForeign(['created_by']);
            }
            
            // Drop columns
            $columnsToDelete = [];
            if (Schema::hasColumn('kardex_pagos', 'fecha_recibo')) {
                $columnsToDelete[] = 'fecha_recibo';
            }
            if (Schema::hasColumn('kardex_pagos', 'uploaded_by')) {
                $columnsToDelete[] = 'uploaded_by';
            }
            if (Schema::hasColumn('kardex_pagos', 'updated_by')) {
                $columnsToDelete[] = 'updated_by';
            }
            if (Schema::hasColumn('kardex_pagos', 'created_by')) {
                $columnsToDelete[] = 'created_by';
            }
            
            if (!empty($columnsToDelete)) {
                $table->dropColumn($columnsToDelete);
            }
        });
    }
};
