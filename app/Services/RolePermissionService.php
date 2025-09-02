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
            
            // Get permissions for this role
            $rolePermissions = $this->getPermissionsForRole($roleId);
            
            if (empty($rolePermissions)) {
                Log::info("No permissions configured for role {$roleId}, using default permissions");
                $this->assignDefaultPermissionsForRole($user, $roleId);
            } else {
                // Clear existing permissions for this user
                UserPermisos::where('user_id', $user->id)->delete();

                // Assign new permissions
                foreach ($rolePermissions as $permission) {
                    UserPermisos::create([
                        'user_id' => $user->id,
                        'permission_id' => $permission->permission_id,
                        'assigned_at' => now(),
                        'scope' => $permission->scope ?? 'self'
                    ]);
                }
            }

            DB::commit();
            Log::info("Permissions assigned successfully to user {$user->id} for role {$roleId}");
            return true;

        } catch (Exception $e) {
            DB::rollback();
            Log::error("Failed to assign permissions to user {$user->id}: " . $e->getMessage());
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
    private function assignDefaultPermissionsForRole(User $user, int $roleId): void
    {
        $permissionIds = $this->getDefaultPermissionIdsForRole($roleId);
        
        foreach ($permissionIds as $permissionId) {
            // Verify permission exists before assigning
            $permissionExists = DB::table('permissions')->where('id', $permissionId)->exists();
            
            if ($permissionExists) {
                UserPermisos::create([
                    'user_id' => $user->id,
                    'permission_id' => $permissionId,
                    'assigned_at' => now(),
                    'scope' => 'self'
                ]);
            } else {
                Log::warning("Permission ID {$permissionId} does not exist, skipping assignment for user {$user->id}");
            }
        }
    }

    /**
     * Get default permission IDs for each role based on requirements
     */
    private function getDefaultPermissionIdsForRole(int $roleId): array
    {
        switch ($roleId) {
            case 1: // Administrador - todos los permisos
                return DB::table('permissions')->pluck('id')->toArray();
                
            case 2: // Docente - permisos 34-43 (Portal Docente)
                return range(34, 43);
                
            case 3: // Estudiante - permisos 44-51, 81 (Estudiante)
                return array_merge(range(44, 51), [81]);
                
            case 4: // Administrativo - permisos 59-64 (Administrativo)
                return range(59, 64);
                
            case 5: // Finanzas - permisos 52-58 (Finanzas)
                return range(52, 58);
                
            case 6: // Seguridad - permisos 65, 67-69, 82-85 (Seguridad)
                return array_merge([65], range(67, 69), range(82, 85));
                
            case 7: // Asesor - permisos 1-5, 8-9, 12 (Prospectos)
                return array_merge(range(1, 5), range(8, 9), [12]);
                
            default:
                Log::warning("Unknown role ID {$roleId}, no default permissions assigned");
                return [];
        }
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