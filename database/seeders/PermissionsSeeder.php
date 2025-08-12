<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PermissionsSeeder extends Seeder
{
    // En tu tabla tienes un CHECK sobre "level" con estos valores,
    // y además guardaremos lo mismo en "action" para mantener consistencia.
    private const ACTIONS = ['view', 'create', 'edit', 'delete', 'export'];

    public function run(): void
    {
        // Trae lo que necesitas de moduleviews
        $moduleviews = DB::table('moduleviews')->get(['id', 'view_path', 'menu', 'submenu']);

        // Trae pares existentes (action + route_path) para no duplicar
        $existing = DB::table('permissions')
            ->get(['action', 'route_path'])
            ->map(fn ($r) => "{$r->action}|{$r->route_path}")
            ->flip(); // para búsquedas O(1)

        $now = now();
        $records = [];

        foreach ($moduleviews as $mv) {
            foreach (self::ACTIONS as $action) {
                $key = "{$action}|{$mv->view_path}";
                if (isset($existing[$key])) {
                    continue; // ya existe, no lo insertamos de nuevo
                }

                $records[] = [
                    'module'      => $mv->menu,                // mapea a tu columna module
                    'section'     => $mv->submenu,             // mapea a tu columna section
                    'resource'    => Str::afterLast($mv->view_path, '/'), // algo representativo
                    'action'      => $action,                  // también lo guardamos aquí
                    'level'       => $action,                  // cumple el CHECK constraint
                    'effect'      => 'allow',                  // por defecto
                    'description' => sprintf('%s sobre %s/%s (%s)', $action, $mv->menu, $mv->submenu, $mv->view_path),
                    'route_path'  => $mv->view_path,
                    'file_name'   => null,
                    'object_id'   => null,
                    'is_enabled'  => true,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
            }
        }

        if (!empty($records)) {
            DB::table('permissions')->insert($records);
        }
    }
}
