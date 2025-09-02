<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RolePermissionService;
use App\Models\User;
use App\Models\Role;

class SyncRolePermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:sync-roles 
                           {--role= : Sync permissions for specific role ID}
                           {--user= : Sync permissions for specific user ID}
                           {--dry-run : Show what would be done without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync permissions for users based on their assigned roles';

    protected $rolePermissionService;

    public function __construct(RolePermissionService $rolePermissionService)
    {
        parent::__construct();
        $this->rolePermissionService = $rolePermissionService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $roleId = $this->option('role');
        $userId = $this->option('user');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('DRY RUN MODE - No changes will be made');
        }

        if ($userId) {
            $this->syncUserPermissions($userId, $dryRun);
        } elseif ($roleId) {
            $this->syncRolePermissions($roleId, $dryRun);
        } else {
            $this->syncAllPermissions($dryRun);
        }

        return 0;
    }

    private function syncUserPermissions($userId, $dryRun = false)
    {
        $user = User::with('userRole.role')->find($userId);
        
        if (!$user) {
            $this->error("User with ID {$userId} not found");
            return;
        }

        if (!$user->userRole) {
            $this->error("User {$userId} has no role assigned");
            return;
        }

        $this->info("Syncing permissions for user {$user->username} (ID: {$user->id}) with role {$user->userRole->role->name}");

        if (!$dryRun) {
            $result = $this->rolePermissionService->assignPermissionsToUser($user);
            if ($result) {
                $this->info("✓ Permissions synced successfully for user {$user->username}");
            } else {
                $this->error("✗ Failed to sync permissions for user {$user->username}");
            }
        } else {
            $this->info("Would sync permissions for user {$user->username}");
        }
    }

    private function syncRolePermissions($roleId, $dryRun = false)
    {
        $role = Role::find($roleId);
        
        if (!$role) {
            $this->error("Role with ID {$roleId} not found");
            return;
        }

        $users = User::whereHas('userRole', function ($query) use ($roleId) {
            $query->where('role_id', $roleId);
        })->with('userRole.role')->get();

        if ($users->isEmpty()) {
            $this->info("No users found with role {$role->name}");
            return;
        }

        $this->info("Syncing permissions for {$users->count()} users with role {$role->name}");

        if (!$dryRun) {
            $results = $this->rolePermissionService->bulkAssignPermissionsByRole($roleId);
            
            $this->info("✓ Successfully synced: {$results['successful']} users");
            
            if ($results['failed'] > 0) {
                $this->error("✗ Failed to sync: {$results['failed']} users");
                foreach ($results['errors'] as $error) {
                    $this->error("  - $error");
                }
            }
        } else {
            foreach ($users as $user) {
                $this->info("Would sync permissions for user {$user->username} (ID: {$user->id})");
            }
        }
    }

    private function syncAllPermissions($dryRun = false)
    {
        $roles = Role::with(['users' => function($query) {
            $query->with('userRole');
        }])->get();

        $totalUsers = 0;
        $successfulUsers = 0;
        $failedUsers = 0;

        foreach ($roles as $role) {
            $users = User::whereHas('userRole', function ($query) use ($role) {
                $query->where('role_id', $role->id);
            })->with('userRole.role')->get();

            if ($users->isEmpty()) {
                continue;
            }

            $totalUsers += $users->count();
            $this->info("Processing role {$role->name} with {$users->count()} users");

            if (!$dryRun) {
                $results = $this->rolePermissionService->bulkAssignPermissionsByRole($role->id);
                $successfulUsers += $results['successful'];
                $failedUsers += $results['failed'];
                
                $this->info("  ✓ Successfully synced: {$results['successful']} users");
                
                if ($results['failed'] > 0) {
                    $this->error("  ✗ Failed to sync: {$results['failed']} users");
                }
            } else {
                foreach ($users as $user) {
                    $this->info("  Would sync permissions for user {$user->username} (ID: {$user->id})");
                }
            }
        }

        if (!$dryRun) {
            $this->info("\nSummary:");
            $this->info("Total users processed: {$totalUsers}");
            $this->info("Successfully synced: {$successfulUsers}");
            $this->info("Failed to sync: {$failedUsers}");
        } else {
            $this->info("\nWould process {$totalUsers} total users across all roles");
        }
    }
}