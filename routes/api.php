<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProspectoController;
use App\Http\Controllers\Api\ProgramaController;
use App\Http\Controllers\Api\UbicacionController;
use App\Http\Controllers\Api\RolController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\SessionController;

// Rutas generales
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/ping', function () {
    return response()->json(['message' => 'pong!']);
});

// Rutas para prospectos
Route::get('/prospectos', [ProspectoController::class, 'index']);
Route::post('/prospectos', [ProspectoController::class, 'store']);

// Rutas para programas
Route::get('/programas', [ProgramaController::class, 'ObtenerProgramas']);

// Rutas para ubicaciones
Route::get('/ubicacion/{paisId}', [UbicacionController::class, 'getUbicacionByPais']);

// Rutas para roles
Route::get('/roles', [RolController::class, 'index']);
Route::get('/roles/{id}', [RolController::class, 'show']);
Route::post('/roles', [RolController::class, 'store']);
Route::put('/roles/{id}', [RolController::class, 'update']);
Route::delete('/roles/{id}', [RolController::class, 'destroy']);

// Rutas para usuarios
Route::get('/users', [UserController::class, 'index']);
Route::get('/users/{id}', [UserController::class, 'show']);
Route::post('/users', [UserController::class, 'store']);
Route::put('/users/{id}', [UserController::class, 'update']);
Route::delete('/users/{id}', [UserController::class, 'destroy']);
Route::post('/users/{id}/restore', [UserController::class, 'restore']);
Route::put('/users/bulk-update', [UserController::class, 'bulkUpdate']);

// Rutas de Login & Logout
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth:sanctum');

// Rutas de Sesiones (protegidas con Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    // Gestión de sesiones
    Route::get('/sessions', [SessionController::class, 'index']);
    Route::put('/sessions/{id}/close', [SessionController::class, 'closeSession']);
    Route::put('/sessions/close-all', [SessionController::class, 'closeAllSessions']);
});
