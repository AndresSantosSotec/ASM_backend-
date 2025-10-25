<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "📋 DETALLE DE PERMISOS ASIGNADOS\n";
echo "================================\n\n";

$userIds = [10, 41];

foreach ($userIds as $userId) {
    $user = DB::table('users')->where('id', $userId)->first();

    echo "👤 USUARIO {$userId}: {$user->username} ({$user->email})\n";
    echo str_repeat("=", 70) . "\n\n";

    $permissions = DB::table('userpermissions as up')
        ->join('moduleviews as mv', 'mv.id', '=', 'up.moduleview_id')
        ->join('modules as m', 'm.id', '=', 'mv.module_id')
        ->where('up.user_id', $userId)
        ->select('m.name as module_name', 'mv.menu', 'mv.submenu', 'mv.view_path', 'mv.icon', 'up.scope')
        ->orderBy('mv.order_num')
        ->get();

    $currentModule = '';
    foreach ($permissions as $perm) {
        if ($currentModule !== $perm->module_name) {
            $currentModule = $perm->module_name;
            echo "\n📦 MÓDULO: {$currentModule}\n";
            echo str_repeat("-", 70) . "\n";
        }

        echo "  ✅ {$perm->menu} > {$perm->submenu}\n";
        echo "     🔗 {$perm->view_path}\n";
        echo "     🔒 Scope: {$perm->scope}\n\n";
    }

    echo "\n📊 Total: " . $permissions->count() . " permisos asignados\n";
    echo "\n" . str_repeat("=", 70) . "\n\n\n";
}

// Resumen por módulo
echo "📊 RESUMEN POR MÓDULO\n";
echo "=====================\n\n";

foreach ($userIds as $userId) {
    $user = DB::table('users')->where('id', $userId)->first();
    echo "👤 {$user->username}:\n";

    $summary = DB::table('userpermissions as up')
        ->join('moduleviews as mv', 'mv.id', '=', 'up.moduleview_id')
        ->join('modules as m', 'm.id', '=', 'mv.module_id')
        ->where('up.user_id', $userId)
        ->select('m.name as module_name', DB::raw('COUNT(*) as total'))
        ->groupBy('m.name')
        ->orderBy('total', 'DESC')
        ->get();

    foreach ($summary as $s) {
        echo "   📦 {$s->module_name}: {$s->total} vistas\n";
    }
    echo "\n";
}
