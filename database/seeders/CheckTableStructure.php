<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CheckTableStructure extends Seeder
{
    /**
     * Verificar la estructura de las tablas principales.
     */
    public function run(): void
    {
        $this->command->info('🔍 VERIFICANDO ESTRUCTURA DE TABLAS...');
        $this->command->info('');

        $tables = ['modules', 'moduleviews', 'permissions', 'roles', 'users', 'userroles', 'userpermissions'];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                $columns = Schema::getColumnListing($table);
                $count = DB::table($table)->count();
                
                $this->command->info("✅ Tabla: {$table} ({$count} registros)");
                $this->command->info("   Columnas: " . implode(', ', $columns));
                $this->command->info('');
            } else {
                $this->command->error("❌ Tabla: {$table} NO EXISTE");
                $this->command->info('');
            }
        }

        // Verificar relaciones específicas
        $this->command->info('🔗 VERIFICANDO RELACIONES...');
        
        // Verificar moduleviews
        if (Schema::hasTable('moduleviews')) {
            $moduleviewsWithModules = DB::table('moduleviews')
                ->join('modules', 'moduleviews.module_id', '=', 'modules.id')
                ->count();
            $this->command->info("✅ ModuleViews con módulos válidos: {$moduleviewsWithModules}");
        }

        // Verificar permissions
        if (Schema::hasTable('permissions')) {
            $permissionsWithModuleviews = DB::table('permissions')
                ->whereNotNull('moduleview_id')
                ->join('moduleviews', 'permissions.moduleview_id', '=', 'moduleviews.id')
                ->count();
            $globalPermissions = DB::table('permissions')
                ->whereNull('moduleview_id')
                ->count();
            
            $this->command->info("✅ Permisos con moduleviews: {$permissionsWithModuleviews}");
            $this->command->info("✅ Permisos globales: {$globalPermissions}");
        }

        $this->command->info('');
        $this->command->info('✅ VERIFICACIÓN DE ESTRUCTURA COMPLETADA');
    }
}
