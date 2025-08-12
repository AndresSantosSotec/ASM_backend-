<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionsSeeder extends Seeder
{
    private const ACTIONS = ['view', 'create', 'edit', 'delete', 'export'];

    public function run(): void
    {
        $moduleviews = DB::table('moduleviews')->get(['id', 'view_path', 'menu', 'submenu']);
        $now = now();
        $records = [];

        foreach ($moduleviews as $mv) {
            foreach (self::ACTIONS as $action) {
                $name = $action . ':' . $mv->view_path;
                $records[] = [
                    'action' => $action,
                    'moduleview_id' => $mv->id,
                    'name' => $name,
                    'description' => sprintf('%s sobre %s/%s (%s)', $action, $mv->menu, $mv->submenu, $mv->view_path),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($records)) {
            DB::table('permissions')->upsert($records, ['name']);
        }
    }
}
