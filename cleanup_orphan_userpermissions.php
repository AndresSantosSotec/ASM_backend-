<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🔍 Analizando registros problemáticos\n";
echo "=====================================\n\n";

// Encontrar los que fallan
$userPerms = DB::table('userpermissions')->get();
$orphans = [];

foreach ($userPerms as $up) {
    $perm = DB::table('permissions')->where('id', $up->permission_id)->first();

    if (!$perm) {
        $orphans[] = [
            'id' => $up->id,
            'user_id' => $up->user_id,
            'permission_id' => $up->permission_id,
            'reason' => 'permission_id no existe'
        ];
        continue;
    }

    $mv = DB::table('moduleviews')->where('view_path', $perm->route_path)->first();

    if (!$mv) {
        $orphans[] = [
            'id' => $up->id,
            'user_id' => $up->user_id,
            'permission_id' => $up->permission_id,
            'route_path' => $perm->route_path,
            'reason' => 'route_path no existe en moduleviews'
        ];
    }
}

if (empty($orphans)) {
    echo "✅ No hay registros huérfanos\n";
    exit(0);
}

echo "❌ Registros huérfanos: " . count($orphans) . "\n\n";

// Agrupar por ruta
$byRoute = [];
foreach ($orphans as $o) {
    $route = $o['route_path'] ?? 'N/A';
    if (!isset($byRoute[$route])) {
        $byRoute[$route] = [];
    }
    $byRoute[$route][] = $o;
}

foreach ($byRoute as $route => $items) {
    echo "📍 {$route} → " . count($items) . " registros\n";
    foreach (array_slice($items, 0, 3) as $item) {
        echo "   - userpermissions.id={$item['id']}, user_id={$item['user_id']}\n";
    }
    if (count($items) > 3) {
        echo "   ... y " . (count($items) - 3) . " más\n";
    }
    echo "\n";
}

echo "\n💡 OPCIONES:\n";
echo "============\n";
echo "A) Eliminar estos registros huérfanos (RECOMENDADO)\n";
echo "B) Crear moduleviews para las rutas faltantes\n";
echo "C) Mapear a otro moduleview existente\n\n";

echo "¿Quieres eliminar los " . count($orphans) . " registros huérfanos? (y/n): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
fclose($handle);

if (trim($line) === 'y' || trim($line) === 'Y') {
    echo "\n🗑️  Eliminando registros huérfanos...\n";
    $ids = array_column($orphans, 'id');
    $deleted = DB::table('userpermissions')->whereIn('id', $ids)->delete();
    echo "✅ Eliminados: {$deleted} registros\n";
    echo "\n✅ Ahora puedes ejecutar la migración nuevamente\n";
} else {
    echo "\n❌ Operación cancelada\n";
}
