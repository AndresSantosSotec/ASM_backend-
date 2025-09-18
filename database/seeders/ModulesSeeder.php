<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Modules;

class ModulesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modules = [
            [
                'id' => 2,
                'name' => 'Prospectos Y Asesores',
                'description' => 'Modulo de Prospectos Y Asesores',
                'status' => true,
                'view_count' => 19,
                'icon' => 'Users',
                'order_num' => 1
            ],
            [
                'id' => 3,
                'name' => 'Inscripcion',
                'description' => 'Modulo de Inscripcion',
                'status' => true,
                'view_count' => 8,
                'icon' => 'FileText',
                'order_num' => 2
            ],
            [
                'id' => 4,
                'name' => 'Academico',
                'description' => 'Modulo de Academico',
                'status' => true,
                'view_count' => 9,
                'icon' => 'BookOpen',
                'order_num' => 3
            ],
            [
                'id' => 5,
                'name' => 'Docentes',
                'description' => 'Modulo de Docentes',
                'status' => true,
                'view_count' => 10,
                'icon' => 'GraduationCapIcon',
                'order_num' => 4
            ],
            [
                'id' => 6,
                'name' => 'Estudiantes',
                'description' => 'Modulo de Estudiantes',
                'status' => true,
                'view_count' => 8,
                'icon' => 'Users',
                'order_num' => 5
            ],
            [
                'id' => 7,
                'name' => 'Finanzas y Pagos',
                'description' => 'Modulo de Finanzas y Pagos',
                'status' => true,
                'view_count' => 7,
                'icon' => 'DollarSign',
                'order_num' => 6
            ],
            [
                'id' => 8,
                'name' => 'Administración',
                'description' => 'Modulo de Administración General',
                'status' => true,
                'view_count' => 6,
                'icon' => 'Settings',
                'order_num' => 7
            ],
            [
                'id' => 9,
                'name' => 'Seguridad',
                'description' => 'Modulo de control de Acceso y seguridad',
                'status' => true,
                'view_count' => 8,
                'icon' => 'Shield',
                'order_num' => 8
            ]
        ];

        foreach ($modules as $module) {
            Modules::updateOrCreate(
                ['id' => $module['id']],
                $module
            );
        }
    }
}
