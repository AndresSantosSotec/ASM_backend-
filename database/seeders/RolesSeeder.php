<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;

class RolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'id' => 1,
                'name' => 'Administrador',
                'description' => 'Acceso completo al sistema',
                'is_system' => false,
                'user_count' => 13,
                'type' => 'system',
                'created_at' => '2025-03-25 18:42:41.606234',
                'updated_at' => '2025-09-12 14:19:15'
            ],
            [
                'id' => 2,
                'name' => 'Docente',
                'description' => 'Acceso a módulos académicos y de docencia',
                'is_system' => false,
                'user_count' => 4,
                'type' => 'academic',
                'created_at' => '2025-03-25 18:42:41.606234',
                'updated_at' => '2025-09-11 16:51:45'
            ],
            [
                'id' => 3,
                'name' => 'Estudiante',
                'description' => 'Acceso a módulos estudiantiles',
                'is_system' => false,
                'user_count' => 26,
                'type' => 'academic',
                'created_at' => '2025-03-25 18:42:41.606234',
                'updated_at' => '2025-09-11 21:15:37'
            ],
            [
                'id' => 4,
                'name' => 'Administrativo',
                'description' => 'Acceso a módulos administrativos',
                'is_system' => false,
                'user_count' => 2,
                'type' => 'operational',
                'created_at' => '2025-03-25 18:42:41.606234',
                'updated_at' => '2025-09-11 16:47:19'
            ],
            [
                'id' => 5,
                'name' => 'Finanzas',
                'description' => 'Acceso a módulos financieros',
                'is_system' => false,
                'user_count' => 2,
                'type' => 'financial',
                'created_at' => '2025-03-25 18:42:41.606234',
                'updated_at' => '2025-09-11 16:53:42'
            ],
            [
                'id' => 6,
                'name' => 'Seguridad',
                'description' => 'Acceso a módulos de seguridad',
                'is_system' => false,
                'user_count' => 1,
                'type' => 'security',
                'created_at' => '2025-03-25 18:42:41.606234',
                'updated_at' => '2025-05-07 03:18:57'
            ],
            [
                'id' => 7,
                'name' => 'Asesor',
                'description' => 'Acceso a Prospectos y asesores',
                'is_system' => false,
                'user_count' => 5,
                'type' => 'Prospectos',
                'created_at' => '2025-03-25 18:42:41.606234',
                'updated_at' => '2025-05-08 14:07:15'
            ],
            [
                'id' => 9,
                'name' => 'Roltest',
                'description' => 'Punto a Punto',
                'is_system' => false,
                'user_count' => 1,
                'type' => '',
                'created_at' => '2025-04-24 21:40:05',
                'updated_at' => '2025-05-07 03:19:09'
            ],
            [
                'id' => 10,
                'name' => 'Marketing',
                'description' => 'Marketing',
                'is_system' => false,
                'user_count' => 0,
                'type' => '',
                'created_at' => '2025-05-07 03:18:03',
                'updated_at' => '2025-05-07 03:18:03'
            ],
            [
                'id' => 11,
                'name' => 'SuperAdmin',
                'description' => 'Super Administrador con acceso completo a todo el sistema',
                'is_system' => true,
                'user_count' => 0,
                'type' => 'system',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['id' => $role['id']],
                $role
            );
        }
    }
}
