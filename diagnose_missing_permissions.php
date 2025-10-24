<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🔍 Diagnóstico: Permisos faltantes para ModuleViews\n";
echo "====================================================\n\n";

// Obtener todos los moduleviews
$moduleviews = App\Models\ModulesViews::orderBy('id')->get();

echo "📊 Total de ModuleViews: " . $moduleviews->count() . "\n\n";

$missingViewPermissions = [];

foreach ($moduleviews as $mv) {
    // Buscar permiso 'view' para esta vista
    $viewPermission = DB::table('permissions')
        ->where('route_path', $mv->view_path)
        ->where('action', 'view')
        ->first();

    if (!$viewPermission) {
        $missingViewPermissions[] = [
            'id' => $mv->id,
            'view_path' => $mv->view_path,
            'menu' => $mv->menu,
            'submenu' => $mv->submenu
        ];
    } elseif (!$viewPermission->is_enabled) {
        echo "⚠️  ModuleView ID {$mv->id} ({$mv->view_path}) - Permiso DESHABILITADO\n";
    }
}

if (empty($missingViewPermissions)) {
    echo "✅ Todos los ModuleViews tienen permisos 'view' configurados\n";
} else {
    echo "❌ ModuleViews SIN permiso 'view' configurado:\n";
    echo "==============================================\n\n";

    foreach ($missingViewPermissions as $missing) {
        echo "ID: {$missing['id']}\n";
        echo "  View Path: {$missing['view_path']}\n";
        echo "  Menú: {$missing['menu']} > {$missing['submenu']}\n";
        echo "\n";
    }

    echo "\n📝 Total faltantes: " . count($missingViewPermissions) . "\n\n";

    // Generar SQL para crear los permisos faltantes
    echo "💡 SQL para crear los permisos faltantes:\n";
    echo "=========================================\n\n";

    foreach ($missingViewPermissions as $missing) {
        $viewPath = addslashes($missing['view_path']);
        $name = "Ver " . $missing['submenu'];
        $description = "Permiso para ver " . $missing['submenu'];

        echo "INSERT INTO permissions (route_path, action, name, description, is_enabled, created_at, updated_at)\n";
        echo "VALUES ('{$viewPath}', 'view', '{$name}', '{$description}', true, NOW(), NOW());\n\n";
    }
}

// Verificar específicamente el moduleview_id 29
echo "\n🎯 Verificación específica de ModuleView ID 29:\n";
echo "==============================================\n";
$mv29 = App\Models\ModulesViews::find(29);
if ($mv29) {
    echo "View Path: {$mv29->view_path}\n";
    echo "Menú: {$mv29->menu} > {$mv29->submenu}\n";

    $perm = DB::table('permissions')
        ->where('route_path', $mv29->view_path)
        ->where('action', 'view')
        ->first();

    if ($perm) {
        echo "Permiso ID: {$perm->id}\n";
        echo "Enabled: " . ($perm->is_enabled ? 'Sí' : 'No') . "\n";
    } else {
        echo "❌ NO EXISTE permiso 'view' para esta ruta\n";
    }
} else {
    echo "❌ ModuleView 29 no existe\n";
}
