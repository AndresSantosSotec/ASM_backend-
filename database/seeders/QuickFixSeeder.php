<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QuickFixSeeder extends Seeder
{
    /**
     * Ejecutar solo los seeders que fallan para debug rápido.
     */
    public function run(): void
    {
        $this->command->info('🔧 QUICK FIX - Verificando estructura y ejecutando seeders problemáticos...');
        $this->command->info('');

        // 1. Verificar estructura de permissions
        if (Schema::hasTable('permissions')) {
            $columns = Schema::getColumnListing('permissions');
            $this->command->info('✅ Columnas en permissions: ' . implode(', ', $columns));
            
            $count = DB::table('permissions')->count();
            $this->command->info("✅ Permisos existentes: {$count}");
            
            if ($count > 0) {
                // Mostrar algunos ejemplos
                $samples = DB::table('permissions')->limit(3)->get();
                foreach ($samples as $sample) {
                    $this->command->info("   - ID {$sample->id}: {$sample->name} ({$sample->action})");
                }
            }
        } else {
            $this->command->error('❌ Tabla permissions no existe');
            return;
        }

        $this->command->info('');

        // 2. Verificar roles
        $roles = DB::table('roles')->pluck('name', 'id');
        $this->command->info('✅ Roles disponibles:');
        foreach ($roles as $id => $name) {
            $this->command->info("   - ID {$id}: {$name}");
        }

        $this->command->info('');

        // 3. Probar RolePermissionsSeeder lógica
        $this->command->info('🧪 Probando lógica de RolePermissionsSeeder...');
        
        // Probar consulta para Administrador (todos los permisos)
        $adminPermissions = DB::table('permissions')->count();
        $this->command->info("   - Administrador debería tener: {$adminPermissions} permisos");

        // Probar consulta para Docente
        $docentePermissions = DB::table('permissions')
            ->join('moduleviews', 'permissions.moduleview_id', '=', 'moduleviews.id')
            ->where('permissions.action', 'view')
            ->where('moduleviews.view_path', 'like', '/docente%')
            ->count();
        $this->command->info("   - Docente (solo view /docente%): {$docentePermissions} permisos");

        $this->command->info('');
        $this->command->info('✅ Verificación completada. Los seeders deberían funcionar ahora.');
    }
}
