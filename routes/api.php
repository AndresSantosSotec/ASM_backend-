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
use App\Http\Controllers\Api\ColumnConfigurationController;
use App\Http\Controllers\Api\ProspectosImportController;
use App\Http\Controllers\Api\CorreoController;
use App\Http\Controllers\Api\TareasAsesorController;
use App\Http\Controllers\Api\TareasGen;


// Rutas generales
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/ping', function () {
    return response()->json(['message' => 'pong!']);
});

// Rutas públicas para prospectos
Route::get('/prospectos/{id}', [ProspectoController::class, 'show']);

// Rutas protegidas para prospectos (todas requieren autenticación)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/prospectos', [ProspectoController::class, 'index']);
    Route::get('/prospectos/{id}', [ProspectoController::class, 'show']);
    Route::post('/prospectos', [ProspectoController::class, 'store']);
    Route::put('/prospectos/{id}', [ProspectoController::class, 'update']);
    Route::put('/prospectos/{id}/status', [ProspectoController::class, 'updateStatus']);
    Route::delete('/prospectos/{id}', [ProspectoController::class, 'destroy']);
});

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
Route::post('/users/bulk-delete', [UserController::class, 'bulkDelete']);
Route::get('/users/export', [UserController::class, 'export']);


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
    Route::get('views', [ModulesViewsController::class, 'index']);
    Route::post('views', [ModulesViewsController::class, 'store']);
    Route::get('views/{viewId}', [ModulesViewsController::class, 'show']);
    Route::put('views/{viewId}', [ModulesViewsController::class, 'update']);
    Route::delete('views/{viewId}', [ModulesViewsController::class, 'destroy']);
    Route::put('views-order', [ModulesViewsController::class, 'updateOrder']);
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

// Rutas para el Column COnfiguration
// Grupo de rutas para la configuración de columnas de prospectos
Route::prefix('/columns')->group(function () {
    // Listar todas las columnas configurables
    Route::get('/', [ColumnConfigurationController::class, 'index'])
        ->name('prospectos.columns.index');

    // Crear o actualizar una configuración de columna (upsert)
    Route::post('/', [ColumnConfigurationController::class, 'store'])
        ->name('prospectos.columns.store');

    // Actualizar una configuración de columna específica
    Route::put('/{id}', [ColumnConfigurationController::class, 'update'])
        ->name('prospectos.columns.update');

    // Eliminar una configuración de columna específica
    Route::delete('/{id}', [ColumnConfigurationController::class, 'destroy'])
        ->name('prospectos.columns.destroy');
});

// Rutas públicas para la importación de prospectos (sin autenticación)
Route::middleware('auth:sanctum')->group(function () {
    // Ruta protegida para la importación de prospectos
    Route::post('/import', [ProspectosImportController::class, 'uploadExcel'])->name('prospectos.import');
});

//enviar correo
Route::post('/enviar-correo', [CorreoController::class, 'enviar']);


//routes Protegidas de las tareas de los asesores   
Route::middleware('auth:sanctum')->group(function () {
    // Ruta protegida para la importación de prospectos

});

//routes para tareas genericas
Route::middleware('auth:sanctum')->group(function () {
    // Ruta protegida para la importación de prospectos
    
});

