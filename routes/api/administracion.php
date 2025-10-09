<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RolController;
use App\Http\Controllers\Api\RolePermissionController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ModulesController;
use App\Http\Controllers\Api\ModulesViewsController;
use App\Http\Controllers\Api\UserPermisosController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\CommissionConfigController;
use App\Http\Controllers\Api\AdvisorCommissionRateController;
use App\Http\Controllers\Api\CommissionController;

/**
 * ==========================================
 * ADMINISTRACIÓN - Usuarios, Roles, Permisos, Sesiones
 * ==========================================
 */

Route::middleware('auth:sanctum')->group(function () {

    // ----------------------
    // Usuario Autenticado
    // ----------------------
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [LoginController::class, 'logout']);

    // ----------------------
    // Usuarios
    // ----------------------
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::get('/{id}', [UserController::class, 'show']);
        Route::post('/', [UserController::class, 'store']);
        Route::put('/{id}', [UserController::class, 'update']);
        Route::delete('/{id}', [UserController::class, 'destroy']);
        Route::post('/{id}/restore', [UserController::class, 'restore']);
        Route::put('/bulk-update', [UserController::class, 'bulkUpdate']);
        Route::post('/bulk-delete', [UserController::class, 'bulkDelete']);
        Route::get('/export', [UserController::class, 'export']);
        Route::get('/role/{roleId}', [UserController::class, 'getUsersByRole']);
        Route::post('/{id}/assign-permissions', [UserController::class, 'assignPermissions']);
    });

    // ----------------------
    // Roles
    // ----------------------
    Route::prefix('roles')->group(function () {
        Route::get('/', [RolController::class, 'index']);
        Route::get('/{id}', [RolController::class, 'show']);
        Route::post('/', [RolController::class, 'store']);
        Route::put('/{id}', [RolController::class, 'update']);
        Route::delete('/{id}', [RolController::class, 'destroy']);
        Route::get('/{role}/permissions', [RolePermissionController::class, 'index']);
        Route::put('/{role}/permissions', [RolePermissionController::class, 'update']);
    });

    // ----------------------
    // Permisos
    // ----------------------
    Route::post('/permissions', [PermissionController::class, 'store']);

    // ----------------------
    // Módulos
    // ----------------------
    Route::prefix('modules')->group(function () {
        Route::get('/', [ModulesController::class, 'index']);
        Route::post('/', [ModulesController::class, 'store']);
        Route::get('/{id}', [ModulesController::class, 'show']);
        Route::put('/{id}', [ModulesController::class, 'update']);
        Route::delete('/{id}', [ModulesController::class, 'destroy']);

        // Vistas de un módulo
        Route::prefix('{moduleId}/views')->group(function () {
            Route::get('/', [ModulesViewsController::class, 'index']);
            Route::post('/', [ModulesViewsController::class, 'store']);
            Route::get('/{viewId}', [ModulesViewsController::class, 'show']);
            Route::put('/{viewId}', [ModulesViewsController::class, 'update']);
            Route::delete('/{viewId}', [ModulesViewsController::class, 'destroy']);
            Route::put('/views-order', [ModulesViewsController::class, 'updateOrder']);
        });
    });

    // ----------------------
    // Permisos de Usuario
    // ----------------------
    Route::prefix('userpermissions')->group(function () {
        Route::get('/', [UserPermisosController::class, 'index']);
        Route::post('/', [UserPermisosController::class, 'store']);
        Route::put('/{id}', [UserPermisosController::class, 'update']);
        Route::delete('/{id}', [UserPermisosController::class, 'destroy']);
        Route::get('/{user_id}', [UserPermisosController::class, 'getPermissionsByUserId']);
    });

    // ----------------------
    // Sesiones
    // ----------------------
    Route::prefix('sessions')->group(function () {
        Route::get('/', [SessionController::class, 'index']);
        Route::put('/{id}/close', [SessionController::class, 'closeSession']);
        Route::put('/close-all', [SessionController::class, 'closeAllSessions']);
    });

    // ----------------------
    // Comisiones
    // ----------------------
    Route::prefix('commissions')->group(function () {
        // Configuración global de comisiones (singleton)
        Route::get('/config', [CommissionConfigController::class, 'index']);
        Route::post('/config', [CommissionConfigController::class, 'store']);
        Route::put('/config', [CommissionConfigController::class, 'update']);

        // Tasas personalizadas por asesor
        Route::get('/rates/{userId}', [AdvisorCommissionRateController::class, 'show']);
        Route::post('/rates', [AdvisorCommissionRateController::class, 'store']);
        Route::put('/rates/{userId}', [AdvisorCommissionRateController::class, 'update']);

        // Comisiones / histórico
        Route::get('/', [CommissionController::class, 'index']);
        Route::post('/', [CommissionController::class, 'store']);
        Route::get('/{id}', [CommissionController::class, 'show']);
        Route::put('/{id}', [CommissionController::class, 'update']);
        Route::delete('/{id}', [CommissionController::class, 'destroy']);
        Route::get('/report', [CommissionController::class, 'report']);
    });
});
