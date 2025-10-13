<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds performance indexes to cuotas_programa_estudiante table
     */
    public function up(): void
    {
        Schema::table('cuotas_programa_estudiante', function (Blueprint $table) {
            // Index on estudiante_programa_id for faster lookups
            $indexName = 'cuotas_estudiante_programa_id_index';
            if (!$this->indexExists('cuotas_programa_estudiante', $indexName)) {
                $table->index('estudiante_programa_id', $indexName);
            }

            // Composite index for finding pending quotas by student and due date
            $compositeIndexName = 'cuotas_estado_fecha_index';
            if (!$this->indexExists('cuotas_programa_estudiante', $compositeIndexName)) {
                $table->index(['estudiante_programa_id', 'estado', 'fecha_vencimiento'], $compositeIndexName);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cuotas_programa_estudiante', function (Blueprint $table) {
            $indexName = 'cuotas_estudiante_programa_id_index';
            if ($this->indexExists('cuotas_programa_estudiante', $indexName)) {
                $table->dropIndex($indexName);
            }

            $compositeIndexName = 'cuotas_estado_fecha_index';
            if ($this->indexExists('cuotas_programa_estudiante', $compositeIndexName)) {
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
