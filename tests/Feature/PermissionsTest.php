<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;
use App\Models\Role;
use App\Models\User;
use App\Models\Permission;
use App\Models\Moduleview;
use App\Services\PermissionService;

class PermissionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropAllTables();

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
            $table->unique(['role_id','permission_id','scope']);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::create('userroles', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('role_id');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();
        });

        // Seed roles
        $roles = ['Administrador','Finanzas','Estudiante'];
        foreach ($roles as $name) {
            DB::table('roles')->insert(['name'=>$name]);
        }

        $moduleId = DB::table('modules')->insertGetId(['name'=>'Test']);
        Moduleview::create(['module_id'=>$moduleId,'menu'=>'Fin','submenu'=>'Pagos','view_path'=>'/finanzas/gestion-pagos']);
        Moduleview::create(['module_id'=>$moduleId,'menu'=>'Est','submenu'=>'Ficha','view_path'=>'/estudiantes/ficha']);
        Moduleview::create(['module_id'=>$moduleId,'menu'=>'Seg','submenu'=>'Logs','view_path'=>'/seguridad/logs']);

        (new \Database\Seeders\PermissionsSeeder())->run();
        (new \Database\Seeders\RolePermissionsSeeder())->run();

        $adminId = DB::table('users')->insertGetId(['name'=>'Admin','email'=>'admin@test.com','password'=>'x']);
        $finId = DB::table('users')->insertGetId(['name'=>'Fin','email'=>'fin@test.com','password'=>'x']);
        $estId = DB::table('users')->insertGetId(['name'=>'Est','email'=>'est@test.com','password'=>'x']);

        $adminRole = Role::where('name','Administrador')->first();
        $finRole = Role::where('name','Finanzas')->first();
        $estRole = Role::where('name','Estudiante')->first();

        DB::table('userroles')->insert(['user_id'=>$adminId,'role_id'=>$adminRole->id,'assigned_at'=>now()]);
        DB::table('userroles')->insert(['user_id'=>$finId,'role_id'=>$finRole->id,'assigned_at'=>now()]);
        DB::table('userroles')->insert(['user_id'=>$estId,'role_id'=>$estRole->id,'assigned_at'=>now()]);
    }

    public function test_seeder_creates_permissions(): void
    {
        $this->assertEquals(5 * Moduleview::count(), Permission::count());
    }

    public function test_permission_service_respects_roles(): void
    {
        $service = app(PermissionService::class);
        $finUser = User::where('email', 'fin@test.com')->first();
        $estUser = User::where('email', 'est@test.com')->first();
        $admin = User::where('email', 'admin@test.com')->first();

        $this->assertTrue($service->canDo($finUser, 'edit', '/finanzas/gestion-pagos'));
        $this->assertFalse($service->canDo($estUser, 'edit', '/finanzas/gestion-pagos'));
        $this->assertTrue($service->canDo($admin, 'delete', '/seguridad/logs'));
    }

    public function test_gate_authorization(): void
    {
        $finUser = User::where('email', 'fin@test.com')->first();
        $estUser = User::where('email', 'est@test.com')->first();

        $this->assertTrue(Gate::forUser($finUser)->allows('do-action-on-path', ['edit', '/finanzas/gestion-pagos']));
        $this->assertTrue(Gate::forUser($estUser)->denies('do-action-on-path', ['edit', '/finanzas/gestion-pagos']));
    }

    public function test_permissions_sync_is_idempotent(): void
    {
        $count = Permission::count();
        Artisan::call('permissions:sync');
        $this->assertEquals($count, Permission::count());
    }

    public function test_can_create_permission_via_api(): void
    {
        $mv = Moduleview::create([
            'module_id' => 1,
            'menu' => 'Extra',
            'submenu' => 'View',
            'view_path' => '/extra/view',
        ]);

        $user = User::first();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/permissions', [
            'moduleview_id' => $mv->id,
            'action' => 'view',
            'description' => 'test',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('permissions', [
            'moduleview_id' => $mv->id,
            'name' => 'view:/extra/view',
        ]);
    }
}
