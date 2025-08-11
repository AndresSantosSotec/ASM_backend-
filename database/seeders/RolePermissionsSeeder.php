<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Role;
use App\Models\Permission;

class RolePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $mapping = [
            'Administrador' => '*',
            'Docente' => [
                '/docente%' => ['view','create','edit','export'],
                '/academico%' => ['view','create','edit','export'],
            ],
            'Estudiante' => [
                '/estudiantes%' => ['view'],
            ],
            'Administrativo' => [
                '/admin%' => ['view','create','edit','export'],
                '/inscripcion%' => ['view','create','edit','export'],
            ],
            'Finanzas' => [
                '/finanzas%' => ['view','create','edit','delete','export'],
            ],
            'Seguridad' => [
                '/seguridad%' => ['view','create','edit','delete','export'],
            ],
            'Asesor' => [
                '/gestion%' => ['view','create','edit','export'],
                '/captura%' => ['view','create','edit','export'],
                '/leads-asignados%' => ['view','create','edit','export'],
                '/seguimiento%' => ['view','create','edit','export'],
                '/importar-leads%' => ['view','create','edit','export'],
                '/correos%' => ['view','create','edit','export'],
                '/calendario%' => ['view','create','edit','export'],
            ],
            'Marketing' => [
                '/admin/plantillas-mailing%' => ['view','export'],
                '/finanzas/reportes%' => ['view','export'],
                '/admin%' => ['view','export'],
            ],
        ];

        foreach ($mapping as $roleName => $rules) {
            $role = Role::where('name', $roleName)->first();
            if (!$role) {
                continue;
            }

            $permissionIds = [];
            if ($rules === '*') {
                $permissionIds = Permission::pluck('id')->all();
            } else {
                foreach ($rules as $prefix => $actions) {
                    foreach ($actions as $action) {
                        $ids = Permission::where('name', 'like', $action.':'.$prefix)->pluck('id')->all();
                        $permissionIds = array_merge($permissionIds, $ids);
                    }
                }
                $permissionIds = array_unique($permissionIds);
            }

            foreach ($permissionIds as $pid) {
                DB::table('rolepermissions')->upsert([
                    'role_id' => $role->id,
                    'permission_id' => $pid,
                    'scope' => 'global',
                    'assigned_at' => now(),
                ], ['role_id','permission_id','scope'], ['assigned_at']);
            }
        }
    }
}
