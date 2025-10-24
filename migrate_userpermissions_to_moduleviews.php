<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "🔄 MIGRACIÓN: Separar lógica de permisos\n";
echo "========================================\n";
echo "userpermissions.permission_id → userpermissions.moduleview_id\n\n";

DB::beginTransaction();
try {
    // PASO 1: Agregar columna moduleview_id
    echo "1️⃣ Agregando columna moduleview_id...\n";
    if (!Schema::hasColumn('userpermissions', 'moduleview_id')) {
        DB::statement('ALTER TABLE userpermissions ADD COLUMN moduleview_id INTEGER');
        echo "   ✅ Columna agregada\n";
    } else {
        echo "   ℹ️  Columna ya existe\n";
    }

    // PASO 2: Mapear permission_id → moduleview_id
    echo "\n2️⃣ Mapeando permission_id → moduleview_id...\n";

    $userPerms = DB::table('userpermissions')->get();
    $updated = 0;
    $failed = [];

    foreach ($userPerms as $up) {
        // Obtener el permission
        $perm = DB::table('permissions')->where('id', $up->permission_id)->first();

        if (!$perm) {
            $failed[] = "ID {$up->id}: permission_id {$up->permission_id} no existe";
            continue;
        }

        // Buscar moduleview con ese route_path
        $mv = DB::table('moduleviews')->where('view_path', $perm->route_path)->first();

        if (!$mv) {
            $failed[] = "ID {$up->id}: route_path '{$perm->route_path}' no existe en moduleviews";
            continue;
        }

        // Actualizar
        DB::table('userpermissions')
            ->where('id', $up->id)
            ->update(['moduleview_id' => $mv->id]);

        $updated++;
    }

    echo "   ✅ Actualizados: {$updated}\n";

    if (!empty($failed)) {
        echo "   ⚠️  Fallidos: " . count($failed) . "\n";
        foreach (array_slice($failed, 0, 5) as $f) {
            echo "      - {$f}\n";
        }
        if (count($failed) > 5) {
            echo "      ... y " . (count($failed) - 5) . " más\n";
        }
    }

    // PASO 3: Verificar que todos tienen moduleview_id
    echo "\n3️⃣ Verificando integridad...\n";
    $nullCount = DB::table('userpermissions')->whereNull('moduleview_id')->count();

    if ($nullCount > 0) {
        echo "   ❌ Hay {$nullCount} registros sin moduleview_id\n";
        echo "   🛑 ROLLBACK - No se puede continuar\n";
        DB::rollBack();
        exit(1);
    }

    echo "   ✅ Todos los registros tienen moduleview_id\n";

    // PASO 4: Eliminar columna permission_id
    echo "\n4️⃣ Eliminando columna permission_id...\n";
    DB::statement('ALTER TABLE userpermissions DROP COLUMN permission_id');
    echo "   ✅ Columna eliminada\n";

    // PASO 5: Agregar foreign key
    echo "\n5️⃣ Agregando foreign key...\n";
    DB::statement('
        ALTER TABLE userpermissions
        ADD CONSTRAINT fk_userpermissions_moduleview
        FOREIGN KEY (moduleview_id)
        REFERENCES moduleviews(id)
        ON DELETE CASCADE
    ');
    echo "   ✅ Foreign key agregada\n";

    DB::commit();

    echo "\n🎉 MIGRACIÓN COMPLETADA EXITOSAMENTE\n";
    echo "====================================\n";
    echo "✅ userpermissions ahora usa moduleview_id\n";
    echo "✅ Lógica separada de permissions (roles)\n";
    echo "✅ {$updated} registros migrados\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
