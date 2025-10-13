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
        $doctrineSchemaManager = $connection->getDoctrineSchemaManager();
        $doctrineTable = $doctrineSchemaManager->listTableDetails($table);
        return $doctrineTable->hasIndex($indexName);
    }
};
