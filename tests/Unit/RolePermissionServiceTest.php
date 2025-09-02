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
                'action' => 'view',
                'name' => "permission_$i",
                'description' => "Test permission $i",
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
                'action' => 'view',
                'name' => "docente_permission_$i",
                'description' => "Docente permission $i",
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        for ($i = 44; $i <= 48; $i++) { // Estudiante permissions
            DB::table('permissions')->insert([
                'id' => $i,
                'action' => 'view',
                'name' => "estudiante_permission_$i",
                'description' => "Estudiante permission $i",
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
        // Use reflection to test the private method
        $reflection = new \ReflectionClass($this->rolePermissionService);
        $method = $reflection->getMethod('getDefaultPermissionIdsForRole');
        $method->setAccessible(true);

        // Test Docente role (ID: 2)
        $docentePermissions = $method->invoke($this->rolePermissionService, 2);
        $this->assertEquals(range(34, 43), $docentePermissions);

        // Test Estudiante role (ID: 3)
        $estudiantePermissions = $method->invoke($this->rolePermissionService, 3);
        $expectedEstudiante = array_merge(range(44, 51), [81]);
        $this->assertEquals($expectedEstudiante, $estudiantePermissions);

        // Test Asesor role (ID: 7)
        $asesorPermissions = $method->invoke($this->rolePermissionService, 7);
        $expectedAsesor = array_merge(range(1, 5), range(8, 9), [12]);
        $this->assertEquals($expectedAsesor, $asesorPermissions);
    }
}