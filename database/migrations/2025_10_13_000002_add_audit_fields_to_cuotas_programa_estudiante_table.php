<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds audit fields (created_by, updated_by, deleted_by) to cuotas_programa_estudiante table
     */
    public function up(): void
    {
        Schema::table('cuotas_programa_estudiante', function (Blueprint $table) {
            // Add audit fields if they don't exist
            if (!Schema::hasColumn('cuotas_programa_estudiante', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('paid_at');
            }
            if (!Schema::hasColumn('cuotas_programa_estudiante', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
            }
            if (!Schema::hasColumn('cuotas_programa_estudiante', 'deleted_by')) {
                $table->unsignedBigInteger('deleted_by')->nullable()->after('updated_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cuotas_programa_estudiante', function (Blueprint $table) {
            $columnsToCheck = ['created_by', 'updated_by', 'deleted_by'];
            foreach ($columnsToCheck as $column) {
                if (Schema::hasColumn('cuotas_programa_estudiante', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
