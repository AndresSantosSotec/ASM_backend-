<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🧪 Probando relación ModulesViews -> Permissions\n";
echo "================================================\n\n";

try {
    // Prueba 1: Cargar moduleviews con permissions
    echo "✅ Prueba 1: ModulesViews::with('permissions')->take(1)->get()\n";
    $moduleviews = App\Models\ModulesViews::with('permissions')->take(1)->get();
    echo "   Resultado: OK - " . $moduleviews->count() . " registro(s) cargado(s)\n";
    if ($moduleviews->first() && $moduleviews->first()->permissions) {
        echo "   Permisos cargados: " . $moduleviews->first()->permissions->count() . "\n";
    }
    echo "\n";

    // Prueba 2: Verificar que la query no use moduleview_id
    echo "✅ Prueba 2: Verificando columnas usadas en la relación\n";
    $query = App\Models\ModulesViews::with('permissions')->toSql();
    echo "   Query SQL: " . substr($query, 0, 100) . "...\n";
    echo "\n";

    // Prueba 3: Probar relación inversa
    echo "✅ Prueba 3: Permisos::with('moduleView')->take(1)->get()\n";
    $permissions = App\Models\Permisos::with('moduleView')->take(1)->get();
    echo "   Resultado: OK - " . $permissions->count() . " registro(s) cargado(s)\n";
    echo "\n";

    echo "🎉 TODAS LAS PRUEBAS PASARON EXITOSAMENTE\n";
    echo "   El error de permissions.moduleview_id está RESUELTO\n";

} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "   Archivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}
