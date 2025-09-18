<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // Primero los datos base
            CoursesSeeder::class,
            PrecioProgramasSeeder::class,
            ProgramasSeeder::class,
            
            // Luego módulos y vistas
            ModulesSeeder::class,
            ModuleViewsSeeder::class,
            
            // Después permisos (primero los automáticos, luego los básicos)
            PermissionsSeeder::class,
            BasicPermissionsSeeder::class,
            
            // Roles y configuración de permisos
            RolesSeeder::class,
            RolePermissionsConfigSeeder::class,
            RolePermissionsSeeder::class,
            
            // Finalmente usuario SuperAdmin
            SuperAdminUserSeeder::class,
            
            // Verificación al final
            VerifySeederResults::class,
        ]);
    }
}
