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
use App\Http\Controllers\Api\ModulesController;
use App\Http\Controllers\Api\ModulesViewsController;
use App\Http\Controllers\Api\UserPermisosController;


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


//Rutas Para Modulos 
Route::get('modules', [ModulesController::class, 'index']);         // Obtener todos los módulos
Route::post('modules', [ModulesController::class, 'store']);        // Agregado de nuevos modulos
Route::get('modules/{id}', [ModulesController::class, 'show']);     // Obtener un módulo específico
Route::put('modules/{id}', [ModulesController::class, 'update']);   // Actualizar un módulo
Route::delete('modules/{id}', [ModulesController::class, 'destroy']); // Eliminar un módulo

//Rutas para Vistas de Modulos
Route::prefix('modules/{moduleId}')->group(function () {
    Route::get('views', [ModulesViewsController::class, 'index']);             // Listar vistas del módulo
    Route::post('views', [ModulesViewsController::class, 'store']);            // Crear nueva vista para el módulo
    Route::get('views/{viewId}', [ModulesViewsController::class, 'show']);     // Mostrar vista específica
    Route::put('views/{viewId}', [ModulesViewsController::class, 'update']);   // Actualizar vista específica
    Route::delete('views/{viewId}', [ModulesViewsController::class, 'destroy']); // Eliminar vista
    Route::put('views-order', [ModulesViewsController::class, 'updateOrder']); // Actualizar orden de vistas
});

//routes de Permisos 

Route::prefix('userpermissions')->group(function () {
    // Listar permisos asignados a un usuario (se espera que se pase ?user_id=)
    Route::get('/', [UserPermisosController::class, 'index']);

    // Asignar o actualizar permisos del usuario
    Route::post('/', [UserPermisosController::class, 'store']);

    // Actualizar un permiso específico (por ejemplo, para modificar el 'scope')
    Route::put('/{id}', [UserPermisosController::class, 'update']);

    // Eliminar un permiso asignado al usuario
    Route::delete('/{id}', [UserPermisosController::class, 'destroy']);
});
