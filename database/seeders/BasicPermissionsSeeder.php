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
            // Permisos básicos del sistema adaptados a la estructura real
            [
                'action' => 'view',
                'moduleview_id' => null, // Permiso global
                'name' => 'superadmin_global_access',
                'description' => 'Acceso completo de SuperAdmin a todo el sistema'
            ],
            [
                'action' => 'create',
                'moduleview_id' => null, // Permiso global
                'name' => 'superadmin_global_create',
                'description' => 'Permiso global de creación para SuperAdmin'
            ],
            [
                'action' => 'edit',
                'moduleview_id' => null, // Permiso global
                'name' => 'superadmin_global_edit',
                'description' => 'Permiso global de edición para SuperAdmin'
            ],
            [
                'action' => 'delete',
                'moduleview_id' => null, // Permiso global
                'name' => 'superadmin_global_delete',
                'description' => 'Permiso global de eliminación para SuperAdmin'
            ],
            [
                'action' => 'export',
                'moduleview_id' => null, // Permiso global
                'name' => 'superadmin_global_export',
                'description' => 'Permiso global de exportación para SuperAdmin'
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
                        'name' => $permission['name']
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
