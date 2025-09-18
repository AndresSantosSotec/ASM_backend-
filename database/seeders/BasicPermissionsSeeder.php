<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Permisos;

class BasicPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $basicPermissions = [
            // Permisos básicos del sistema
            [
                'module' => 'Sistema',
                'section' => 'Dashboard',
                'resource' => 'dashboard',
                'action' => 'view',
                'effect' => 'allow',
                'description' => 'Ver dashboard principal',
                'route_path' => '/dashboard',
                'file_name' => 'dashboard',
                'object_id' => null,
                'is_enabled' => true,
                'level' => 'global'
            ],
            [
                'module' => 'Sistema',
                'section' => 'Usuarios',
                'resource' => 'users',
                'action' => 'view',
                'effect' => 'allow',
                'description' => 'Ver usuarios',
                'route_path' => '/usuarios',
                'file_name' => 'users',
                'object_id' => null,
                'is_enabled' => true,
                'level' => 'global'
            ],
            [
                'module' => 'Sistema',
                'section' => 'Usuarios',
                'resource' => 'users',
                'action' => 'create',
                'effect' => 'allow',
                'description' => 'Crear usuarios',
                'route_path' => '/usuarios/crear',
                'file_name' => 'users',
                'object_id' => null,
                'is_enabled' => true,
                'level' => 'global'
            ],
            [
                'module' => 'Sistema',
                'section' => 'Usuarios',
                'resource' => 'users',
                'action' => 'edit',
                'effect' => 'allow',
                'description' => 'Editar usuarios',
                'route_path' => '/usuarios/editar',
                'file_name' => 'users',
                'object_id' => null,
                'is_enabled' => true,
                'level' => 'global'
            ],
            [
                'module' => 'Sistema',
                'section' => 'Usuarios',
                'resource' => 'users',
                'action' => 'delete',
                'effect' => 'allow',
                'description' => 'Eliminar usuarios',
                'route_path' => '/usuarios/eliminar',
                'file_name' => 'users',
                'object_id' => null,
                'is_enabled' => true,
                'level' => 'global'
            ],
            [
                'module' => 'Sistema',
                'section' => 'Roles',
                'resource' => 'roles',
                'action' => 'view',
                'effect' => 'allow',
                'description' => 'Ver roles',
                'route_path' => '/roles',
                'file_name' => 'roles',
                'object_id' => null,
                'is_enabled' => true,
                'level' => 'global'
            ],
            [
                'module' => 'Sistema',
                'section' => 'Permisos',
                'resource' => 'permissions',
                'action' => 'view',
                'effect' => 'allow',
                'description' => 'Ver permisos',
                'route_path' => '/permisos',
                'file_name' => 'permissions',
                'object_id' => null,
                'is_enabled' => true,
                'level' => 'global'
            ],
            [
                'module' => 'Sistema',
                'section' => 'Configuración',
                'resource' => 'settings',
                'action' => 'view',
                'effect' => 'allow',
                'description' => 'Ver configuración del sistema',
                'route_path' => '/configuracion',
                'file_name' => 'settings',
                'object_id' => null,
                'is_enabled' => true,
                'level' => 'global'
            ],
            [
                'module' => 'Sistema',
                'section' => 'Configuración',
                'resource' => 'settings',
                'action' => 'edit',
                'effect' => 'allow',
                'description' => 'Editar configuración del sistema',
                'route_path' => '/configuracion/editar',
                'file_name' => 'settings',
                'object_id' => null,
                'is_enabled' => true,
                'level' => 'global'
            ],
            [
                'module' => 'Sistema',
                'section' => 'SuperAdmin',
                'resource' => 'superadmin',
                'action' => 'all',
                'effect' => 'allow',
                'description' => 'Acceso completo de SuperAdmin',
                'route_path' => '/*',
                'file_name' => 'superadmin',
                'object_id' => null,
                'is_enabled' => true,
                'level' => 'global'
            ]
        ];

        foreach ($basicPermissions as $permission) {
            Permisos::updateOrCreate(
                [
                    'module' => $permission['module'],
                    'section' => $permission['section'],
                    'resource' => $permission['resource'],
                    'action' => $permission['action']
                ],
                $permission
            );
        }

        $this->command->info('Permisos básicos creados: ' . count($basicPermissions) . ' permisos');
    }
}
