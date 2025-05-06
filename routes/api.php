<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Importar controladores
use App\Http\Controllers\Api\ProspectoController;
use App\Http\Controllers\Api\ProgramaController;
use App\Http\Controllers\Api\UbicacionController;
use App\Http\Controllers\Api\RolController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\ModulesController;
use App\Http\Controllers\Api\ModulesViewsController;
use App\Http\Controllers\Api\UserPermisosController;
use App\Http\Controllers\Api\ColumnConfigurationController;
use App\Http\Controllers\Api\ProspectosImportController;
use App\Http\Controllers\Api\CorreoController;
use App\Http\Controllers\Api\ActividadesController;
use App\Http\Controllers\Api\CitasController;
use App\Http\Controllers\Api\InteraccionesController;
use App\Http\Controllers\Api\TareasGenController;
use App\Http\Controllers\PriceController;
use App\Http\Controllers\Api\KardexPagoController;
use App\Http\Controllers\Api\ProspectosDocumentoController;
use App\Http\Controllers\Api\ConvenioController;
use App\Http\Controllers\Api\KardexController;
use App\Http\Controllers\Api\ProspectoProgramaController;
use App\Http\Controllers\Api\EstudianteProgramaController;
use App\Http\Controllers\Api\ProspectoConvenioController;
use App\Http\Controllers\Api\ProspectoCuotaEstudianteController;
use App\Http\Controllers\Api\PlanPagosController;
use App\Http\Controllers\InscripcionController;
use App\Http\Controllers\Api\DuplicateRecordController;
use App\Http\Controllers\Api\CommissionConfigController;
use App\Http\Controllers\Api\AdvisorCommissionRateController;
use App\Http\Controllers\Api\CommissionController;

/**
 * Rutas Públicas
 */
// Ping para verificar que el API esté activo
Route::get('/ping', fn() => response()->json(['message' => 'pong!']));

// Rutas de autenticación
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth:sanctum');

// Inscripciones y generación de plan de pagos
Route::post('/plan-pagos/generar', [PlanPagosController::class, 'generar']);
Route::post('/inscripciones/finalizar', [InscripcionController::class, 'finalizar']);

// Rutas de consulta pública de prospectos (solo lectura)
Route::get('/prospectos/{id}', [ProspectoController::class, 'show']);

// Consulta de ficha (solo para IDs numéricos y con autenticación)
Route::get('/fichas/{id}', [InscripcionController::class, 'show'])
    ->middleware('auth:sanctum')
    ->where('id', '[0-9]+');

/**
 * Rutas Protegidas (Requieren auth:sanctum)
 */
Route::middleware('auth:sanctum')->group(function () {

    // Usuario autenticado
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // ----------------------
    // Rutas de Prospectos
    // ----------------------
    Route::prefix('prospectos')->group(function () {
        // CRUD básico
        Route::get('/', [ProspectoController::class, 'index']);
        Route::post('/', [ProspectoController::class, 'store']);

        // Funciones adicionales fijas
        Route::put('/bulk-assign', [ProspectoController::class, 'bulkAssign']);
        Route::put('/bulk-update-status', [ProspectoController::class, 'bulkUpdateStatus']);
        Route::delete('/bulk-delete', [ProspectoController::class, 'bulkDelete']);

        // Luego las rutas que usan el parámetro {id} (agregando restricción para que se acepten solo números)
        Route::get('/{id}', [ProspectoController::class, 'show'])->where('id', '[0-9]+');
        Route::put('/{id}', [ProspectoController::class, 'update'])->where('id', '[0-9]+');
        Route::delete('/{id}', [ProspectoController::class, 'destroy'])->where('id', '[0-9]+');
        Route::put('/{id}/status', [ProspectoController::class, 'updateStatus'])->where('id', '[0-9]+');
        Route::put('/{id}/assign', [ProspectoController::class, 'assignOne'])->where('id', '[0-9]+');
        Route::post('/{id}/enviar-contrato', [ProspectoController::class, 'enviarContrato'])->where('id', '[0-9]+');
        Route::get('/status/{status}', [ProspectoController::class, 'filterByStatus']);
        Route::get('/fichas/pendientes', [ProspectoController::class, 'pendientesAprobacion']);
    });

    // Importación de prospectos
    Route::post('/import', [ProspectosImportController::class, 'uploadExcel'])
        ->name('prospectos.import');

    // ----------------------
    // Sesiones
    // ----------------------
    Route::prefix('sessions')->group(function () {
        Route::get('/', [SessionController::class, 'index']);
        Route::put('/{id}/close', [SessionController::class, 'closeSession']);
        Route::put('/close-all', [SessionController::class, 'closeAllSessions']);
    });

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

    Route::prefix('duplicates')->group(function () {
        // 1) Listar duplicados (GET  /api/duplicates)
        Route::get('/', [DuplicateRecordController::class, 'index']);
        // 2) Disparar detección (POST /api/duplicates/detect)
        Route::post('/detect', [DuplicateRecordController::class, 'detect']);
        // 3) Acción sobre un duplicado (POST /api/duplicates/{id}/action)
        Route::post(
            '/{duplicate}/action',
            [DuplicateRecordController::class, 'action']
        )
            ->where('duplicate', '[0-9]+');
    });

    Route::prefix('commissions')->group(function () {
        //
        // 1) Configuración global de comisiones (singleton)
        //
        // GET  /api/commissions/config   → CommissionConfigController@index
        // POST /api/commissions/config   → CommissionConfigController@store
        // PUT  /api/commissions/config   → CommissionConfigController@update
        Route::get('/config', [CommissionConfigController::class, 'index']);
        Route::post('/config', [CommissionConfigController::class, 'store']);
        Route::put('/config', [CommissionConfigController::class, 'update']);

        //
        // 2) Tasas personalizadas por asesor
        //
        // GET  /api/commissions/rates/{userId}  → AdvisorCommissionRateController@show
        // PUT  /api/commissions/rates/{userId}  → AdvisorCommissionRateController@update
        // Get commission rate for an advisor
        Route::get('/rates/{userId}', [AdvisorCommissionRateController::class, 'show']);

        // Store (create) commission rate for an advisor
        Route::post('/rates', [AdvisorCommissionRateController::class, 'store']);

        // Update commission rate for an advisor
        Route::put('/rates/{userId}', [AdvisorCommissionRateController::class, 'update']);
        //
        // 3) Comisiones / histórico
        //
        // GET  /api/commissions         → CommissionController@index
        // POST /api/commissions         → CommissionController@store
        // GET  /api/commissions/{id}    → CommissionController@show
        // DELETE /api/commissions/{id}  → CommissionController@destroy
        // (opcional) GET /api/commissions/report → CommissionController@report
        Route::get('/',         [CommissionController::class, 'index']);
        Route::post('/',         [CommissionController::class, 'store']);
        Route::get('/{id}',     [CommissionController::class, 'show']);
        Route::put('/{id}',     [CommissionController::class, 'update']);
        Route::delete('/{id}',     [CommissionController::class, 'destroy']);
        Route::get('/report',   [CommissionController::class, 'report']);
    });
});

/**
 * Rutas Públicas (sin auth)
 */
// ----------------------
// Programas y Ubicación
// ----------------------
Route::get('/programas', [ProgramaController::class, 'ObtenerProgramas']);
Route::get('/ubicacion/{paisId}', [UbicacionController::class, 'getUbicacionByPais']);

// ----------------------
// Roles
// ----------------------
Route::prefix('roles')->group(function () {
    Route::get('/', [RolController::class, 'index']);
    Route::get('/{id}', [RolController::class, 'show']);
    Route::post('/', [RolController::class, 'store']);
    Route::put('/{id}', [RolController::class, 'update']);
    Route::delete('/{id}', [RolController::class, 'destroy']);
});

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
});

// ----------------------
// Módulos y Vistas de Módulos
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
});

// ----------------------
// Column Configuration (Prospectos)
// ----------------------
Route::prefix('columns')->group(function () {
    Route::get('/', [ColumnConfigurationController::class, 'index'])
        ->name('prospectos.columns.index');
    Route::post('/', [ColumnConfigurationController::class, 'store'])
        ->name('prospectos.columns.store');
    Route::put('/{id}', [ColumnConfigurationController::class, 'update'])
        ->name('prospectos.columns.update');
    Route::delete('/{id}', [ColumnConfigurationController::class, 'destroy'])
        ->name('prospectos.columns.destroy');
});

// ----------------------
// Importación y Envío de Correos para Prospectos
// ----------------------
Route::post('/enviar-correo', [CorreoController::class, 'enviar']);

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

// ----------------------
// Documentos para Prospectos
// ----------------------
Route::prefix('documentos')->group(function () {
    Route::get('/', [ProspectosDocumentoController::class, 'index']);
    Route::post('/', [ProspectosDocumentoController::class, 'store']);
    Route::get('/{id}', [ProspectosDocumentoController::class, 'show']);
    Route::put('/{id}', [ProspectosDocumentoController::class, 'update']);
    Route::delete('/{id}', [ProspectosDocumentoController::class, 'destroy']);
});

// ----------------------
// Convenios (Uso de apiResource para CRUD completo)
// ----------------------
Route::apiResource('convenios', ConvenioController::class);

// ----------------------
// Estudiante Programa
// ----------------------
Route::prefix('estudiante-programa')->group(function () {
    Route::post('/', [EstudianteProgramaController::class, 'store']);
});

// ----------------------
// Precios
// ----------------------
Route::get('precios/programa/{programa}', [PriceController::class, 'porPrograma']);
Route::get('precios/convenio/{convenio}/{programa}', [PriceController::class, 'porConvenio']);
