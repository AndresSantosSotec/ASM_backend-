<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🔍 VERIFICANDO ESTRUCTURA ACTUAL\n";
echo "================================\n\n";

echo "1️⃣ Tabla userpermissions:\n";
$userPermColumns = DB::select("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'userpermissions' ORDER BY ordinal_position");
foreach ($userPermColumns as $col) {
    echo "   {$col->column_name} ({$col->data_type})\n";
}

echo "\n2️⃣ ¿Tiene columna moduleview_id?\n";
$hasModuleviewId = false;
foreach ($userPermColumns as $col) {
    if ($col->column_name === 'moduleview_id') {
        $hasModuleviewId = true;
        break;
    }
}

if ($hasModuleviewId) {
    echo "   ✅ SÍ tiene moduleview_id\n";
} else {
    echo "   ❌ NO tiene moduleview_id (solo permission_id)\n";
    echo "\n💡 SOLUCIÓN:\n";
    echo "   Opción A: Renombrar permission_id → moduleview_id\n";
    echo "   Opción B: Agregar moduleview_id y migrar datos\n";
}

echo "\n3️⃣ Datos actuales en userpermissions:\n";
$count = DB::table('userpermissions')->count();
echo "   Total registros: {$count}\n";

if ($count > 0) {
    $sample = DB::table('userpermissions')->first();
    echo "   Ejemplo:\n";
    echo "     user_id: {$sample->user_id}\n";
    echo "     permission_id: {$sample->permission_id}\n";

    // ¿A qué apunta permission_id?
    $perm = DB::table('permissions')->where('id', $sample->permission_id)->first();
    if ($perm) {
        echo "     → Apunta a permissions.route_path: {$perm->route_path}\n";
    }
}

echo "\n\n💡 PLAN DE MIGRACIÓN:\n";
echo "====================\n";
echo "1. Agregar columna moduleview_id a userpermissions\n";
echo "2. Mapear permission_id → moduleview_id (usando route_path)\n";
echo "3. Eliminar columna permission_id\n";
echo "4. Actualizar modelo y controller\n";
