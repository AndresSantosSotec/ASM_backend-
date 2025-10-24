<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// Eliminar todos los userpermissions que apuntan a /academico/programacion
$permIds = DB::table('permissions')
    ->where('route_path', '/academico/programacion')
    ->pluck('id')
    ->toArray();

echo "🗑️  Eliminando userpermissions con route_path='/academico/programacion'\n";
echo "=========================================================\n\n";

$deleted = DB::table('userpermissions')->whereIn('permission_id', $permIds)->delete();
echo "✅ Eliminados: {$deleted} registros\n";
