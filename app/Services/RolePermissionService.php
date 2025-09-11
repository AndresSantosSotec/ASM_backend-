<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserPermisos;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class RolePermissionService
{
    /**
     * Get permissions configured for a specific role
     */
    public function getPermissionsForRole(int $roleId): array
    {
        return DB::table('role_permissions_config')
            ->where('role_id', $roleId)
            ->select('permission_id', 'action', 'scope')
            ->get()
            ->toArray();
    }

    /**
     * Assign permissions to a user based on their role
     */
    public function assignPermissionsToUser(User $user): bool
    {
        try {
            DB::beginTransaction();

            // Get user's role
            $userRole = $user->userRole;
            if (!$userRole) {
                Log::warning("User {$user->id} has no role assigned, skipping permission assignment");
                DB::rollback();
                return false;
            }

            $roleId = $userRole->role_id;
            Log::info("Assigning permissions to user {$user->id} with role {$roleId}");
            
            // Clear existing permissions for this user first
            $deletedCount = UserPermisos::where('user_id', $user->id)->delete();
            Log::info("Cleared {$deletedCount} existing permissions for user {$user->id}");
            
            // Get permissions for this role
            $rolePermissions = $this->getPermissionsForRole($roleId);
            
            if (empty($rolePermissions)) {
                Log::info("No permissions configured for role {$roleId}, using default permissions");
                $result = $this->assignDefaultPermissionsForRole($user, $roleId);
                
                if (!$result) {
                    Log::error("Failed to assign default permissions for role {$roleId}");
                    DB::rollback();
                    return false;
                }
            } else {
                Log::info("Found " . count($rolePermissions) . " configured permissions for role {$roleId}");
                
                // Assign configured permissions
                $assignedCount = 0;
                foreach ($rolePermissions as $permission) {
                    try {
                        UserPermisos::create([
                            'user_id' => $user->id,
                            'permission_id' => $permission->permission_id,
                            'assigned_at' => now(),
                            'scope' => $permission->scope ?? 'self'
                        ]);
                        $assignedCount++;
                    } catch (Exception $e) {
                        Log::warning("Failed to assign permission {$permission->permission_id} to user {$user->id}: " . $e->getMessage());
                    }
                }
                
                Log::info("Assigned {$assignedCount} permissions to user {$user->id}");
            }

            DB::commit();
            Log::info("Permission assignment completed successfully for user {$user->id} with role {$roleId}");
            return true;

        } catch (Exception $e) {
            DB::rollback();
            Log::error("Failed to assign permissions to user {$user->id}: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Update user permissions when role changes
     */
    public function updateUserPermissionsOnRoleChange(User $user, int $oldRoleId, int $newRoleId): bool
    {
        try {
            DB::beginTransaction();

            // Remove old permissions
            UserPermisos::where('user_id', $user->id)->delete();

            // Assign new permissions based on new role
            $result = $this->assignPermissionsToUser($user);

            if ($result) {
                DB::commit();
                Log::info("User {$user->id} permissions updated from role {$oldRoleId} to role {$newRoleId}");
                return true;
            } else {
                DB::rollback();
                return false;
            }

        } catch (Exception $e) {
            DB::rollback();
            Log::error("Failed to update permissions for user {$user->id} role change: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Assign default permissions for role based on business logic
     * This method handles the specific permission ranges mentioned in requirements
     */
    private function assignDefaultPermissionsForRole(User $user, int $roleId): bool
    {
        try {
            $permissionIds = $this->getDefaultPermissionIdsForRole($roleId);
            
            if (empty($permissionIds)) {
                Log::warning("No default permissions defined for role {$roleId}");
                return false;
            }

            Log::info("Attempting to assign " . count($permissionIds) . " default permissions for role {$roleId}");
            
            $assignedCount = 0;
            $skippedCount = 0;
            
            foreach ($permissionIds as $permissionId) {
                // Verify permission exists before assigning
                $permissionExists = DB::table('permissions')->where('id', $permissionId)->exists();
                
                if ($permissionExists) {
                    try {
                        UserPermisos::create([
                            'user_id' => $user->id,
                            'permission_id' => $permissionId,
                            'assigned_at' => now(),
                            'scope' => 'self'
                        ]);
                        $assignedCount++;
                    } catch (Exception $e) {
                        Log::warning("Failed to create permission record for user {$user->id}, permission {$permissionId}: " . $e->getMessage());
                        $skippedCount++;
                    }
                } else {
                    Log::warning("Permission ID {$permissionId} does not exist, skipping assignment for user {$user->id}");
                    $skippedCount++;
                }
            }
            
            Log::info("Default permissions assignment complete for user {$user->id}: {$assignedCount} assigned, {$skippedCount} skipped");
            
            // Return true if at least some permissions were assigned
            return $assignedCount > 0;
            
        } catch (Exception $e) {
            Log::error("Error in assignDefaultPermissionsForRole for user {$user->id}, role {$roleId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get default permission IDs for each role based on requirements
     */
    private function getDefaultPermissionIdsForRole(int $roleId): array
    {
        // Try to get permissions dynamically based on patterns first
        $dynamicPermissions = $this->getDynamicPermissionsForRole($roleId);
        
        if (!empty($dynamicPermissions)) {
            Log::info("Using dynamic permissions for role {$roleId}: " . count($dynamicPermissions) . " found");
            return $dynamicPermissions;
        }
        
        // Fallback to hardcoded ranges
        Log::info("Using hardcoded permission ranges for role {$roleId}");
        
        switch ($roleId) {
            case 1: // Administrador - todos los permisos
                return DB::table('permissions')->where('is_enabled', true)->pluck('id')->toArray();
                
            case 2: // Docente - permisos 34-43 (Portal Docente)
                return $this->getExistingPermissionsInRange(34, 43);
                
            case 3: // Estudiante - permisos 44-51, 81 (Estudiante)
                return array_merge(
                    $this->getExistingPermissionsInRange(44, 51), 
                    $this->getExistingPermissionsInRange(81, 81)
                );
                
            case 4: // Administrativo - permisos 59-64 (Administrativo)
                return $this->getExistingPermissionsInRange(59, 64);
                
            case 5: // Finanzas - permisos 52-58 (Finanzas)
                return $this->getExistingPermissionsInRange(52, 58);
                
            case 6: // Seguridad - permisos 65, 67-69, 82-85 (Seguridad)
                return array_merge(
                    $this->getExistingPermissionsInRange(65, 65),
                    $this->getExistingPermissionsInRange(67, 69),
                    $this->getExistingPermissionsInRange(82, 85)
                );
                
            case 7: // Asesor - permisos 1-5, 8-9, 12 (Prospectos)
                return array_merge(
                    $this->getExistingPermissionsInRange(1, 5),
                    $this->getExistingPermissionsInRange(8, 9),
                    $this->getExistingPermissionsInRange(12, 12)
                );
                
            default:
                Log::warning("Unknown role ID {$roleId}, no default permissions assigned");
                return [];
        }
    }

    /**
     * Get permissions that exist in a given range
     */
    private function getExistingPermissionsInRange(int $start, int $end): array
    {
        return DB::table('permissions')
            ->where('is_enabled', true)
            ->whereBetween('id', [$start, $end])
            ->pluck('id')
            ->toArray();
    }

    /**
     * Try to get permissions dynamically based on role patterns
     */
    private function getDynamicPermissionsForRole(int $roleId): array
    {
        try {
            // Get role name to match with permission patterns
            $role = DB::table('roles')->where('id', $roleId)->first();
            
            if (!$role) {
                return [];
            }
            
            $roleName = strtolower($role->name);
            
            // Try to match permissions based on role name patterns
            $patterns = [
                'administrador' => ['module' => '%'], // All modules
                'docente' => ['module' => 'docente', 'section' => 'portal'],
                'estudiante' => ['module' => 'estudiante'],
                'administrativo' => ['module' => 'administrativo'],
                'finanzas' => ['module' => 'finanzas'],
                'seguridad' => ['module' => 'seguridad'],
                'asesor' => ['module' => 'prospectos']
            ];
            
            if (isset($patterns[$roleName])) {
                $query = DB::table('permissions')->where('is_enabled', true);
                
                foreach ($patterns[$roleName] as $field => $value) {
                    if ($value === '%') {
                        // Skip - get all permissions for admin
                        continue;
                    }
                    $query->where($field, 'LIKE', "%{$value}%");
                }
                
                $permissions = $query->pluck('id')->toArray();
                
                if (!empty($permissions)) {
                    Log::info("Found " . count($permissions) . " dynamic permissions for role {$roleName}");
                    return $permissions;
                }
            }
            
        } catch (Exception $e) {
            Log::warning("Failed to get dynamic permissions for role {$roleId}: " . $e->getMessage());
        }
        
        return [];
    }

    /**
     * Bulk assign permissions to multiple users by role
     */
    public function bulkAssignPermissionsByRole(int $roleId): array
    {
        $users = User::whereHas('userRole', function ($query) use ($roleId) {
            $query->where('role_id', $roleId);
        })->get();

        $results = [
            'successful' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($users as $user) {
            if ($this->assignPermissionsToUser($user)) {
                $results['successful']++;
            } else {
                $results['failed']++;
                $results['errors'][] = "Failed to assign permissions to user {$user->id}";
            }
        }

        return $results;
    }

    /**
     * Configure permissions for a role in the role_permissions_config table
     */
    public function configureRolePermissions(int $roleId, array $permissionIds, string $action = 'view', string $scope = 'self'): bool
    {
        try {
            DB::beginTransaction();

            // Clear existing configuration for this role and action
            DB::table('role_permissions_config')
                ->where('role_id', $roleId)
                ->where('action', $action)
                ->delete();

            // Insert new configuration
            $records = [];
            foreach ($permissionIds as $permissionId) {
                $records[] = [
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'action' => $action,
                    'scope' => $scope,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }

            DB::table('role_permissions_config')->insert($records);

            DB::commit();
            Log::info("Role permissions configuration updated for role {$roleId}");
            return true;

        } catch (Exception $e) {
            DB::rollback();
            Log::error("Failed to configure role permissions for role {$roleId}: " . $e->getMessage());
            return false;
        }
    }
}