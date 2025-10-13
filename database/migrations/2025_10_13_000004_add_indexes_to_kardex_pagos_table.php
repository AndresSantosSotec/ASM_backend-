<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds performance indexes to kardex_pagos table
     */
    public function up(): void
    {
        Schema::table('kardex_pagos', function (Blueprint $table) {
            // Index on estudiante_programa_id for faster lookups
            $indexName = 'kardex_pagos_estudiante_programa_id_index';
            if (!$this->indexExists('kardex_pagos', $indexName)) {
                $table->index('estudiante_programa_id', $indexName);
            }

            // Composite index for duplicate detection by boleta and student
            $compositeIndexName = 'kardex_pagos_boleta_student_index';
            if (!$this->indexExists('kardex_pagos', $compositeIndexName)) {
                $table->index(['numero_boleta_normalizada', 'estudiante_programa_id'], $compositeIndexName);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kardex_pagos', function (Blueprint $table) {
            $indexName = 'kardex_pagos_estudiante_programa_id_index';
            if ($this->indexExists('kardex_pagos', $indexName)) {
                $table->dropIndex($indexName);
            }

            $compositeIndexName = 'kardex_pagos_boleta_student_index';
            if ($this->indexExists('kardex_pagos', $compositeIndexName)) {
                $table->dropIndex($compositeIndexName);
            }
        });
    }

    /**
     * Check if an index exists on a table
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();
        
        // Query information_schema for PostgreSQL or MySQL
        $query = "SELECT COUNT(*) as count 
                  FROM information_schema.statistics 
                  WHERE table_schema = ? 
                  AND table_name = ? 
                  AND index_name = ?";
        
        $result = $connection->selectOne($query, [$databaseName, $table, $indexName]);
        return $result->count > 0;
    }
};
