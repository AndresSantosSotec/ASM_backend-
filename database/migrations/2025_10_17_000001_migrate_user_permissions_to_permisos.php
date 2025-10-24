<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Migrates user-specific permission data from 'permissions' to 'permisos' table.
     * Updates 'userpermissions' to reference the new 'permisos' table.
     */
    public function up(): void
    {
        // Only proceed if permisos table exists
        if (!Schema::hasTable('permisos')) {
            return;
        }

        // Get all unique permission_ids from userpermissions
        $userPermissionIds = DB::table('userpermissions')
            ->distinct()
            ->pluck('permission_id')
            ->toArray();

        if (empty($userPermissionIds)) {
            return;
        }

        // Get permissions that are referenced by users (from permissions table)
        $permissions = DB::table('permissions')
            ->whereIn('id', $userPermissionIds)
            ->whereNotNull('moduleview_id')
            ->where('action', 'view')
            ->get();

        // Create a mapping of old permission_id to new permiso_id
        $permissionMapping = [];

        foreach ($permissions as $permission) {
            // Check if this permiso already exists
            $existingPermiso = DB::table('permisos')
                ->where('moduleview_id', $permission->moduleview_id)
                ->where('action', $permission->action)
                ->first();

            if ($existingPermiso) {
                $permissionMapping[$permission->id] = $existingPermiso->id;
            } else {
                // Insert into permisos table
                $permisoId = DB::table('permisos')->insertGetId([
                    'moduleview_id' => $permission->moduleview_id,
                    'action' => $permission->action,
                    'name' => $permission->name,
                    'description' => $permission->description ?? 'Migrated from permissions table',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                $permissionMapping[$permission->id] = $permisoId;
            }
        }

        // Update userpermissions to use new permisos ids
        foreach ($permissionMapping as $oldId => $newId) {
            DB::table('userpermissions')
                ->where('permission_id', $oldId)
                ->update(['permission_id' => $newId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We don't reverse this migration as it would require keeping the old mapping
        // If you need to rollback, restore from database backup
    }
};
