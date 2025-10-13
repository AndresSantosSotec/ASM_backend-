<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds performance indexes to estudiante_programa table
     */
    public function up(): void
    {
        Schema::table('estudiante_programa', function (Blueprint $table) {
            // Index on prospecto_id for faster lookups
            $indexName = 'estudiante_programa_prospecto_id_index';
            if (!$this->indexExists('estudiante_programa', $indexName)) {
                $table->index('prospecto_id', $indexName);
            }

            // Index on programa_id for faster lookups
            $programaIndexName = 'estudiante_programa_programa_id_index';
            if (!$this->indexExists('estudiante_programa', $programaIndexName)) {
                $table->index('programa_id', $programaIndexName);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('estudiante_programa', function (Blueprint $table) {
            $indexName = 'estudiante_programa_prospecto_id_index';
            if ($this->indexExists('estudiante_programa', $indexName)) {
                $table->dropIndex($indexName);
            }

            $programaIndexName = 'estudiante_programa_programa_id_index';
            if ($this->indexExists('estudiante_programa', $programaIndexName)) {
                $table->dropIndex($programaIndexName);
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
