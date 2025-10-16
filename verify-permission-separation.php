#!/usr/bin/env php
<?php

/**
 * Script de Verificación - Separación de Permisos
 * 
 * Este script verifica que la separación entre permisos de usuario y permisos de rol
 * se haya realizado correctamente.
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║  Verificación de Separación de Permisos - Usuario vs Rol        ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

$errors = [];
$warnings = [];
$success = [];

// 1. Verificar que existe la tabla permisos
echo "1. Verificando existencia de tabla 'permisos'...\n";
if (Schema::hasTable('permisos')) {
    $count = DB::table('permisos')->count();
    $success[] = "✓ Tabla 'permisos' existe con {$count} registros";
} else {
    $errors[] = "✗ Tabla 'permisos' NO existe. Ejecutar: php artisan migrate";
}

// 2. Verificar que existe la tabla permissions
echo "2. Verificando existencia de tabla 'permissions'...\n";
if (Schema::hasTable('permissions')) {
    $count = DB::table('permissions')->count();
    $success[] = "✓ Tabla 'permissions' existe con {$count} registros";
} else {
    $errors[] = "✗ Tabla 'permissions' NO existe";
}

// 3. Verificar columnas de permisos
echo "3. Verificando estructura de tabla 'permisos'...\n";
if (Schema::hasTable('permisos')) {
    $requiredColumns = ['id', 'moduleview_id', 'action', 'name', 'description'];
    $missingColumns = [];
    
    foreach ($requiredColumns as $col) {
        if (!Schema::hasColumn('permisos', $col)) {
            $missingColumns[] = $col;
        }
    }
    
    if (empty($missingColumns)) {
        $success[] = "✓ Tabla 'permisos' tiene todas las columnas requeridas";
    } else {
        $errors[] = "✗ Tabla 'permisos' falta columnas: " . implode(', ', $missingColumns);
    }
}

// 4. Verificar que userpermissions apunta a permisos
echo "4. Verificando relación userpermissions → permisos...\n";
$brokenRefs = DB::table('userpermissions as up')
    ->leftJoin('permisos as p', 'p.id', '=', 'up.permission_id')
    ->whereNull('p.id')
    ->count();

if ($brokenRefs === 0) {
    $totalUserPerms = DB::table('userpermissions')->count();
    $success[] = "✓ Todos los {$totalUserPerms} registros en userpermissions tienen referencia válida a permisos";
} else {
    $errors[] = "✗ Hay {$brokenRefs} registros en userpermissions sin referencia válida a permisos";
}

// 5. Verificar que rolepermissions apunta a permissions
echo "5. Verificando relación rolepermissions → permissions...\n";
if (Schema::hasTable('rolepermissions')) {
    $brokenRefs = DB::table('rolepermissions as rp')
        ->leftJoin('permissions as p', 'p.id', '=', 'rp.permission_id')
        ->whereNull('p.id')
        ->count();
    
    if ($brokenRefs === 0) {
        $totalRolePerms = DB::table('rolepermissions')->count();
        $success[] = "✓ Todos los {$totalRolePerms} registros en rolepermissions tienen referencia válida a permissions";
    } else {
        $errors[] = "✗ Hay {$brokenRefs} registros en rolepermissions sin referencia válida a permissions";
    }
}

// 6. Verificar que permisos apunta a moduleviews
echo "6. Verificando relación permisos → moduleviews...\n";
$brokenRefs = DB::table('permisos as p')
    ->whereNotNull('p.moduleview_id')
    ->leftJoin('moduleviews as mv', 'mv.id', '=', 'p.moduleview_id')
    ->whereNull('mv.id')
    ->count();

if ($brokenRefs === 0) {
    $success[] = "✓ Todos los permisos tienen referencia válida a moduleviews";
} else {
    $warnings[] = "⚠ Hay {$brokenRefs} permisos sin referencia válida a moduleviews (pueden ser huérfanos)";
}

// 7. Verificar que permissions apunta a moduleviews
echo "7. Verificando relación permissions → moduleviews...\n";
$brokenRefs = DB::table('permissions as p')
    ->whereNotNull('p.moduleview_id')
    ->leftJoin('moduleviews as mv', 'mv.id', '=', 'p.moduleview_id')
    ->whereNull('mv.id')
    ->count();

if ($brokenRefs === 0) {
    $success[] = "✓ Todos los permissions tienen referencia válida a moduleviews";
} else {
    $warnings[] = "⚠ Hay {$brokenRefs} permissions sin referencia válida a moduleviews";
}

// 8. Verificar que no hay duplicados en permisos
echo "8. Verificando duplicados en tabla 'permisos'...\n";
if (Schema::hasTable('permisos')) {
    $duplicates = DB::table('permisos')
        ->select('name', DB::raw('COUNT(*) as count'))
        ->groupBy('name')
        ->having('count', '>', 1)
        ->get();
    
    if ($duplicates->isEmpty()) {
        $success[] = "✓ No hay permisos duplicados en tabla 'permisos'";
    } else {
        $warnings[] = "⚠ Hay {$duplicates->count()} nombres duplicados en tabla 'permisos'";
        foreach ($duplicates as $dup) {
            echo "   - '{$dup->name}' aparece {$dup->count} veces\n";
        }
    }
}

// 9. Verificar modelos
echo "9. Verificando configuración de modelos...\n";
try {
    $permisosTable = (new \App\Models\Permisos())->getTable();
    $permissionTable = (new \App\Models\Permission())->getTable();
    
    if ($permisosTable === 'permisos') {
        $success[] = "✓ Modelo Permisos usa tabla 'permisos' (correcto)";
    } else {
        $errors[] = "✗ Modelo Permisos usa tabla '{$permisosTable}' (debe ser 'permisos')";
    }
    
    if ($permissionTable === 'permissions') {
        $success[] = "✓ Modelo Permission usa tabla 'permissions' (correcto)";
    } else {
        $errors[] = "✗ Modelo Permission usa tabla '{$permissionTable}' (debe ser 'permissions')";
    }
} catch (\Exception $e) {
    $errors[] = "✗ Error al verificar modelos: " . $e->getMessage();
}

// 10. Estadísticas generales
echo "10. Recopilando estadísticas...\n";
try {
    $stats = [
        'permisos_usuario' => DB::table('permisos')->count(),
        'permisos_rol' => DB::table('permissions')->count(),
        'usuarios_con_permisos' => DB::table('userpermissions')->distinct('user_id')->count(),
        'roles_con_permisos' => Schema::hasTable('rolepermissions') 
            ? DB::table('rolepermissions')->distinct('role_id')->count() 
            : 0,
        'moduleviews_totales' => DB::table('moduleviews')->count(),
    ];
    
    echo "\n╔══════════════════════════════════════════════════════════════════╗\n";
    echo "║                         ESTADÍSTICAS                             ║\n";
    echo "╚══════════════════════════════════════════════════════════════════╝\n";
    echo "  Permisos de Usuario (permisos):     {$stats['permisos_usuario']}\n";
    echo "  Permisos de Rol (permissions):      {$stats['permisos_rol']}\n";
    echo "  Usuarios con permisos:              {$stats['usuarios_con_permisos']}\n";
    echo "  Roles con permisos:                 {$stats['roles_con_permisos']}\n";
    echo "  ModuleViews totales:                {$stats['moduleviews_totales']}\n";
} catch (\Exception $e) {
    $warnings[] = "⚠ No se pudieron obtener estadísticas: " . $e->getMessage();
}

// Resumen
echo "\n╔══════════════════════════════════════════════════════════════════╗\n";
echo "║                         RESUMEN                                  ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

if (!empty($success)) {
    echo "✅ ÉXITOS (" . count($success) . "):\n";
    foreach ($success as $s) {
        echo "  {$s}\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "⚠️  ADVERTENCIAS (" . count($warnings) . "):\n";
    foreach ($warnings as $w) {
        echo "  {$w}\n";
    }
    echo "\n";
}

if (!empty($errors)) {
    echo "❌ ERRORES (" . count($errors) . "):\n";
    foreach ($errors as $e) {
        echo "  {$e}\n";
    }
    echo "\n";
}

// Resultado final
echo "╔══════════════════════════════════════════════════════════════════╗\n";
if (empty($errors)) {
    echo "║  ✅ VERIFICACIÓN EXITOSA - Sistema correctamente separado       ║\n";
    echo "╚══════════════════════════════════════════════════════════════════╝\n";
    exit(0);
} else {
    echo "║  ❌ VERIFICACIÓN FALLIDA - Corregir errores antes de continuar  ║\n";
    echo "╚══════════════════════════════════════════════════════════════════╝\n";
    exit(1);
}
