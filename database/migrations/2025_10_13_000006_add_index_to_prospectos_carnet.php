<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds index on carnet field in prospectos table for faster lookups
     */
    public function up(): void
    {
        Schema::table('prospectos', function (Blueprint $table) {
            // Index on carnet for faster student lookups during import
            $indexName = 'prospectos_carnet_index';
            if (!$this->indexExists('prospectos', $indexName)) {
                $table->index('carnet', $indexName);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prospectos', function (Blueprint $table) {
            $indexName = 'prospectos_carnet_index';
            if ($this->indexExists('prospectos', $indexName)) {
                $table->dropIndex($indexName);
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
