<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Importar controladores
use App\Http\Controllers\Api\ProspectoController;
use App\Http\Controllers\Api\ProgramaController;
use App\Http\Controllers\Api\UbicacionController;
use App\Http\Controllers\Api\RolController;
use App\Http\Controllers\Api\RolePermissionController;
use App\Http\Controllers\Api\PermissionController;
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
use App\Http\Controllers\Api\ReportsController;
use App\Http\Controllers\Api\ReconciliationController;
use App\Http\Controllers\Api\RuleController;
use App\Http\Controllers\Api\CollectionLogController;
use App\Http\Controllers\Api\PaymentRuleNotificationController;
use App\Http\Controllers\Api\MoodleConsultasController;
use App\Http\Controllers\Api\PaymentRuleBlockingRuleController;
use App\Http\Controllers\Api\PaymentExceptionCategoryController;
use App\Http\Controllers\Api\PaymentGatewayController;
use App\Http\Controllers\Api\EstudiantePagosController;
use App\Http\Controllers\Api\DashboardFinancieroController;
use App\Http\Controllers\Api\EmailController;
use App\Http\Controllers\Api\AdminEstudiantePagosController;



use App\Http\Controllers\Api\GestionPagosController;


use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Rutas Públicas
|--------------------------------------------------------------------------
| Rutas accesibles sin autenticación
*/

// ============================================
// Health Check y Status (consolidados)
// ============================================
Route::get('/health', function () {
    try {
        DB::connection()->getPdo();

        $basic = [
            'status' => 'API is healthy',
            'ok'     => true,
            'time'   => now()->toDateTimeString(),
            'version'=> '1.0.0',
        ];

        // Entorno de desarrollo: información ampliada (no secretos)
        if (app()->environment(['local', 'testing'])) {
            $basic['meta'] = [
                'app'     => config('app.name'),
                'db'      => [
                    'connected' => true,
                ],
            ];

            Log::info('Healthcheck successful (dev)', [
                'env' => app()->environment(),
                'ok'  => true,
            ]);
        } else {
            // Producción: mínima y segura
            Log::info('Healthcheck successful', ['ok' => true]);
        }

        return response()->json($basic);
    } catch (\Throwable $e) {
        Log::error('Healthcheck failed', ['error' => $e->getMessage()]);

        return response()->json([
            'status' => 'API is unhealthy',
            'ok'     => false,
            'time'   => now()->toDateTimeString(),
        ], 500);
    }
});

// Alias para health check
Route::get('/ping', fn() => response()->json(['message' => 'pong!', 'ok' => true]));

// ============================================
// Emails Públicos
// ============================================
Route::post('/emails/send', [EmailController::class, 'send']);
Route::post('/emails/send-queued', [EmailController::class, 'sendQueued']);

// ============================================
// Consultas Públicas Admin
// ============================================
Route::prefix('admin')->group(function () {
    Route::get('/prospectos', [AdminEstudiantePagosController::class, 'index']);
    Route::get('/prospectos/{id}/estado-cuenta', [AdminEstudiantePagosController::class, 'estadoCuenta']);
    Route::get('/prospectos/{id}/historial', [AdminEstudiantePagosController::class, 'historial']);
});


// ============================================
// Autenticación
// ============================================
Route::post('/login', [LoginController::class, 'login'])->name('login');
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth:sanctum');

// ============================================
// Consultas Públicas de Prospectos
// ============================================
Route::get('/prospectos/{id}', [ProspectoController::class, 'show']);
Route::get('/prospectos/fichas/pendientes-public', [ProspectoController::class, 'pendientesAprobacion']);

// ============================================
// Inscripciones (Públicas - considerar mover a protegidas)
// ============================================
Route::post('/plan-pagos/generar', [PlanPagosController::class, 'generar']);
Route::post('/inscripciones/finalizar', [InscripcionController::class, 'finalizar']);

// Consulta de ficha con autenticación
Route::get('/fichas/{id}', [InscripcionController::class, 'show'])
    ->middleware('auth:sanctum')
    ->where('id', '[0-9]+');

/*
|--------------------------------------------------------------------------
| Rutas Protegidas (auth:sanctum requerido)
|--------------------------------------------------------------------------
| Todas las rutas dentro de este grupo requieren autenticación
*/
Route::middleware('auth:sanctum')->group(function () {

    // ============================================
    // Usuario Autenticado
    // ============================================
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // ============================================
    // DOMINIO: PROSPECTOS Y SEGUIMIENTO
    // ============================================
    
    // Prospectos - CRUD y funciones especiales
    Route::prefix('prospectos')->group(function () {
        Route::get('/', [ProspectoController::class, 'index']);
        Route::post('/', [ProspectoController::class, 'store']);
        
        // Acciones masivas
        Route::put('/bulk-assign', [ProspectoController::class, 'bulkAssign']);
        Route::put('/bulk-update-status', [ProspectoController::class, 'bulkUpdateStatus']);
        Route::delete('/bulk-delete', [ProspectoController::class, 'bulkDelete']);
        
        // Consultas especiales
        Route::get('/status/{status}', [ProspectoController::class, 'filterByStatus']);
        Route::get('/fichas/pendientes', [ProspectoController::class, 'pendientesAprobacion']);
        Route::get('/pendientes-con-docs', [ProspectoController::class, 'pendientesConDocs']);
        Route::get('/inscritos-with-courses', [ProspectoController::class, 'inscritosConCursos']);
        
        // Rutas individuales (requieren ID numérico)
        Route::get('/{id}', [ProspectoController::class, 'show'])->where('id', '[0-9]+');
        Route::put('/{id}', [ProspectoController::class, 'update'])->where('id', '[0-9]+');
        Route::delete('/{id}', [ProspectoController::class, 'destroy'])->where('id', '[0-9]+');
        Route::put('/{id}/status', [ProspectoController::class, 'updateStatus'])->where('id', '[0-9]+');
        Route::put('/{id}/assign', [ProspectoController::class, 'assignOne'])->where('id', '[0-9]+');
        Route::post('/{id}/enviar-contrato', [ProspectoController::class, 'enviarContrato'])->where('id', '[0-9]+');
        Route::get('/{id}/download-contrato', [ProspectoController::class, 'downloadContrato'])->where('id', '[0-9]+');
    });

    // Importación de prospectos
    Route::post('/import', [ProspectosImportController::class, 'uploadExcel'])->name('prospectos.import');

    // Documentos de prospectos
    Route::prefix('documentos')->group(function () {
        Route::get('/', [ProspectosDocumentoController::class, 'index']);
        Route::post('/', [ProspectosDocumentoController::class, 'store']);
        Route::get('/{id}', [ProspectosDocumentoController::class, 'show']);
        Route::put('/{id}', [ProspectosDocumentoController::class, 'update']);
        Route::delete('/{id}', [ProspectosDocumentoController::class, 'destroy']);
        Route::get('/{id}/file', [ProspectosDocumentoController::class, 'download']);
        Route::get('/prospecto/{prospectoId}', [ProspectosDocumentoController::class, 'documentosPorProspecto']);
    });

    // Configuración de columnas (Prospectos)
    Route::prefix('columns')->group(function () {
        Route::get('/', [ColumnConfigurationController::class, 'index'])->name('prospectos.columns.index');
        Route::post('/', [ColumnConfigurationController::class, 'store'])->name('prospectos.columns.store');
        Route::put('/{id}', [ColumnConfigurationController::class, 'update'])->name('prospectos.columns.update');
        Route::delete('/{id}', [ColumnConfigurationController::class, 'destroy'])->name('prospectos.columns.destroy');
    });

    // Duplicados
    Route::prefix('duplicates')->group(function () {
        Route::get('/', [DuplicateRecordController::class, 'index']);
        Route::post('/detect', [DuplicateRecordController::class, 'detect']);
        Route::post('/{duplicate}/action', [DuplicateRecordController::class, 'action'])->where('duplicate', '[0-9]+');
        Route::post('/bulk-action', [DuplicateRecordController::class, 'bulkAction']);
    });

    // Actividades
    Route::prefix('actividades')->group(function () {
        Route::get('/', [ActividadesController::class, 'index']);
        Route::get('/{id}', [ActividadesController::class, 'show']);
        Route::post('/', [ActividadesController::class, 'store']);
        Route::put('/{id}', [ActividadesController::class, 'update']);
        Route::delete('/{id}', [ActividadesController::class, 'destroy']);
    });

    // Citas
    Route::prefix('citas')->group(function () {
        Route::get('/', [CitasController::class, 'index']);
        Route::get('/{id}', [CitasController::class, 'show']);
        Route::post('/', [CitasController::class, 'store']);
        Route::put('/{id}', [CitasController::class, 'update']);
        Route::delete('/{id}', [CitasController::class, 'destroy']);
    });

    // Interacciones
    Route::prefix('interacciones')->group(function () {
        Route::get('/', [InteraccionesController::class, 'index']);
        Route::get('/{id}', [InteraccionesController::class, 'show']);
        Route::post('/', [InteraccionesController::class, 'store']);
        Route::put('/{id}', [InteraccionesController::class, 'update']);
        Route::delete('/{id}', [InteraccionesController::class, 'destroy']);
    });

    // Tareas
    Route::prefix('tareas')->group(function () {
        Route::get('/', [TareasGenController::class, 'index']);
        Route::get('/{id}', [TareasGenController::class, 'show']);
        Route::post('/', [TareasGenController::class, 'store']);
        Route::put('/{id}', [TareasGenController::class, 'update']);
        Route::delete('/{id}', [TareasGenController::class, 'destroy']);
    });

    // Correos
    Route::post('/enviar-correo', [CorreoController::class, 'enviar']);

    // Comisiones
    Route::prefix('commissions')->group(function () {
        // Configuración global
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

    // ============================================
    // DOMINIO: ACADÉMICO
    // ============================================
    
    // Importación de estudiantes
    Route::prefix('estudiantes')->group(function () {
        Route::post('/import', [\App\Http\Controllers\Api\EstudiantesImportController::class, 'uploadExcel'])
            ->name('estudiantes.import');
    });

    // Programas académicos
    Route::prefix('programas')->group(function () {
        Route::get('/', [ProgramaController::class, 'ObtenerProgramas']);
        Route::post('/', [ProgramaController::class, 'CretatePrograma']);
        Route::put('/{id}', [ProgramaController::class, 'UpdatePrograma']);
        Route::delete('/{id}', [ProgramaController::class, 'deletePrograma']);
        Route::get('/{programaId}/precios', [ProgramaController::class, 'obtenerPreciosPrograma']);
        Route::put('/{programaId}/precios', [ProgramaController::class, 'actualizarPrecioPrograma']);
    });

    // Estudiante-Programa
    Route::prefix('estudiante-programa')->group(function () {
        Route::get('/', [EstudianteProgramaController::class, 'getProgramasProspecto']);
        Route::get('/all', [EstudianteProgramaController::class, 'getProgramas']);
        Route::get('/{id}', [EstudianteProgramaController::class, 'show'])->whereNumber('id');
        Route::get('/{id}/with-courses', [EstudianteProgramaController::class, 'getProgramasConCursos'])->whereNumber('id');
        Route::post('/', [EstudianteProgramaController::class, 'store']);
        Route::put('/{id}', [EstudianteProgramaController::class, 'update'])->whereNumber('id');
        Route::delete('/{id}', [EstudianteProgramaController::class, 'destroy'])->whereNumber('id');
    });

    // Cursos
    Route::prefix('courses')->group(function () {
        // Rutas estáticas primero
        Route::get('/available-for-students', [CourseController::class, 'getAvailableCourses']);
        Route::post('/assign', [CourseController::class, 'assignCourses']);
        Route::post('/unassign', [CourseController::class, 'unassignCourses']);
        Route::post('/bulk-assign', [CourseController::class, 'bulkAssignCourses']);
        Route::post('/bulk-sync-moodle', [CourseController::class, 'bulkSyncToMoodle']);
        Route::post('/by-programs', [CourseController::class, 'byPrograms']);

        // Listar y crear
        Route::get('/', [CourseController::class, 'index']);
        Route::post('/', [CourseController::class, 'store']);

        // Rutas de acción sobre un curso existente
        Route::post('/{course}/approve', [CourseController::class, 'approve']);
        Route::post('/{course}/sync-moodle', [CourseController::class, 'syncToMoodle']);
        Route::post('/{course}/assign-facilitator', [CourseController::class, 'assignFacilitator']);

        // Rutas REST estándar con restricción numérica
        Route::get('/{course}', [CourseController::class, 'show'])->whereNumber('course');
        Route::put('/{course}', [CourseController::class, 'update'])->whereNumber('course');
        Route::delete('/{course}', [CourseController::class, 'destroy'])->whereNumber('course');
    });

    // Estudiantes
    Route::get('/students', [StudentController::class, 'index']);
    Route::get('/students/{id}', [StudentController::class, 'show']);

    // Ranking y rendimiento
    Route::get('/ranking/students', [RankingController::class, 'index']);
    Route::get('/ranking/courses', [CoursePerformanceController::class, 'index']);
    Route::get('/ranking/report', [RankingController::class, 'report']);

    // Moodle
    Route::prefix('moodle')->group(function () {
        Route::get('/consultas/{carnet?}', [MoodleConsultasController::class, 'cursosPorCarnet']);
        Route::get('/consultas/aprobados/{carnet?}', [MoodleConsultasController::class, 'cursosAprobados']);
        Route::get('/consultas/reprobados/{carnet?}', [MoodleConsultasController::class, 'cursosReprobados']);
        Route::get('/consultas/estatus/{carnet?}', [MoodleConsultasController::class, 'estatusAcademico']);
        Route::get('/programacion-cursos', [MoodleConsultasController::class, 'programacionCursos']);
    });

    // ============================================
    // DOMINIO: FINANCIERO
    // ============================================
    
    // Dashboard financiero
    Route::get('/dashboard-financiero', [DashboardFinancieroController::class, 'index'])->name('dashboard.financiero');

    // Conciliación bancaria (estandarizado a 'conciliacion')
    Route::prefix('conciliacion')->group(function () {
        Route::post('/import', [ReconciliationController::class, 'import']);
        Route::post('/import-kardex', [ReconciliationController::class, 'ImportarPagosKardex']);
        Route::get('/template', [ReconciliationController::class, 'downloadTemplate']);
        Route::get('/export', [ReconciliationController::class, 'export']);
        Route::get('/pendientes-desde-kardex', [ReconciliationController::class, 'kardexNoConciliados']);
        Route::get('/conciliados-desde-kardex', [ReconciliationController::class, 'kardexConciliados']);
    });

    // Facturas
    Route::prefix('invoices')->group(function () {
        Route::get('/', [InvoiceController::class, 'index']);
        Route::post('/', [InvoiceController::class, 'store']);
        Route::put('/{invoice}', [InvoiceController::class, 'update']);
        Route::delete('/{invoice}', [InvoiceController::class, 'destroy']);
    });

    // Pagos
    Route::prefix('payments')->group(function () {
        Route::get('/', [PaymentController::class, 'index']);
        Route::post('/', [PaymentController::class, 'store']);
    });

    // Cuotas
    Route::get('/prospectos/{id}/cuotas', [\App\Http\Controllers\Api\CuotaController::class, 'byProspecto']);
    Route::get('/estudiante-programa/{id}/cuotas', [\App\Http\Controllers\Api\CuotaController::class, 'byPrograma']);

    // Kardex de pagos
    Route::get('/kardex-pagos', [\App\Http\Controllers\Api\KardexPagoController::class, 'index']);
    Route::post('/kardex-pagos', [\App\Http\Controllers\Api\KardexPagoController::class, 'store']);

    // Reglas de pago
    Route::prefix('payment-rules')->group(function () {
        Route::get('/', [RuleController::class, 'index']);
        Route::post('/', [RuleController::class, 'store']);
        Route::get('/{rule}', [RuleController::class, 'show']);
        Route::put('/{rule}', [RuleController::class, 'update']);

        // Notificaciones
        Route::post('/{rule}/notifications', [PaymentRuleNotificationController::class, 'store']);
        Route::put('/{rule}/notifications/{notification}', [PaymentRuleNotificationController::class, 'update']);
        Route::delete('/{rule}/notifications/{notification}', [PaymentRuleNotificationController::class, 'destroy']);
        Route::get('/{rule}/notifications', [PaymentRuleNotificationController::class, 'index']);
        Route::get('/{rule}/notifications/{notification}', [PaymentRuleNotificationController::class, 'show']);

        // Reglas de bloqueo
        Route::prefix('/{rule}/blocking-rules')->group(function () {
            Route::get('/', [PaymentRuleBlockingRuleController::class, 'index']);
            Route::post('/', [PaymentRuleBlockingRuleController::class, 'store']);
            Route::get('/{blockingRule}', [PaymentRuleBlockingRuleController::class, 'show']);
            Route::put('/{blockingRule}', [PaymentRuleBlockingRuleController::class, 'update']);
            Route::delete('/{blockingRule}', [PaymentRuleBlockingRuleController::class, 'destroy']);
            Route::patch('/{blockingRule}/toggle-status', [PaymentRuleBlockingRuleController::class, 'toggleStatus']);
        });

        Route::get('/{rule}/blocking-rules/applicable', [PaymentRuleBlockingRuleController::class, 'getApplicableRules']);
    });

    // Pasarelas de pago
    Route::prefix('payment-gateways')->group(function () {
        Route::get('/', [PaymentGatewayController::class, 'index']);
        Route::post('/', [PaymentGatewayController::class, 'store']);
        Route::get('/active', [PaymentGatewayController::class, 'activeGateways']);
        Route::get('/{gateway}', [PaymentGatewayController::class, 'show']);
        Route::put('/{gateway}', [PaymentGatewayController::class, 'update']);
        Route::delete('/{gateway}', [PaymentGatewayController::class, 'destroy']);
        Route::patch('/{gateway}/toggle-status', [PaymentGatewayController::class, 'toggleStatus']);
    });

    // Categorías de excepción de pago
    Route::prefix('payment-exception-categories')->group(function () {
        Route::get('/', [PaymentExceptionCategoryController::class, 'index']);
        Route::post('/', [PaymentExceptionCategoryController::class, 'store']);
        Route::get('/{category}', [PaymentExceptionCategoryController::class, 'show']);
        Route::put('/{category}', [PaymentExceptionCategoryController::class, 'update']);
        Route::delete('/{category}', [PaymentExceptionCategoryController::class, 'destroy']);
        Route::patch('/{category}/toggle-status', [PaymentExceptionCategoryController::class, 'toggleStatus']);
        Route::post('/{category}/assign-prospecto', [PaymentExceptionCategoryController::class, 'assignToProspecto']);
        Route::delete('/{category}/remove-prospecto', [PaymentExceptionCategoryController::class, 'removeFromProspecto']);
        Route::get('/{category}/assigned-prospectos', [PaymentExceptionCategoryController::class, 'assignedProspectos']);
        Route::post('/{category}/assign-student', [PaymentExceptionCategoryController::class, 'assignToStudent']);
    });

    // Pagos del estudiante (portal)
    Route::prefix('estudiante/pagos')->group(function () {
        Route::get('/pendientes', [EstudiantePagosController::class, 'pagosPendientes']);
        Route::get('/historial', [EstudiantePagosController::class, 'historialPagos']);
        Route::get('/estado-cuenta', [EstudiantePagosController::class, 'estadoCuenta']);
        Route::post('/subir-recibo', [EstudiantePagosController::class, 'subirReciboPago']);
        Route::post('/prevalidar-recibo', [EstudiantePagosController::class, 'prevalidarRecibo'])->name('prevalidar_recibo');
    });

    // Gestión de pagos (admin/finanzas)
    Route::prefix('collections')->group(function () {
        Route::get('/late-payments', [GestionPagosController::class, 'latePayments']);
        Route::get('/students/{epId}/snapshot', [GestionPagosController::class, 'studentSnapshot'])->whereNumber('epId');
        Route::get('/payment-plans', [GestionPagosController::class, 'paymentPlansOverview']);
        Route::post('/payment-plans/preview', [GestionPagosController::class, 'previewPaymentPlan']);
        Route::post('/payment-plans', [GestionPagosController::class, 'createPaymentPlan']);
    });

    // Logs de cobro
    Route::prefix('collection-logs')->group(function () {
        Route::get('/', [CollectionLogController::class, 'index']);
        Route::post('/', [CollectionLogController::class, 'store']);
        Route::get('/{id}', [CollectionLogController::class, 'show']);
        Route::put('/{id}', [CollectionLogController::class, 'update']);
        Route::delete('/{id}', [CollectionLogController::class, 'destroy']);
    });

    // Reportes financieros
    Route::prefix('reports')->group(function () {
        Route::get('/summary', [ReportsController::class, 'summary']);
        Route::get('/export', [ReportsController::class, 'export']);
    });

    // ============================================
    // DOMINIO: ADMINISTRACIÓN
    // ============================================
    
    // Sesiones
    Route::prefix('sessions')->group(function () {
        Route::get('/', [SessionController::class, 'index']);
        Route::put('/{id}/close', [SessionController::class, 'closeSession']);
        Route::put('/close-all', [SessionController::class, 'closeAllSessions']);
    });

    // Usuarios
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

    // Roles
    Route::prefix('roles')->group(function () {
        Route::get('/', [RolController::class, 'index']);
        Route::get('/{id}', [RolController::class, 'show']);
        Route::post('/', [RolController::class, 'store']);
        Route::put('/{id}', [RolController::class, 'update']);
        Route::delete('/{id}', [RolController::class, 'destroy']);
        Route::get('/{role}/permissions', [RolePermissionController::class, 'index']);
        Route::put('/{role}/permissions', [RolePermissionController::class, 'update']);
    });

    // Permisos
    Route::post('/permissions', [PermissionController::class, 'store']);
    Route::prefix('userpermissions')->group(function () {
        Route::get('/', [UserPermisosController::class, 'index']);
        Route::post('/', [UserPermisosController::class, 'store']);
        Route::put('/{id}', [UserPermisosController::class, 'update']);
        Route::delete('/{id}', [UserPermisosController::class, 'destroy']);
        Route::get('/{user_id}', [UserPermisosController::class, 'getPermissionsByUserId']);
    });

    // Módulos y vistas
    Route::prefix('modules')->group(function () {
        Route::get('/', [ModulesController::class, 'index']);
        Route::post('/', [ModulesController::class, 'store']);
        Route::get('/{id}', [ModulesController::class, 'show']);
        Route::put('/{id}', [ModulesController::class, 'update']);
        Route::delete('/{id}', [ModulesController::class, 'destroy']);

        Route::prefix('{moduleId}/views')->group(function () {
            Route::get('/', [ModulesViewsController::class, 'index']);
            Route::post('/', [ModulesViewsController::class, 'store']);
            Route::get('/{viewId}', [ModulesViewsController::class, 'show']);
            Route::put('/{viewId}', [ModulesViewsController::class, 'update']);
            Route::delete('/{viewId}', [ModulesViewsController::class, 'destroy']);
            Route::put('/views-order', [ModulesViewsController::class, 'updateOrder']);
        });
    });

    // Flujos de aprobación
    Route::prefix('approval-flows')->group(function () {
        Route::get('/', [ApprovalFlowController::class, 'index']);
        Route::post('/', [ApprovalFlowController::class, 'store']);
        Route::get('/{flow}', [ApprovalFlowController::class, 'show']);
        Route::put('/{flow}', [ApprovalFlowController::class, 'update']);
        Route::delete('/{flow}', [ApprovalFlowController::class, 'destroy']);
        Route::post('/{flow}/toggle', [ApprovalFlowController::class, 'toggle']);

        Route::post('/{flow}/stages', [ApprovalStageController::class, 'store']);
        Route::put('/stages/{stage}', [ApprovalStageController::class, 'update']);
        Route::delete('/stages/{stage}', [ApprovalStageController::class, 'destroy']);
    });
});

/*
|--------------------------------------------------------------------------
| Rutas de Recursos Adicionales (Fuera de auth:sanctum principal)
|--------------------------------------------------------------------------
| Algunas rutas que requieren manejos especiales de autenticación
*/

// Periodos de inscripción
Route::apiResource('periodos', PeriodoInscripcionController::class);
Route::apiResource('periodos.inscripciones', InscripcionPeriodoController::class)->shallow();

// Contactos enviados
Route::apiResource('contactos-enviados', ContactoEnviadoController::class);
Route::get('/prospectos/{prospecto}/contactos-enviados', [ContactoEnviadoController::class, 'byProspecto']);
Route::get('/contactos-enviados/{id}/download-contrato', [ContactoEnviadoController::class, 'downloadContrato']);
Route::get('/contactos-enviados/today', [ContactoEnviadoController::class, 'today']);

// Ubicación
Route::get('/ubicacion/{paisId}', [UbicacionController::class, 'getUbicacionByPais']);

// Convenios
Route::apiResource('convenios', ConvenioController::class);

// Precios
Route::get('/precios/programa/{programa}', [PriceController::class, 'porPrograma']);
Route::get('/precios/convenio/{convenio}/{programa}', [PriceController::class, 'porConvenio']);

// Reglas (legacy - considerar consolidar con payment-rules)
Route::apiResource('rules', RuleController::class);
Route::get('/payment-rules-current', [RuleController::class, 'current']);
