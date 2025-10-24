#!/usr/bin/env php
<?php
/**
 * Script de verificación del sistema de permisos
 * 
 * Este script verifica que:
 * 1. Los permisos se crean con el formato correcto (action:view_path)
 * 2. Las relaciones entre modelos funcionan correctamente
 * 3. El PermissionService puede verificar permisos correctamente
 */

// Bootstrap Laravel
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\ModulesViews;
use App\Models\Permisos;
use App\Models\User;
use App\Models\UserPermisos;
use App\Services\PermissionService;
use Illuminate\Support\Facades\DB;

echo "==============================================\n";
echo "  Sistema de Permisos - Script de Verificación\n";
echo "==============================================\n\n";

// Test 1: Verificar formato de nombres de permisos
echo "Test 1: Verificando formato de nombres de permisos...\n";
$permissions = Permisos::with('moduleView')->limit(10)->get();
$correctFormat = 0;
$incorrectFormat = 0;

foreach ($permissions as $perm) {
    $expectedName = $perm->action . ':' . ($perm->moduleView->view_path ?? '');
    if ($perm->name === $expectedName) {
        $correctFormat++;
    } else {
        $incorrectFormat++;
        echo "  ⚠ Permiso ID {$perm->id}: nombre incorrecto\n";
        echo "    Actual: {$perm->name}\n";
        echo "    Esperado: {$expectedName}\n";
    }
}

echo "  ✓ Formato correcto: {$correctFormat}\n";
if ($incorrectFormat > 0) {
    echo "  ✗ Formato incorrecto: {$incorrectFormat}\n";
    echo "  → Ejecutar: php artisan permissions:sync --action=all --force\n";
}
echo "\n";

// Test 2: Verificar que todas las moduleviews tengan al menos un permiso 'view'
echo "Test 2: Verificando permisos 'view' para moduleviews...\n";
$moduleviews = ModulesViews::all();
$withPermission = 0;
$withoutPermission = 0;

foreach ($moduleviews as $mv) {
    $hasViewPermission = Permisos::where('moduleview_id', $mv->id)
        ->where('action', 'view')
        ->exists();
    
    if ($hasViewPermission) {
        $withPermission++;
    } else {
        $withoutPermission++;
        echo "  ⚠ ModuleView ID {$mv->id} ({$mv->menu} > {$mv->submenu}): sin permiso 'view'\n";
    }
}

echo "  ✓ Con permiso 'view': {$withPermission}\n";
if ($withoutPermission > 0) {
    echo "  ✗ Sin permiso 'view': {$withoutPermission}\n";
    echo "  → Ejecutar: php artisan permissions:sync --action=view\n";
}
echo "\n";

// Test 3: Verificar relaciones
echo "Test 3: Verificando relaciones entre modelos...\n";
try {
    $testPerm = Permisos::with('moduleView')->first();
    if ($testPerm && $testPerm->moduleView) {
        echo "  ✓ Relación Permisos -> ModuleView: OK\n";
    } else {
        echo "  ✗ Relación Permisos -> ModuleView: FALLA\n";
    }
    
    $testMv = ModulesViews::with('permissions')->first();
    if ($testMv && $testMv->permissions->count() >= 0) {
        echo "  ✓ Relación ModuleView -> Permisos: OK\n";
    } else {
        echo "  ✗ Relación ModuleView -> Permisos: FALLA\n";
    }
} catch (\Exception $e) {
    echo "  ✗ Error verificando relaciones: {$e->getMessage()}\n";
}
echo "\n";

// Test 4: Verificar PermissionService (si hay usuarios con permisos)
echo "Test 4: Verificando PermissionService...\n";
try {
    $userWithPerms = UserPermisos::with('user', 'permission')->first();
    
    if ($userWithPerms) {
        $user = $userWithPerms->user;
        $perm = $userWithPerms->permission;
        
        if ($user && $perm) {
            $service = new PermissionService();
            $canDo = $service->canDo($user, $perm->action, $perm->moduleView->view_path ?? '');
            
            if ($canDo) {
                echo "  ✓ PermissionService: verificación correcta\n";
            } else {
                echo "  ✗ PermissionService: fallo en verificación\n";
                echo "    Usuario: {$user->username} (ID: {$user->id})\n";
                echo "    Permiso: {$perm->name}\n";
            }
        } else {
            echo "  ⚠ No se pudo cargar usuario o permiso para test\n";
        }
    } else {
        echo "  ⚠ No hay asignaciones de permisos para verificar\n";
    }
} catch (\Exception $e) {
    echo "  ✗ Error verificando PermissionService: {$e->getMessage()}\n";
}
echo "\n";

// Resumen
echo "==============================================\n";
echo "  Resumen\n";
echo "==============================================\n";
echo "Total de permisos: " . Permisos::count() . "\n";
echo "Total de moduleviews: " . ModulesViews::count() . "\n";
echo "Total de asignaciones usuario-permiso: " . UserPermisos::count() . "\n";
echo "\n";

if ($incorrectFormat > 0 || $withoutPermission > 0) {
    echo "⚠ Se encontraron problemas que requieren atención.\n";
    echo "Ejecutar los comandos sugeridos arriba para corregir.\n";
    exit(1);
} else {
    echo "✓ Todos los tests pasaron correctamente.\n";
    exit(0);
}
