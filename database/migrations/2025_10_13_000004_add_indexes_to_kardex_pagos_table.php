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
        $doctrineSchemaManager = $connection->getDoctrineSchemaManager();
        $doctrineTable = $doctrineSchemaManager->listTableDetails($table);
        return $doctrineTable->hasIndex($indexName);
    }
};
