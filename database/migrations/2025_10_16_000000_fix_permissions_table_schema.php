<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration ensures the permissions table has the correct schema:
     * - Renames 'route_path' to 'moduleview_id' if it exists
     * - Adds 'moduleview_id' column if it doesn't exist
     * - Adds 'is_enabled' column if it doesn't exist
     */
    public function up(): void
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        
        // Check if route_path column exists and moduleview_id doesn't
        $hasRoutePath = Schema::hasColumn('permissions', 'route_path');
        $hasModuleviewId = Schema::hasColumn('permissions', 'moduleview_id');
        
        if ($hasRoutePath && !$hasModuleviewId) {
            // For PostgreSQL, we need to handle the rename specially
            if ($driver === 'pgsql') {
                // Drop any existing foreign key constraints on route_path
                DB::statement("
                    DO $$ 
                    DECLARE
                        r RECORD;
                    BEGIN
                        FOR r IN (
                            SELECT constraint_name 
                            FROM information_schema.table_constraints 
                            WHERE table_name = 'permissions' 
                            AND constraint_type = 'FOREIGN KEY'
                            AND constraint_name LIKE '%route_path%'
                        ) LOOP
                            EXECUTE 'ALTER TABLE permissions DROP CONSTRAINT IF EXISTS ' || quote_ident(r.constraint_name);
                        END LOOP;
                    END $$;
                ");
                
                // Drop any indexes on route_path
                DB::statement("
                    DO $$ 
                    DECLARE
                        r RECORD;
                    BEGIN
                        FOR r IN (
                            SELECT indexname 
                            FROM pg_indexes 
                            WHERE tablename = 'permissions' 
                            AND indexdef LIKE '%route_path%'
                        ) LOOP
                            EXECUTE 'DROP INDEX IF EXISTS ' || quote_ident(r.indexname);
                        END LOOP;
                    END $$;
                ");
                
                // Rename the column
                DB::statement('ALTER TABLE permissions RENAME COLUMN route_path TO moduleview_id');
                
                // Change the data type to integer if it's not already
                DB::statement('ALTER TABLE permissions ALTER COLUMN moduleview_id TYPE INTEGER USING moduleview_id::INTEGER');
            } else {
                // For MySQL/MariaDB
                Schema::table('permissions', function (Blueprint $table) {
                    $table->renameColumn('route_path', 'moduleview_id');
                });
            }
        } elseif (!$hasModuleviewId) {
            // If moduleview_id doesn't exist at all, add it
            Schema::table('permissions', function (Blueprint $table) {
                $table->unsignedInteger('moduleview_id')->nullable()->after('action');
            });
        }
        
        // Add is_enabled column if it doesn't exist
        if (!Schema::hasColumn('permissions', 'is_enabled')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->boolean('is_enabled')->default(true);
            });
        }
        
        // Add index and foreign key for moduleview_id
        if (Schema::hasColumn('permissions', 'moduleview_id')) {
            // Check if index exists
            if ($driver === 'pgsql') {
                $indexExists = DB::select("
                    SELECT indexname 
                    FROM pg_indexes 
                    WHERE tablename = 'permissions' 
                    AND indexdef LIKE '%moduleview_id%'
                ");
                
                if (empty($indexExists)) {
                    DB::statement('CREATE INDEX permissions_moduleview_id_index ON permissions(moduleview_id)');
                }
                
                // Check if foreign key exists
                $fkExists = DB::select("
                    SELECT constraint_name 
                    FROM information_schema.table_constraints 
                    WHERE table_name = 'permissions' 
                    AND constraint_type = 'FOREIGN KEY'
                    AND constraint_name LIKE '%moduleview%'
                ");
                
                if (empty($fkExists) && Schema::hasTable('moduleviews')) {
                    DB::statement('
                        ALTER TABLE permissions 
                        ADD CONSTRAINT permissions_moduleview_id_foreign 
                        FOREIGN KEY (moduleview_id) 
                        REFERENCES moduleviews(id) 
                        ON UPDATE CASCADE 
                        ON DELETE CASCADE
                    ');
                }
            } else {
                // MySQL/MariaDB - use Schema builder
                Schema::table('permissions', function (Blueprint $table) {
                    $table->index('moduleview_id');
                    if (Schema::hasTable('moduleviews')) {
                        $table->foreign('moduleview_id')
                            ->references('id')
                            ->on('moduleviews')
                            ->onUpdate('cascade')
                            ->onDelete('cascade');
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            // Remove is_enabled if it exists
            if (Schema::hasColumn('permissions', 'is_enabled')) {
                $table->dropColumn('is_enabled');
            }
            
            // Note: We don't reverse the moduleview_id rename since that would break the system
            // If you need to rollback, you should restore from backup
        });
    }
};
