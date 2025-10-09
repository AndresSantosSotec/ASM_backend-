<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CitasController;
use App\Http\Controllers\Api\InteraccionesController;
use App\Http\Controllers\Api\TareasGenController;
use App\Http\Controllers\Api\ActividadesController;

/**
 * ==========================================
 * SEGUIMIENTO - Citas, Tareas, Interacciones, Actividades
 * ==========================================
 * Todas las rutas requieren autenticación (auth:sanctum)
 */

Route::middleware('auth:sanctum')->group(function () {

    // ----------------------
    // Citas
    // ----------------------
    Route::prefix('citas')->group(function () {
        Route::get('/', [CitasController::class, 'index']);
        Route::get('/{id}', [CitasController::class, 'show']);
        Route::post('/', [CitasController::class, 'store']);
        Route::put('/{id}', [CitasController::class, 'update']);
        Route::delete('/{id}', [CitasController::class, 'destroy']);
    });

    // ----------------------
    // Interacciones
    // ----------------------
    Route::prefix('interacciones')->group(function () {
        Route::get('/', [InteraccionesController::class, 'index']);
        Route::get('/{id}', [InteraccionesController::class, 'show']);
        Route::post('/', [InteraccionesController::class, 'store']);
        Route::put('/{id}', [InteraccionesController::class, 'update']);
        Route::delete('/{id}', [InteraccionesController::class, 'destroy']);
    });

    // ----------------------
    // Tareas Genéricas
    // ----------------------
    Route::prefix('tareas')->group(function () {
        Route::get('/', [TareasGenController::class, 'index']);
        Route::get('/{id}', [TareasGenController::class, 'show']);
        Route::post('/', [TareasGenController::class, 'store']);
        Route::put('/{id}', [TareasGenController::class, 'update']);
        Route::delete('/{id}', [TareasGenController::class, 'destroy']);
    });

    // ----------------------
    // Actividades
    // ----------------------
    Route::prefix('actividades')->group(function () {
        Route::get('/', [ActividadesController::class, 'index']);
        Route::get('/{id}', [ActividadesController::class, 'show']);
        Route::post('/', [ActividadesController::class, 'store']);
        Route::put('/{id}', [ActividadesController::class, 'update']);
        Route::delete('/{id}', [ActividadesController::class, 'destroy']);
    });
});
