<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🔍 ANÁLISIS: Lógica de Permisos por Usuario vs Permisos por Rol\n";
echo "================================================================\n\n";

// 1. Estructura de userpermissions
echo "📋 Estructura de userpermissions:\n";
$userPermColumns = DB::select("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'userpermissions' ORDER BY ordinal_position");
foreach ($userPermColumns as $col) {
    echo "  - {$col->column_name} ({$col->data_type})\n";
}
echo "\n";

// 2. Verificar qué referencia permission_id
echo "🔗 ¿Qué es permission_id en userpermissions?\n";
$sample = DB::table('userpermissions')->first();
if ($sample) {
    echo "  user_id: {$sample->user_id}\n";
    echo "  permission_id: {$sample->permission_id}\n";

    // ¿Es de la tabla permissions (permisos por rol)?
    $perm = DB::table('permissions')->where('id', $sample->permission_id)->first();
    if ($perm) {
        echo "  ❌ PROBLEMA: Referencia tabla 'permissions' (permisos de ROL)\n";
        echo "     route_path: {$perm->route_path}\n";
        echo "     action: {$perm->action}\n";
    }
}
echo "\n";

// 3. Propuesta de solución
echo "💡 SOLUCIÓN PROPUESTA:\n";
echo "=====================\n\n";

echo "OPCIÓN 1: Cambiar userpermissions para usar moduleview_id directamente\n";
echo "-----------------------------------------------------------------------\n";
echo "• userpermissions.permission_id → userpermissions.moduleview_id\n";
echo "• Guardar directamente el ID de moduleviews (vistas del sistema)\n";
echo "• NO usar la tabla permissions (que es para roles)\n";
echo "• Simplifica la lógica: Usuario → ModuleView directo\n\n";

echo "OPCIÓN 2: Mantener estructura actual pero clarificar\n";
echo "-----------------------------------------------------\n";
echo "• Crear permisos 'view' en permissions para CADA moduleview\n";
echo "• Mantener userpermissions → permissions → moduleviews\n";
echo "• Más complejo pero mantiene consistencia con roles\n\n";

echo "🎯 RECOMENDACIÓN: OPCIÓN 1\n";
echo "==========================\n";
echo "Razón: Los permisos de USUARIO son más simples:\n";
echo "  - Usuario puede ver Vista X (sí/no)\n";
echo "  - No necesita actions (view/create/edit/delete)\n";
echo "  - Eso es para ROLES, no usuarios individuales\n\n";

echo "📊 ¿Cuántos moduleviews hay?\n";
$mvCount = DB::table('moduleviews')->count();
echo "  Total: {$mvCount}\n\n";

echo "📊 ¿Cuántos permisos 'view' existen?\n";
$viewPermCount = DB::table('permissions')->where('action', 'view')->count();
echo "  Total: {$viewPermCount}\n";
echo "  Faltantes: " . ($mvCount - $viewPermCount) . "\n\n";

if ($mvCount > $viewPermCount) {
    echo "❌ HAY MODULEVIEWS SIN PERMISO 'view' → Por eso falla\n";
}
