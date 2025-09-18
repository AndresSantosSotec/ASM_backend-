<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Permisos;

class BasicPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Verificar si la tabla permissions existe y tiene datos
        if (!Schema::hasTable('permissions')) {
            $this->command->warn('La tabla permissions no existe. Saltando BasicPermissionsSeeder.');
            return;
        }

        // Verificar si ya hay permisos en la tabla
        $existingPermissions = DB::table('permissions')->count();
        if ($existingPermissions == 0) {
            $this->command->warn('La tabla permissions está vacía. Se recomienda ejecutar PermissionsSeeder primero.');
            return;
        }

        // Obtener las columnas de la tabla para adaptar la inserción
        $columns = Schema::getColumnListing('permissions');
        $this->command->info('Columnas disponibles en permissions: ' . implode(', ', $columns));

        $basicPermissions = [
            // Permisos básicos del sistema adaptados a la estructura existente
            [
                'module' => 'Sistema',
                'section' => 'SuperAdmin',
                'resource' => 'superadmin',
                'action' => 'all',
                'level' => 'all',
                'effect' => 'allow',
                'description' => 'Acceso completo de SuperAdmin a todo el sistema',
                'route_path' => '/*',
                'file_name' => null,
                'object_id' => null,
                'is_enabled' => true
            ],
            [
                'module' => 'Sistema',
                'section' => 'Dashboard',
                'resource' => 'dashboard',
                'action' => 'view',
                'level' => 'view',
                'effect' => 'allow',
                'description' => 'Ver dashboard principal del sistema',
                'route_path' => '/dashboard',
                'file_name' => null,
                'object_id' => null,
                'is_enabled' => true
            ]
        ];

        foreach ($basicPermissions as $permission) {
            try {
                // Filtrar solo las columnas que existen en la tabla
                $filteredPermission = array_intersect_key($permission, array_flip($columns));
                
                // Agregar timestamps si existen las columnas
                if (in_array('created_at', $columns)) {
                    $filteredPermission['created_at'] = now();
                }
                if (in_array('updated_at', $columns)) {
                    $filteredPermission['updated_at'] = now();
                }

                DB::table('permissions')->updateOrInsert(
                    [
                        'route_path' => $permission['route_path'],
                        'action' => $permission['action']
                    ],
                    $filteredPermission
                );

                $this->command->info("✓ Permiso creado: {$permission['description']}");

            } catch (\Exception $e) {
                $this->command->error("✗ Error al crear permiso '{$permission['description']}': " . $e->getMessage());
            }
        }

        $this->command->info('BasicPermissionsSeeder completado.');
    }
}
