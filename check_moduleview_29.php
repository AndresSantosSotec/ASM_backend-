<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🔍 Buscando ModuleView ID 29 específicamente\n";
echo "============================================\n\n";

$mv = DB::table('moduleviews')->where('id', 29)->first();
if ($mv) {
    echo "✅ ModuleView ID 29 existe:\n";
    echo "   view_path: {$mv->view_path}\n";
    echo "   menu: {$mv->menu}\n";
    echo "   submenu: {$mv->submenu}\n\n";

    echo "🔍 Buscando permiso 'view' para route_path = '{$mv->view_path}':\n";
    $perm = DB::table('permissions')
        ->where('route_path', $mv->view_path)
        ->where('action', 'view')
        ->first();

    if ($perm) {
        echo "   ✅ Permiso encontrado:\n";
        echo "      ID: {$perm->id}\n";
        echo "      Enabled: " . ($perm->is_enabled ? 'true' : 'false') . "\n";
        echo "      Name: {$perm->name}\n";
    } else {
        echo "   ❌ NO EXISTE permiso 'view'\n\n";

        echo "   Permisos existentes para esta ruta:\n";
        $allPerms = DB::table('permissions')
            ->where('route_path', $mv->view_path)
            ->get();

        if ($allPerms->count() > 0) {
            foreach ($allPerms as $p) {
                echo "      - action: {$p->action}, enabled: " . ($p->is_enabled ? 'true' : 'false') . "\n";
            }
        } else {
            echo "      (ninguno)\n";
        }

        echo "\n   💡 SQL para crear el permiso faltante:\n";
        echo "   =====================================\n";
        $name = addslashes("Ver {$mv->submenu}");
        $desc = addslashes("Permiso para visualizar {$mv->submenu}");
        echo "   INSERT INTO permissions (route_path, action, name, description, is_enabled, created_at, updated_at)\n";
        echo "   VALUES ('{$mv->view_path}', 'view', '{$name}', '{$desc}', true, NOW(), NOW());\n";
    }
} else {
    echo "❌ ModuleView ID 29 NO EXISTE\n";
}
