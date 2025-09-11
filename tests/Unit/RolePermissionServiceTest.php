<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\UserRole;
use App\Models\UserPermisos;
use App\Services\RolePermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class RolePermissionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $rolePermissionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rolePermissionService = new RolePermissionService();
    }

    public function test_assigns_permissions_to_docente_role()
    {
        // Create a role for Docente (ID: 2)
        $role = Role::create([
            'id' => 2,
            'name' => 'Docente',
            'description' => 'Portal Docente',
            'is_system' => true,
            'user_count' => 0
        ]);

        // Create a user
        $user = User::create([
            'username' => 'test_docente',
            'email' => 'docente@test.com',
            'password_hash' => bcrypt('password'),
            'first_name' => 'Test',
            'last_name' => 'Docente',
            'is_active' => true
        ]);

        // Create user role assignment
        UserRole::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'assigned_at' => now()
        ]);

        // Create some test permissions in the expected range (34-43)
        for ($i = 34; $i <= 38; $i++) {
            DB::table('permissions')->insert([
                'id' => $i,
                'module' => 'docente',
                'section' => 'portal',
                'resource' => 'view',
                'action' => 'view',
                'effect' => 'allow',
                'description' => "Test permission $i",
                'route_path' => "/docente/portal/$i",
                'is_enabled' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Reload user with role relationship
        $user->load('userRole');

        // Test permission assignment
        $result = $this->rolePermissionService->assignPermissionsToUser($user);

        $this->assertTrue($result);

        // Verify permissions were assigned
        $assignedPermissions = UserPermisos::where('user_id', $user->id)->pluck('permission_id')->toArray();
        
        // Should have permissions 34-38 (the ones we created)
        $expectedPermissions = range(34, 38);
        
        foreach ($expectedPermissions as $expectedPermission) {
            $this->assertContains($expectedPermission, $assignedPermissions);
        }
    }

    public function test_updates_permissions_on_role_change()
    {
        // Create roles
        $docenteRole = Role::create([
            'id' => 2,
            'name' => 'Docente',
            'description' => 'Portal Docente',
            'is_system' => true,
            'user_count' => 0
        ]);

        $estudianteRole = Role::create([
            'id' => 3,
            'name' => 'Estudiante',
            'description' => 'Portal Estudiante',
            'is_system' => true,
            'user_count' => 0
        ]);

        // Create a user initially assigned to Docente role
        $user = User::create([
            'username' => 'test_user',
            'email' => 'user@test.com',
            'password_hash' => bcrypt('password'),
            'first_name' => 'Test',
            'last_name' => 'User',
            'is_active' => true
        ]);

        $userRole = UserRole::create([
            'user_id' => $user->id,
            'role_id' => $docenteRole->id,
            'assigned_at' => now()
        ]);

        // Create permissions for both roles
        for ($i = 34; $i <= 38; $i++) { // Docente permissions
            DB::table('permissions')->insert([
                'id' => $i,
                'module' => 'docente',
                'section' => 'portal',
                'resource' => 'view',
                'action' => 'view',
                'effect' => 'allow',
                'description' => "Docente permission $i",
                'route_path' => "/docente/portal/$i",
                'is_enabled' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        for ($i = 44; $i <= 48; $i++) { // Estudiante permissions
            DB::table('permissions')->insert([
                'id' => $i,
                'module' => 'estudiante',
                'section' => 'portal',
                'resource' => 'view',
                'action' => 'view',
                'effect' => 'allow',
                'description' => "Estudiante permission $i",
                'route_path' => "/estudiante/portal/$i",
                'is_enabled' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Initially assign Docente permissions
        $user->load('userRole');
        $this->rolePermissionService->assignPermissionsToUser($user);

        // Verify initial permissions
        $initialPermissions = UserPermisos::where('user_id', $user->id)->pluck('permission_id')->toArray();
        $this->assertContains(34, $initialPermissions);
        $this->assertNotContains(44, $initialPermissions);

        // Change role to Estudiante
        $userRole->update(['role_id' => $estudianteRole->id]);
        $user->load('userRole');

        // Update permissions based on new role
        $result = $this->rolePermissionService->updateUserPermissionsOnRoleChange(
            $user, 
            $docenteRole->id, 
            $estudianteRole->id
        );

        $this->assertTrue($result);

        // Verify permissions were updated
        $updatedPermissions = UserPermisos::where('user_id', $user->id)->pluck('permission_id')->toArray();
        $this->assertNotContains(34, $updatedPermissions); // Old Docente permission removed
        $this->assertContains(44, $updatedPermissions); // New Estudiante permission added
    }

    public function test_get_default_permission_ids_for_role()
    {
        // Create some test permissions first
        for ($i = 34; $i <= 38; $i++) {
            DB::table('permissions')->insert([
                'id' => $i,
                'module' => 'docente',
                'section' => 'portal',
                'resource' => 'view',
                'action' => 'view',
                'effect' => 'allow',
                'description' => "Docente permission $i",
                'route_path' => "/docente/view/$i",
                'is_enabled' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Use reflection to test the private method
        $reflection = new \ReflectionClass($this->rolePermissionService);
        $method = $reflection->getMethod('getDefaultPermissionIdsForRole');
        $method->setAccessible(true);

        // Test Docente role (ID: 2) - should only return existing permissions
        $docentePermissions = $method->invoke($this->rolePermissionService, 2);
        
        // Should only contain the permissions we created (34-38), not the full range (34-43)
        $this->assertEquals([34, 35, 36, 37, 38], $docentePermissions);

        // Test unknown role
        $unknownPermissions = $method->invoke($this->rolePermissionService, 99);
        $this->assertEquals([], $unknownPermissions);
    }

    public function test_handles_missing_permissions_gracefully()
    {
        // Create a role for Docente (ID: 2)
        $role = Role::create([
            'id' => 2,
            'name' => 'Docente',
            'description' => 'Portal Docente',
            'is_system' => true,
            'user_count' => 0
        ]);

        // Create a user
        $user = User::create([
            'username' => 'test_docente',
            'email' => 'docente@test.com',
            'password_hash' => bcrypt('password'),
            'first_name' => 'Test',
            'last_name' => 'Docente',
            'is_active' => true
        ]);

        // Create user role assignment
        UserRole::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'assigned_at' => now()
        ]);

        // Don't create any permissions - so assignment should fail gracefully

        // Reload user with role relationship
        $user->load('userRole');

        // Test permission assignment - should return false but not crash
        $result = $this->rolePermissionService->assignPermissionsToUser($user);

        $this->assertFalse($result);

        // Verify no permissions were assigned
        $assignedPermissions = UserPermisos::where('user_id', $user->id)->count();
        $this->assertEquals(0, $assignedPermissions);
    }
}