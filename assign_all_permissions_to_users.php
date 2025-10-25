<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🔐 ASIGNANDO TODOS LOS PERMISOS A USUARIOS 10 Y 41\n";
echo "===================================================\n\n";

$userIds = [10, 41];

DB::beginTransaction();
try {
    foreach ($userIds as $userId) {
        // Verificar que el usuario existe
        $user = DB::table('users')->where('id', $userId)->first();

        if (!$user) {
            echo "❌ Usuario {$userId} no existe\n";
            continue;
        }

        echo "👤 Usuario {$userId}: {$user->username} ({$user->email})\n";

        // Limpiar permisos existentes
        $deleted = DB::table('userpermissions')->where('user_id', $userId)->delete();
        echo "   🗑️  Eliminados {$deleted} permisos anteriores\n";

        // Obtener todos los moduleviews activos
        $moduleviews = DB::table('moduleviews')
            ->where('status', true)
            ->orderBy('order_num')
            ->get();

        echo "   📋 Total de vistas disponibles: " . $moduleviews->count() . "\n";

        // Insertar todos los permisos
        $inserted = 0;
        $now = now();

        foreach ($moduleviews as $mv) {
            DB::table('userpermissions')->insert([
                'user_id' => $userId,
                'moduleview_id' => $mv->id,
                'assigned_at' => $now,
                'scope' => 'self'  // ✅ Valor válido
            ]);
            $inserted++;
        }

        echo "   ✅ Insertados {$inserted} permisos\n\n";
    }

    DB::commit();

    // Verificar resultados
    echo "\n📊 VERIFICACIÓN FINAL:\n";
    echo "=====================\n\n";

    $results = DB::table('userpermissions')
        ->select('user_id', DB::raw('COUNT(*) as total'))
        ->whereIn('user_id', $userIds)
        ->groupBy('user_id')
        ->get();

    foreach ($results as $result) {
        $user = DB::table('users')->where('id', $result->user_id)->first();
        echo "👤 Usuario {$result->user_id} ({$user->username}): {$result->total} permisos\n";
    }

    echo "\n🎉 PERMISOS ASIGNADOS EXITOSAMENTE\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
