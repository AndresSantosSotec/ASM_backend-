<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🔍 Encontrando TODOS los ModuleViews sin permisos 'view'\n";
echo "=======================================================\n\n";

$moduleviews = DB::table('moduleviews')->orderBy('id')->get();
$missing = [];

foreach ($moduleviews as $mv) {
    $viewPerm = DB::table('permissions')
        ->where('route_path', $mv->view_path)
        ->where('action', 'view')
        ->first();

    if (!$viewPerm) {
        $missing[] = $mv;
        echo "❌ ID {$mv->id}: {$mv->view_path} ({$mv->submenu})\n";
    }
}

if (empty($missing)) {
    echo "✅ Todos los ModuleViews tienen permisos 'view'\n";
    exit(0);
}

echo "\n📊 Total faltantes: " . count($missing) . "\n\n";

echo "💡 Creando permisos faltantes...\n";
echo "================================\n\n";

DB::beginTransaction();
try {
    foreach ($missing as $mv) {
        $name = "Ver {$mv->submenu}";
        $description = "Permiso para visualizar {$mv->submenu}";

        $id = DB::table('permissions')->insertGetId([
            'route_path' => $mv->view_path,
            'action' => 'view',
            'name' => $name,
            'description' => $description,
            'is_enabled' => true,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        echo "✅ Creado permiso ID {$id} para ModuleView {$mv->id} ({$mv->view_path})\n";
    }

    DB::commit();
    echo "\n🎉 ¡Todos los permisos 'view' creados exitosamente!\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
