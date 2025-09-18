<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Modules;
use App\Models\ModulesViews;
use App\Models\Role;
use App\Models\User;
use App\Models\Permisos;
use Illuminate\Support\Facades\DB;

class VerifySeederResults extends Seeder
{
    /**
     * Verificar los resultados de los seeders.
     */
    public function run(): void
    {
        $this->command->info('🔍 VERIFICANDO RESULTADOS DE LOS SEEDERS...');
        $this->command->info('');

        // Verificar Módulos
        $modulesCount = Modules::count();
        $this->command->info("📁 Módulos creados: {$modulesCount}");
        
        $modules = Modules::orderBy('id')->get(['id', 'name']);
        foreach ($modules as $module) {
            $this->command->info("   - ID {$module->id}: {$module->name}");
        }
        $this->command->info('');

        // Verificar Vistas de Módulos
        $moduleViewsCount = ModulesViews::count();
        $this->command->info("👁️ Vistas de módulos creadas: {$moduleViewsCount}");
        
        $viewsByModule = ModulesViews::select('module_id', DB::raw('count(*) as total'))
            ->groupBy('module_id')
            ->orderBy('module_id')
            ->get();
        
        foreach ($viewsByModule as $view) {
            $moduleName = Modules::find($view->module_id)->name ?? 'Desconocido';
            $this->command->info("   - Módulo {$view->module_id} ({$moduleName}): {$view->total} vistas");
        }
        $this->command->info('');

        // Verificar Roles
        $rolesCount = Role::count();
        $this->command->info("👥 Roles creados: {$rolesCount}");
        
        $roles = Role::orderBy('id')->get(['id', 'name', 'type']);
        foreach ($roles as $role) {
            $this->command->info("   - ID {$role->id}: {$role->name} ({$role->type})");
        }
        $this->command->info('');

        // Verificar Permisos
        $permissionsCount = Permisos::where('is_enabled', true)->count();
        $this->command->info("🔐 Permisos habilitados: {$permissionsCount}");
        $this->command->info('');

        // Verificar Usuario SuperAdmin
        $superAdmin = User::where('email', 'superadmin@blueatlas.com')->first();
        
        if ($superAdmin) {
            $this->command->info("👑 USUARIO SUPERADMIN ENCONTRADO:");
            $this->command->info("   - ID: {$superAdmin->id}");
            $this->command->info("   - Username: {$superAdmin->username}");
            $this->command->info("   - Email: {$superAdmin->email}");
            $this->command->info("   - Carnet: {$superAdmin->carnet}");
            $this->command->info("   - Activo: " . ($superAdmin->is_active ? 'Sí' : 'No'));
            $this->command->info("   - Email verificado: " . ($superAdmin->email_verified ? 'Sí' : 'No'));
            
            // Verificar rol asignado
            $userRole = DB::table('userroles')
                ->join('roles', 'userroles.role_id', '=', 'roles.id')
                ->where('userroles.user_id', $superAdmin->id)
                ->first(['roles.name', 'roles.id']);
            
            if ($userRole) {
                $this->command->info("   - Rol asignado: {$userRole->name} (ID: {$userRole->id})");
            } else {
                $this->command->error("   - ❌ NO SE ENCONTRÓ ROL ASIGNADO");
            }
            
            // Verificar permisos asignados
            $userPermissionsCount = DB::table('userpermissions')
                ->where('user_id', $superAdmin->id)
                ->count();
            
            $this->command->info("   - Permisos asignados: {$userPermissionsCount}");
            
            if ($userPermissionsCount > 0) {
                $scopeBreakdown = DB::table('userpermissions')
                    ->select('scope', DB::raw('count(*) as total'))
                    ->where('user_id', $superAdmin->id)
                    ->groupBy('scope')
                    ->get();
                
                foreach ($scopeBreakdown as $scope) {
                    $this->command->info("     - Scope '{$scope->scope}': {$scope->total} permisos");
                }
            }
            
        } else {
            $this->command->error("❌ USUARIO SUPERADMIN NO ENCONTRADO");
        }
        
        $this->command->info('');
        $this->command->info('✅ VERIFICACIÓN COMPLETADA');
        
        // Resumen final
        $this->command->info('');
        $this->command->info('📊 RESUMEN:');
        $this->command->info("   - Módulos: {$modulesCount}");
        $this->command->info("   - Vistas: {$moduleViewsCount}");
        $this->command->info("   - Roles: {$rolesCount}");
        $this->command->info("   - Permisos: {$permissionsCount}");
        $this->command->info("   - SuperAdmin: " . ($superAdmin ? 'Creado' : 'No encontrado'));
        
        if ($superAdmin) {
            $this->command->info('');
            $this->command->info('🔑 CREDENCIALES DE ACCESO:');
            $this->command->info('   Email: superadmin@blueatlas.com');
            $this->command->info('   Password: SuperAdmin123!');
            $this->command->warn('   ⚠️  Recuerda cambiar la contraseña después del primer login');
        }
    }
}
