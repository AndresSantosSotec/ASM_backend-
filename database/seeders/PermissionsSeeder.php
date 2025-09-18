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

        // Trae pares existentes (action + moduleview_id) para no duplicar
        $existing = DB::table('permissions')
            ->get(['action', 'moduleview_id'])
            ->map(fn ($r) => "{$r->action}|{$r->moduleview_id}")
            ->flip(); // para búsquedas O(1)

        $now = now();
        $records = [];

        foreach ($moduleviews as $mv) {
            foreach (self::ACTIONS as $action) {
                $key = "{$action}|{$mv->id}";
                if (isset($existing[$key])) {
                    continue; // ya existe, no lo insertamos de nuevo
                }

                $records[] = [
                    'action'        => $action,
                    'moduleview_id' => $mv->id,
                    'name'          => sprintf('%s_%s_%d', $action, Str::slug($mv->menu), $mv->id),
                    'description'   => sprintf('%s sobre %s/%s (%s)', ucfirst($action), $mv->menu, $mv->submenu, $mv->view_path),
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];
            }
        }

        if (!empty($records)) {
            DB::table('permissions')->insert($records);
        }
    }
}
