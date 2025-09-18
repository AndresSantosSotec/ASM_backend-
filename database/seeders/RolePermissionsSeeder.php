<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Role;

class RolePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $mapping = [
            'Administrador' => '*',
            'SuperAdmin' => '*',
            'Docente' => [
                '/docente%'   => ['view','create','edit','export'],
                '/academico%' => ['view','create','edit','export'],
            ],
            'Estudiante' => [
                '/estudiantes%' => ['view'],
            ],
            'Administrativo' => [
                '/admin%'       => ['view','create','edit','export'],
                '/inscripcion%' => ['view','create','edit','export'],
            ],
            'Finanzas' => [
                '/finanzas%' => ['view','create','edit','delete','export'],
            ],
            'Seguridad' => [
                '/seguridad%' => ['view','create','edit','delete','export'],
            ],
            'Asesor' => [
                '/gestion%'        => ['view','create','edit','export'],
                '/captura%'        => ['view','create','edit','export'],
                '/leads-asignados%'=> ['view','create','edit','export'],
                '/seguimiento%'    => ['view','create','edit','export'],
                '/importar-leads%' => ['view','create','edit','export'],
                '/correos%'        => ['view','create','edit','export'],
                '/calendario%'     => ['view','create','edit','export'],
            ],
            'Marketing' => [
                '/admin/plantillas-mailing%' => ['view','export'],
                '/finanzas/reportes%'        => ['view','export'],
                '/admin%'                    => ['view','export'],
            ],
        ];

        foreach ($mapping as $roleName => $rules) {
            $role = Role::where('name', $roleName)->first();
            if (!$role) {
                continue;
            }

            // Si '*' → todos los permisos (incluyendo globales)
            if ($rules === '*') {
                $permissionIds = DB::table('permissions')
                    ->pluck('id')
                    ->all();
                
                $this->command->info("Asignando {$role->name}: " . count($permissionIds) . " permisos (todos)");
            } else {
                $permissionIds = [];
                foreach ($rules as $routePrefixLike => $actions) {
                    foreach ($actions as $action) {
                        // Filtrar por action y moduleview que coincida con la ruta
                        $ids = DB::table('permissions')
                            ->join('moduleviews', 'permissions.moduleview_id', '=', 'moduleviews.id')
                            ->where('permissions.action', $action)
                            ->where('moduleviews.view_path', 'like', $routePrefixLike)
                            ->pluck('permissions.id')
                            ->all();

                        $permissionIds = array_merge($permissionIds, $ids);
                    }
                }
                $permissionIds = array_values(array_unique($permissionIds));
                
                $this->command->info("Asignando {$role->name}: " . count($permissionIds) . " permisos específicos");
            }

            // Upsert en tabla pivote rolepermissions
            foreach ($permissionIds as $pid) {
                DB::table('rolepermissions')->upsert(
                    [[
                        'role_id'      => $role->id,
                        'permission_id'=> $pid,
                        'scope'        => 'global',
                        'assigned_at'  => now(),
                    ]],
                    ['role_id','permission_id','scope'], // clave de conflicto
                    ['assigned_at']                      // columnas a actualizar
                );
            }
        }
    }
}
