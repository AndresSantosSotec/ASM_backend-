#!/usr/bin/env php
<?php
/**
 * Pre-Migration Validation Script
 * 
 * Run this script BEFORE running the migration to check current database state
 * Usage: php validate_permissions_schema.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "\n";
echo "===========================================\n";
echo "  Permission Schema Validation Script\n";
echo "===========================================\n\n";

// Check database connection
try {
    DB::connection()->getPdo();
    echo "✓ Database connection successful\n";
    $driver = DB::connection()->getDriverName();
    echo "  Driver: {$driver}\n\n";
} catch (\Exception $e) {
    echo "✗ Database connection failed: {$e->getMessage()}\n";
    exit(1);
}

// Check if permissions table exists
if (!Schema::hasTable('permissions')) {
    echo "✗ Table 'permissions' does not exist\n";
    echo "  You need to create the permissions table first.\n";
    exit(1);
}
echo "✓ Table 'permissions' exists\n\n";

// Check columns in permissions table
echo "Checking permissions table columns:\n";
echo "-----------------------------------\n";

$hasModuleviewId = Schema::hasColumn('permissions', 'moduleview_id');
$hasRoutePath = Schema::hasColumn('permissions', 'route_path');
$hasIsEnabled = Schema::hasColumn('permissions', 'is_enabled');
$hasAction = Schema::hasColumn('permissions', 'action');
$hasName = Schema::hasColumn('permissions', 'name');

echo "  moduleview_id: " . ($hasModuleviewId ? "✓ EXISTS" : "✗ MISSING") . "\n";
echo "  route_path:    " . ($hasRoutePath ? "⚠ EXISTS (will be renamed)" : "✓ NOT EXISTS") . "\n";
echo "  is_enabled:    " . ($hasIsEnabled ? "✓ EXISTS" : "⚠ MISSING (will be added)") . "\n";
echo "  action:        " . ($hasAction ? "✓ EXISTS" : "✗ MISSING") . "\n";
echo "  name:          " . ($hasName ? "✓ EXISTS" : "✗ MISSING") . "\n";
echo "\n";

// Determine what the migration will do
echo "Migration Actions:\n";
echo "------------------\n";

if ($hasRoutePath && !$hasModuleviewId) {
    echo "  1. RENAME 'route_path' → 'moduleview_id'\n";
    echo "     - Drop foreign keys on route_path\n";
    echo "     - Drop indexes on route_path\n";
    echo "     - Rename column\n";
    echo "     - Convert to INTEGER type\n";
} elseif (!$hasModuleviewId) {
    echo "  1. ADD 'moduleview_id' column (INTEGER, nullable)\n";
} else {
    echo "  1. ✓ 'moduleview_id' already exists - no action needed\n";
}

if (!$hasIsEnabled) {
    echo "  2. ADD 'is_enabled' column (BOOLEAN, default TRUE)\n";
} else {
    echo "  2. ✓ 'is_enabled' already exists - no action needed\n";
}

echo "  3. ADD index on 'moduleview_id' (if missing)\n";
echo "  4. ADD foreign key: moduleview_id → moduleviews.id\n";
echo "\n";

// Check if moduleviews table exists
if (!Schema::hasTable('moduleviews')) {
    echo "⚠ Warning: Table 'moduleviews' does not exist\n";
    echo "  Foreign key constraint will be skipped\n\n";
}

// Count existing permissions
try {
    $permCount = DB::table('permissions')->count();
    echo "Current Data:\n";
    echo "-------------\n";
    echo "  Total permissions: {$permCount}\n";
    
    if ($hasRoutePath && !$hasModuleviewId) {
        $withRoutePath = DB::table('permissions')->whereNotNull('route_path')->count();
        $nullRoutePath = DB::table('permissions')->whereNull('route_path')->count();
        echo "  With route_path: {$withRoutePath}\n";
        echo "  NULL route_path: {$nullRoutePath}\n";
    }
    
    if ($hasModuleviewId) {
        $withModuleviewId = DB::table('permissions')->whereNotNull('moduleview_id')->count();
        $nullModuleviewId = DB::table('permissions')->whereNull('moduleview_id')->count();
        echo "  With moduleview_id: {$withModuleviewId}\n";
        echo "  NULL moduleview_id: {$nullModuleviewId}\n";
    }
    echo "\n";
} catch (\Exception $e) {
    echo "⚠ Could not count permissions: {$e->getMessage()}\n\n";
}

// Check for potential issues
echo "Potential Issues:\n";
echo "-----------------\n";

$issues = [];

if (!$hasAction || !$hasName) {
    $issues[] = "Missing required columns (action, name) - migration may fail";
}

if ($hasRoutePath && !$hasModuleviewId) {
    try {
        $nonNumeric = DB::table('permissions')
            ->whereNotNull('route_path')
            ->whereRaw("route_path !~ '^[0-9]+$'")
            ->count();
        
        if ($nonNumeric > 0) {
            $issues[] = "Found {$nonNumeric} permissions with non-numeric route_path values";
            $issues[] = "These may fail when converting to INTEGER type";
            
            // Show sample
            $sample = DB::table('permissions')
                ->whereNotNull('route_path')
                ->whereRaw("route_path !~ '^[0-9]+$'")
                ->select('id', 'route_path', 'name')
                ->limit(5)
                ->get();
            
            echo "  ⚠ Sample non-numeric route_path values:\n";
            foreach ($sample as $row) {
                echo "    - ID {$row->id}: route_path = '{$row->route_path}'\n";
            }
        }
    } catch (\Exception $e) {
        // Postgres-specific regex might not work on MySQL
    }
}

if (empty($issues)) {
    echo "  ✓ No issues detected\n";
} else {
    foreach ($issues as $issue) {
        echo "  ⚠ {$issue}\n";
    }
}
echo "\n";

// Recommendations
echo "Recommendations:\n";
echo "----------------\n";

if ($hasRoutePath && !$hasModuleviewId) {
    echo "  1. BACKUP your database before running migration!\n";
    echo "     pg_dump -U username -d database > backup_$(date +%Y%m%d_%H%M%S).sql\n\n";
    echo "  2. The migration will rename 'route_path' to 'moduleview_id'\n";
    echo "     Make sure route_path contains valid moduleview IDs (integers)\n\n";
} else {
    echo "  1. BACKUP your database before running migration (best practice)\n";
    echo "     pg_dump -U username -d database > backup_$(date +%Y%m%d_%H%M%S).sql\n\n";
}

echo "  3. After migration, run:\n";
echo "     php artisan migrate\n\n";

echo "  4. Test the API endpoints:\n";
echo "     GET  /api/userpermissions?user_id=1\n";
echo "     POST /api/userpermissions\n\n";

// Final verdict
echo "===========================================\n";
if (empty($issues) || (count($issues) === 1 && strpos($issues[0], 'non-numeric') === false)) {
    echo "Status: ✓ READY TO MIGRATE\n";
    echo "You can safely run: php artisan migrate\n";
} else {
    echo "Status: ⚠ REVIEW REQUIRED\n";
    echo "Please review the issues above before migrating\n";
}
echo "===========================================\n\n";
