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
            BasicPermissionsSeeder::class,
            CoursesSeeder::class,
            ModulesSeeder::class,
            ModuleViewsSeeder::class,
            PermissionsSeeder::class,
            PrecioProgramasSeeder::class,
            ProgramasSeeder::class,
            RolePermissionsConfigSeeder::class,
            RolePermissionsSeeder::class,
            RolesSeeder::class,
            SuperAdminUserSeeder::class,
            VerifySeederResults::class,
        ]);
    }
}
