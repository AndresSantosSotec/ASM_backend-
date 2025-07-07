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
use App\Http\Controllers\Api\ProspectosDocumentoController;
use App\Http\Controllers\Api\ConvenioController;
use App\Http\Controllers\Api\EstudianteProgramaController;
use App\Http\Controllers\Api\PlanPagosController;
use App\Http\Controllers\InscripcionController;
use App\Http\Controllers\Api\DuplicateRecordController;
use App\Http\Controllers\Api\CommissionConfigController;
use App\Http\Controllers\Api\AdvisorCommissionRateController;
use App\Http\Controllers\Api\CommissionController;
use App\Http\Controllers\Api\ContactoEnviadoController;
use App\Http\Controllers\Api\PeriodoInscripcionController;
use App\Http\Controllers\Api\InscripcionPeriodoController;
use App\Http\Controllers\Api\ApprovalFlowController;
use App\Http\Controllers\Api\ApprovalStageController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\RankingController;
use App\Http\Controllers\Api\CoursePerformanceController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\CollectionLogController;


/**
 * Rutas Públicas
 */
// Ping para verificar que el API esté activo
Route::get('/ping', fn() => response()->json(['message' => 'pong!']));

// Rutas de autenticación
Route::post('/login', [LoginController::class, 'login'])->name('login');
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
        //new route
        Route::put('/prospectos/bulk-update-status', [ProspectoController::class, 'bulkUpdateStatus']);

        Route::get('pendientes-con-docs', [ProspectoController::class, 'pendientesConDocs']);
        Route::get('prospectos/{id}/download-contrato', [ProspectoController::class, 'downloadContrato']);
        // Prospectos inscritos con sus programas y cursos
        Route::get('inscritos-with-courses', [ProspectoController::class, 'inscritosConCursos']);
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

        Route::post('/bulk-action', [DuplicateRecordController::class, 'bulkAction']);
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

Route::apiResource('periodos', PeriodoInscripcionController::class);

Route::apiResource('periodos.inscripciones', InscripcionPeriodoController::class)
    ->shallow();

Route::apiResource('contactos-enviados', ContactoEnviadoController::class);
Route::get('prospectos/{prospecto}/contactos-enviados', [ContactoEnviadoController::class, 'byProspecto']);

// ----------------------
// Programas y Ubicación
// ----------------------
Route::prefix('programas')->group(function () {
    // Obtener todos los programas (ya existe)
    Route::get('/', [ProgramaController::class, 'ObtenerProgramas']);

    // Crear nuevo programa con precios
    Route::post('/', [ProgramaController::class, 'CretatePrograma']);

    // Actualizar programa y sus precios
    Route::put('/{id}', [ProgramaController::class, 'UpdatePrograma']);

    // Eliminar programa (y sus precios en cascada)
    Route::delete('/{id}', [ProgramaController::class, 'deletePrograma']);

    // Rutas específicas para precios
    Route::get('/{programaId}/precios', [ProgramaController::class, 'obtenerPreciosPrograma']);
    Route::put('/{programaId}/precios', [ProgramaController::class, 'actualizarPrecioPrograma']);
});
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
    Route::get('/{user_id}', [UserPermisosController::class, 'getPermissionsByUserId']);
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
    // routes/api.php
    Route::get('/documentos/{id}/file', [ProspectosDocumentoController::class, 'download']);

    Route::get('/prospecto/{prospectoId}', [ProspectosDocumentoController::class, 'documentosPorProspecto']);

    //Obtener documentos por prospecto
});

// ----------------------
// Convenios (Uso de apiResource para CRUD completo)
// ----------------------
Route::apiResource('convenios', ConvenioController::class);

// ----------------------
// Estudiante Programa
// ----------------------
Route::prefix('estudiante-programa')->group(function () {
    // 1️⃣ Primero la estática:
    Route::get('/all', [EstudianteProgramaController::class, 'getProgramas']);

    // 2️⃣ Luego la dinámica, ahora restringida a IDs numéricos:
    Route::get('/{id}', [EstudianteProgramaController::class, 'show'])
        ->whereNumber('id');

    Route::get('/{id}/with-courses', [EstudianteProgramaController::class, 'getProgramasConCursos'])
        ->whereNumber('id');

    Route::post('/',    [EstudianteProgramaController::class, 'store']);
    Route::put('/{id}', [EstudianteProgramaController::class, 'update'])
        ->whereNumber('id');
    Route::delete('/{id}', [EstudianteProgramaController::class, 'destroy'])
        ->whereNumber('id');

    // Si quieres seguir con la versión que lee query param:
    // puedes usar GET /estudiante-programa?prospecto_id=123
    // y en el controlador leer $request->input('prospecto_id'):
    Route::get('/', [EstudianteProgramaController::class, 'getProgramasProspecto']);
});


// ----------------------
// Precios
// ----------------------
Route::get('precios/programa/{programa}', [PriceController::class, 'porPrograma']);
Route::get('precios/convenio/{convenio}/{programa}', [PriceController::class, 'porConvenio']);



Route::get('/prospectos/fichas/pendientes-public', [ProspectoController::class, 'pendientesAprobacion']);

Route::get('contactos-enviados/{id}/download-contrato', [ContactoEnviadoController::class, 'downloadContrato']);

Route::get(
    '/contactos-enviados/today',
    [ContactoEnviadoController::class, 'today']
);


Route::prefix('approval-flows')->group(function () {
    Route::get('/',           [ApprovalFlowController::class, 'index']);
    Route::post('/',          [ApprovalFlowController::class, 'store']);
    Route::get('{flow}',      [ApprovalFlowController::class, 'show']);
    Route::put('{flow}',      [ApprovalFlowController::class, 'update']);
    Route::delete('{flow}',   [ApprovalFlowController::class, 'destroy']);
    Route::post('{flow}/toggle', [ApprovalFlowController::class, 'toggle']);

    // Etapas anidadas
    Route::post('{flow}/stages',    [ApprovalStageController::class, 'store']);
    Route::put('stages/{stage}',    [ApprovalStageController::class, 'update']);
    Route::delete('stages/{stage}', [ApprovalStageController::class, 'destroy']);
});

//Rutas para la crearion de los cursos
Route::prefix('courses')->group(function () {
    Route::get('/', [CourseController::class, 'index']);
    Route::post('/', [CourseController::class, 'store']);
    Route::get('/{course}', [CourseController::class, 'show']);
    Route::put('/{course}', [CourseController::class, 'update']);
    Route::delete('/{course}', [CourseController::class, 'destroy']);

    // Rutas adicionales
    Route::post('/{course}/approve', [CourseController::class, 'approve']);
    Route::post('/{course}/sync-moodle', [CourseController::class, 'syncToMoodle']);
    Route::post('/{course}/assign-facilitator', [CourseController::class, 'assignFacilitator']);

    // Rutas de asignación de cursos a prospectos
    Route::post('/assign', [CourseController::class, 'assignCourses']);
    Route::post('/unassign', [CourseController::class, 'unassignCourses']);
});

// Ranking y rendimiento
Route::get('/ranking/students', [RankingController::class, 'index'])->middleware('auth:sanctum');
Route::get('/ranking/courses', [CoursePerformanceController::class, 'index'])->middleware('auth:sanctum');
Route::get('/students/{id}', [StudentController::class, 'show'])->middleware('auth:sanctum');

// ----------------------
// Finanzas y Pagos
// ----------------------
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/invoices', [InvoiceController::class, 'index']);
    Route::post('/invoices', [InvoiceController::class, 'store']);
    Route::put('/invoices/{invoice}', [InvoiceController::class, 'update']);
    Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy']);

    Route::get('/payments', [PaymentController::class, 'index']);
    Route::post('/payments', [PaymentController::class, 'store']);


    // Planes de pago reales
    Route::get('/prospectos/{id}/cuotas', [\App\Http\Controllers\Api\CuotaController::class, 'byProspecto']);
    Route::get('/estudiante-programa/{id}/cuotas', [\App\Http\Controllers\Api\CuotaController::class, 'byPrograma']);

    Route::get('/kardex-pagos', [\App\Http\Controllers\Api\KardexPagoController::class, 'index']);
    Route::post('/kardex-pagos', [\App\Http\Controllers\Api\KardexPagoController::class, 'store']);

    // Collection Logs
    Route::get('/collection-logs', [CollectionLogController::class, 'index']);
    Route::post('/collection-logs', [CollectionLogController::class, 'store']);
    Route::get('/collection-logs/{id}', [CollectionLogController::class, 'show']);
    Route::put('/collection-logs/{id}', [CollectionLogController::class, 'update']);
    Route::delete('/collection-logs/{id}', [CollectionLogController::class, 'destroy']);

});
