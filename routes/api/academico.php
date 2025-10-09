<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProgramaController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\EstudianteProgramaController;
use App\Http\Controllers\Api\RankingController;
use App\Http\Controllers\Api\CoursePerformanceController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\MoodleConsultasController;
use App\Http\Controllers\Api\ConvenioController;
use App\Http\Controllers\Api\UbicacionController;
use App\Http\Controllers\PriceController;
use App\Http\Controllers\Api\PeriodoInscripcionController;
use App\Http\Controllers\Api\InscripcionPeriodoController;
use App\Http\Controllers\Api\ApprovalFlowController;
use App\Http\Controllers\Api\ApprovalStageController;

/**
 * ==========================================
 * ACADÉMICO - Programas, Cursos, Estudiantes
 * ==========================================
 */

// ----------------------
// Programas Académicos
// ----------------------
Route::prefix('programas')->group(function () {
    Route::get('/', [ProgramaController::class, 'ObtenerProgramas']);
    Route::post('/', [ProgramaController::class, 'CretatePrograma']);
    Route::put('/{id}', [ProgramaController::class, 'UpdatePrograma']);
    Route::delete('/{id}', [ProgramaController::class, 'deletePrograma']);
    Route::get('/{programaId}/precios', [ProgramaController::class, 'obtenerPreciosPrograma']);
    Route::put('/{programaId}/precios', [ProgramaController::class, 'actualizarPrecioPrograma']);
});

// ----------------------
// Ubicación
// ----------------------
Route::get('/ubicacion/{paisId}', [UbicacionController::class, 'getUbicacionByPais']);

// ----------------------
// Precios
// ----------------------
Route::get('precios/programa/{programa}', [PriceController::class, 'porPrograma']);
Route::get('precios/convenio/{convenio}/{programa}', [PriceController::class, 'porConvenio']);

// ----------------------
// Convenios
// ----------------------
Route::apiResource('convenios', ConvenioController::class);

// ----------------------
// Periodos de Inscripción
// ----------------------
Route::apiResource('periodos', PeriodoInscripcionController::class);
Route::apiResource('periodos.inscripciones', InscripcionPeriodoController::class)->shallow();

// ----------------------
// Rutas Protegidas (Requieren auth:sanctum)
// ----------------------
Route::middleware('auth:sanctum')->group(function () {

    // ----------------------
    // Cursos - Gestión y Operaciones
    // ----------------------
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

        // Rutas REST estándar show/update/delete (con restricción numérica)
        Route::get('/{course}', [CourseController::class, 'show'])->whereNumber('course');
        Route::put('/{course}', [CourseController::class, 'update'])->whereNumber('course');
        Route::delete('/{course}', [CourseController::class, 'destroy'])->whereNumber('course');
    });

    // ----------------------
    // Estudiantes Programa
    // ----------------------
    Route::prefix('estudiante-programa')->group(function () {
        // Primero la ruta estática
        Route::get('/all', [EstudianteProgramaController::class, 'getProgramas']);

        // Luego las dinámicas con restricción numérica
        Route::get('/{id}', [EstudianteProgramaController::class, 'show'])->whereNumber('id');
        Route::get('/{id}/with-courses', [EstudianteProgramaController::class, 'getProgramasConCursos'])->whereNumber('id');
        Route::post('/', [EstudianteProgramaController::class, 'store']);
        Route::put('/{id}', [EstudianteProgramaController::class, 'update'])->whereNumber('id');
        Route::delete('/{id}', [EstudianteProgramaController::class, 'destroy'])->whereNumber('id');

        // Query param version
        Route::get('/', [EstudianteProgramaController::class, 'getProgramasProspecto']);
    });

    // ----------------------
    // Importación de Estudiantes
    // ----------------------
    Route::prefix('estudiantes')->group(function () {
        Route::post('import', [\App\Http\Controllers\Api\EstudiantesImportController::class, 'uploadExcel'])
            ->name('estudiantes.import');
    });

    // ----------------------
    // Estudiantes - Listado
    // ----------------------
    Route::get('/students', [StudentController::class, 'index']);
    Route::get('/students/{id}', [StudentController::class, 'show']);

    // ----------------------
    // Ranking y Rendimiento
    // ----------------------
    Route::get('/ranking/students', [RankingController::class, 'index']);
    Route::get('/ranking/courses', [CoursePerformanceController::class, 'index']);
    Route::get('/ranking/report', [RankingController::class, 'report']);

    // ----------------------
    // Moodle - Consultas
    // ----------------------
    Route::get('/moodle/consultas/{carnet?}', [MoodleConsultasController::class, 'cursosPorCarnet']);
    Route::get('/moodle/consultas/aprobados/{carnet?}', [MoodleConsultasController::class, 'cursosAprobados']);
    Route::get('/moodle/consultas/reprobados/{carnet?}', [MoodleConsultasController::class, 'cursosReprobados']);
    Route::get('/moodle/consultas/estatus/{carnet?}', [MoodleConsultasController::class, 'estatusAcademico']);
    Route::get('/moodle/consultas', [MoodleConsultasController::class, 'cursosPorCarnet']);
    Route::get('/moodle/programacion-cursos', [MoodleConsultasController::class, 'programacionCursos']);

    // ----------------------
    // Flujos de Aprobación
    // ----------------------
    Route::prefix('approval-flows')->group(function () {
        Route::get('/', [ApprovalFlowController::class, 'index']);
        Route::post('/', [ApprovalFlowController::class, 'store']);
        Route::get('{flow}', [ApprovalFlowController::class, 'show']);
        Route::put('{flow}', [ApprovalFlowController::class, 'update']);
        Route::delete('{flow}', [ApprovalFlowController::class, 'destroy']);
        Route::post('{flow}/toggle', [ApprovalFlowController::class, 'toggle']);

        // Etapas anidadas
        Route::post('{flow}/stages', [ApprovalStageController::class, 'store']);
        Route::put('stages/{stage}', [ApprovalStageController::class, 'update']);
        Route::delete('stages/{stage}', [ApprovalStageController::class, 'destroy']);
    });
});
