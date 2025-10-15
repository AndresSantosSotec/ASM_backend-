<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Role;
use App\Models\Permisos;
use App\Models\ModulesViews;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class RolePermissionControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropAllTables();

        // Create necessary tables
        Schema::create('modules', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
        });

        Schema::create('moduleviews', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('module_id');
            $table->string('menu')->nullable();
            $table->string('submenu')->nullable();
            $table->string('view_path');
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->integer('user_count')->default(0);
            $table->string('type')->nullable();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('action');
            $table->unsignedInteger('moduleview_id')->nullable();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('rolepermissions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('role_id');
            $table->unsignedInteger('permission_id');
            $table->string('scope')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->unique(['role_id', 'permission_id']);
        });

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        // Seed test data
        $moduleId = DB::table('modules')->insertGetId(['name' => 'TestModule']);
        
        $mvId1 = DB::table('moduleviews')->insertGetId([
            'module_id' => $moduleId,
            'menu' => 'Seguridad',
            'submenu' => 'Permisos',
            'view_path' => '/seguridad/permisos'
        ]);

        $mvId2 = DB::table('moduleviews')->insertGetId([
            'module_id' => $moduleId,
            'menu' => 'Finanzas',
            'submenu' => 'Pagos',
            'view_path' => '/finanzas/pagos'
        ]);

        // Create permissions for each moduleview
        foreach ([$mvId1, $mvId2] as $mvId) {
            foreach (['view', 'create', 'edit', 'delete', 'export'] as $action) {
                DB::table('permissions')->insert([
                    'action' => $action,
                    'moduleview_id' => $mvId,
                    'name' => "{$action}_moduleview_{$mvId}",
                    'description' => "Test permission {$action}",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Create test role
        DB::table('roles')->insert([
            'name' => 'TestRole',
            'description' => 'Test role for permissions',
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create test user
        DB::table('users')->insert([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_can_list_role_permissions()
    {
        $role = Role::where('name', 'TestRole')->first();
        $user = User::where('email', 'test@example.com')->first();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/roles/{$role->id}/permissions");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            '*' => [
                'moduleview_id',
                'menu',
                'submenu',
                'view_path',
                'permissions' => [
                    'view',
                    'create',
                    'edit',
                    'delete',
                    'export',
                ],
            ],
        ]);
    }

    public function test_can_update_role_permissions()
    {
        $role = Role::where('name', 'TestRole')->first();
        $user = User::where('email', 'test@example.com')->first();
        $moduleview = ModulesViews::where('view_path', '/seguridad/permisos')->first();

        $payload = [
            'permissions' => [
                [
                    'moduleview_id' => $moduleview->id,
                    'actions' => ['view', 'create', 'edit'],
                ],
            ],
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/roles/{$role->id}/permissions", $payload);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Permisos actualizados']);

        // Verify permissions were assigned
        $assignedPermissions = DB::table('rolepermissions')
            ->where('role_id', $role->id)
            ->count();

        $this->assertEquals(3, $assignedPermissions);
    }

    public function test_update_permissions_queries_by_moduleview_id()
    {
        $role = Role::where('name', 'TestRole')->first();
        $user = User::where('email', 'test@example.com')->first();
        $moduleview = ModulesViews::where('view_path', '/finanzas/pagos')->first();

        $payload = [
            'permissions' => [
                [
                    'moduleview_id' => $moduleview->id,
                    'actions' => ['view', 'export'],
                ],
            ],
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/roles/{$role->id}/permissions", $payload);

        $response->assertStatus(200);

        // Verify the correct permissions were assigned based on moduleview_id
        $assignedPermissionIds = DB::table('rolepermissions')
            ->where('role_id', $role->id)
            ->pluck('permission_id')
            ->toArray();

        $expectedPermissionIds = Permisos::where('moduleview_id', $moduleview->id)
            ->whereIn('action', ['view', 'export'])
            ->pluck('id')
            ->toArray();

        $this->assertEquals(count($expectedPermissionIds), count($assignedPermissionIds));
        $this->assertEmpty(array_diff($expectedPermissionIds, $assignedPermissionIds));
    }

    public function test_update_permissions_handles_multiple_moduleviews()
    {
        $role = Role::where('name', 'TestRole')->first();
        $user = User::where('email', 'test@example.com')->first();
        $moduleviews = ModulesViews::all();

        $payload = [
            'permissions' => [
                [
                    'moduleview_id' => $moduleviews[0]->id,
                    'actions' => ['view', 'create'],
                ],
                [
                    'moduleview_id' => $moduleviews[1]->id,
                    'actions' => ['view', 'edit', 'delete'],
                ],
            ],
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/roles/{$role->id}/permissions", $payload);

        $response->assertStatus(200);

        // Verify correct number of permissions assigned (2 + 3 = 5)
        $assignedPermissions = DB::table('rolepermissions')
            ->where('role_id', $role->id)
            ->count();

        $this->assertEquals(5, $assignedPermissions);
    }
}
