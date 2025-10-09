<?php

/**
 * ==========================================
 * RUTAS API - Estructura Modular
 * ==========================================
 * 
 * Este archivo carga las rutas organizadas por dominio:
 * - public.php: Rutas públicas (health, login, etc.)
 * - prospectos.php: Gestión de leads y seguimiento
 * - seguimiento.php: Citas, tareas, interacciones
 * - academico.php: Programas, cursos, estudiantes
 * - financiero.php: Pagos, facturas, conciliación
 * - administracion.php: Usuarios, roles, permisos
 * 
 * Cambios principales:
 * - Consolidado health checks en un solo endpoint /health
 * - Agrupados dominios por archivo modular
 * - Estandarizada nomenclatura (reconciliation en lugar de conciliacion)
 * - Eliminadas rutas duplicadas
 * - Mantenida compatibilidad con frontend existente
 */

use Illuminate\Support\Facades\Route;


// ==========================================
// Cargar rutas modulares por dominio
// ==========================================

// Rutas públicas (sin autenticación)
require __DIR__ . '/api/public.php';

// Dominio: Prospectos y gestión de leads
require __DIR__ . '/api/prospectos.php';

// Dominio: Seguimiento (citas, tareas, interacciones)
require __DIR__ . '/api/seguimiento.php';

// Dominio: Académico (programas, cursos, estudiantes)
require __DIR__ . '/api/academico.php';

// Dominio: Financiero (pagos, facturas, conciliación)
require __DIR__ . '/api/financiero.php';

// Dominio: Administración (usuarios, roles, permisos)
require __DIR__ . '/api/administracion.php';

// ==========================================
// Rutas de compatibilidad legacy
// ==========================================
// Estas rutas mantienen compatibilidad con el frontend existente
// pero están duplicadas/dispersas. Considerar eliminar en futuras versiones.

// Alias para health check (mantener por compatibilidad)
Route::get('/ping', fn() => response()->json(['message' => 'pong!']));
Route::get('/status', function () {
    return response()->json(['status' => 'API is running']);
});
Route::get('/version', function () {
    return response()->json(['version' => '1.0.0']);
});
Route::get('/time', function () {
    return response()->json(['time' => now()->toDateTimeString()]);
});
Route::get('/db-status', function () {
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        return response()->json([
            'db'     => 'connected',
            'status' => 'ok',
            'time'   => now()->toDateTimeString(),
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'db'     => 'disconnected',
            'status' => 'error',
            'error'  => 'No se pudo conectar a la base de datos',
            'time'   => now()->toDateTimeString(),
        ], 500);
    }
});

