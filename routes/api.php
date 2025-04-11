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
use App\Http\Controllers\Api\ActividadesController;
use App\Http\Controllers\Api\CitasController;
use App\Http\Controllers\Api\InteraccionesController;

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

//Estas son las rutas para controlador de Actividades
Route::prefix('actividades')->group(function () {
    Route::get('/', [ActividadesController::class, 'index']); // Obtener todas las actividades
    Route::get('/{id}', [ActividadesController::class, 'show']); // Obtener una actividad específica
    Route::post('/', [ActividadesController::class, 'store']); // Crear una nueva actividad
    Route::put('/{id}', [ActividadesController::class, 'update']); // Actualizar una actividad existente
    Route::delete('/{id}', [ActividadesController::class, 'destroy']); // Eliminar una actividad
});

// Rutas protegidas para el controlador de Citas
Route::middleware('auth:sanctum')->prefix('citas')->group(function () {
    Route::get('/', [CitasController::class, 'index']); // List all citas
    Route::get('/{id}', [CitasController::class, 'show']); // Show a single cita
    Route::post('/', [CitasController::class, 'store']); // Create a new cita
    Route::put('/{id}', [CitasController::class, 'update']); // Update an existing cita
    Route::delete('/{id}', [CitasController::class, 'destroy']); // Delete a cita
});

// Rutas protegidas para el controlador de Interacciones
Route::middleware('auth:sanctum')->prefix('interacciones')->group(function () {
    Route::get('/', [InteraccionesController::class, 'index']); // List all interacciones
    Route::get('/{id}', [InteraccionesController::class, 'show']); // Show a single interaccion
    Route::post('/', [InteraccionesController::class, 'store']); // Create a new interaccion
    Route::put('/{id}', [InteraccionesController::class, 'update']); // Update an existing interaccion
    Route::delete('/{id}', [InteraccionesController::class, 'destroy']); // Delete an interaccion
});