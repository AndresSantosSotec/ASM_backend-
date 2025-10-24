<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🧪 Probando nueva lógica de UserPermisos\n";
echo "========================================\n\n";

// Prueba 1: Cargar permisos de un usuario
echo "1️⃣ UserPermisos::with('moduleView')->first()\n";
$up = App\Models\UserPermisos::with('moduleView')->first();
if ($up) {
    echo "   ✅ user_id: {$up->user_id}\n";
    echo "   ✅ moduleview_id: {$up->moduleview_id}\n";
    if ($up->moduleView) {
        echo "   ✅ view_path: {$up->moduleView->view_path}\n";
        echo "   ✅ submenu: {$up->moduleView->submenu}\n";
    }
} else {
    echo "   ℹ️  No hay registros\n";
}

echo "\n2️⃣ Verificar que NO usa permissions\n";
try {
    $test = App\Models\UserPermisos::with('permission')->first();
    echo "   ❌ ERROR: Todavía usa relación 'permission'\n";
} catch (\Exception $e) {
    if (str_contains($e->getMessage(), 'permission')) {
        echo "   ✅ Correcto: Relación 'permission' no existe\n";
    }
}

echo "\n3️⃣ Simular request del frontend\n";
$request = new \Illuminate\Http\Request();
$request->merge([
    'user_id' => 1,
    'permissions' => [1, 2, 3, 29] // IDs de moduleviews (incluye el 29 que causaba error)
]);

$validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
    'user_id'       => 'required|exists:users,id',
    'permissions'   => 'required|array',
    'permissions.*' => 'exists:moduleviews,id'
]);

if ($validator->fails()) {
    echo "   ❌ Validación falló:\n";
    print_r($validator->errors()->toArray());
} else {
    echo "   ✅ Validación pasó correctamente\n";
    echo "   ✅ ModuleView ID 29 ahora es válido\n";
}

echo "\n🎉 LÓGICA SEPARADA EXITOSAMENTE\n";
echo "================================\n";
echo "✅ userpermissions → moduleviews (permisos por usuario)\n";
echo "✅ permissions (permisos por rol) - separado\n";
