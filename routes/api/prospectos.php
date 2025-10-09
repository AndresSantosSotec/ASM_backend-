<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProspectoController;
use App\Http\Controllers\Api\ProspectosImportController;
use App\Http\Controllers\Api\ProspectosDocumentoController;
use App\Http\Controllers\Api\ContactoEnviadoController;
use App\Http\Controllers\Api\DuplicateRecordController;
use App\Http\Controllers\Api\ColumnConfigurationController;
use App\Http\Controllers\Api\CorreoController;

/**
 * ==========================================
 * PROSPECTOS - Gestión de Leads y Seguimiento
 * ==========================================
 * Todas las rutas requieren autenticación (auth:sanctum)
 */

Route::middleware('auth:sanctum')->group(function () {

    // ----------------------
    // Prospectos - CRUD y Gestión
    // ----------------------
    Route::prefix('prospectos')->group(function () {
        // CRUD básico
        Route::get('/', [ProspectoController::class, 'index']);
        Route::post('/', [ProspectoController::class, 'store']);
        
        // Funciones adicionales fijas (bulk operations)
        Route::put('/bulk-assign', [ProspectoController::class, 'bulkAssign']);
        Route::put('/bulk-update-status', [ProspectoController::class, 'bulkUpdateStatus']);
        Route::delete('/bulk-delete', [ProspectoController::class, 'bulkDelete']);
        
        // Filtros y consultas especiales
        Route::get('/status/{status}', [ProspectoController::class, 'filterByStatus']);
        Route::get('/fichas/pendientes', [ProspectoController::class, 'pendientesAprobacion']);
        Route::get('pendientes-con-docs', [ProspectoController::class, 'pendientesConDocs']);
        Route::get('inscritos-with-courses', [ProspectoController::class, 'inscritosConCursos']);
        
        // Operaciones sobre prospecto individual (con restricción numérica)
        Route::get('/{id}', [ProspectoController::class, 'show'])->where('id', '[0-9]+');
        Route::put('/{id}', [ProspectoController::class, 'update'])->where('id', '[0-9]+');
        Route::delete('/{id}', [ProspectoController::class, 'destroy'])->where('id', '[0-9]+');
        Route::put('/{id}/status', [ProspectoController::class, 'updateStatus'])->where('id', '[0-9]+');
        Route::put('/{id}/assign', [ProspectoController::class, 'assignOne'])->where('id', '[0-9]+');
        Route::post('/{id}/enviar-contrato', [ProspectoController::class, 'enviarContrato'])->where('id', '[0-9]+');
        Route::get('/{id}/download-contrato', [ProspectoController::class, 'downloadContrato'])->where('id', '[0-9]+');
    });

    // ----------------------
    // Documentos de Prospectos
    // ----------------------
    Route::prefix('documentos')->group(function () {
        Route::get('/', [ProspectosDocumentoController::class, 'index']);
        Route::post('/', [ProspectosDocumentoController::class, 'store']);
        Route::get('/{id}', [ProspectosDocumentoController::class, 'show']);
        Route::put('/{id}', [ProspectosDocumentoController::class, 'update']);
        Route::delete('/{id}', [ProspectosDocumentoController::class, 'destroy']);
        Route::get('/{id}/file', [ProspectosDocumentoController::class, 'download']);
        Route::get('/prospecto/{prospectoId}', [ProspectosDocumentoController::class, 'documentosPorProspecto']);
    });

    // ----------------------
    // Importación de Prospectos
    // ----------------------
    Route::post('/import', [ProspectosImportController::class, 'uploadExcel'])->name('prospectos.import');

    // ----------------------
    // Configuración de Columnas para Importación
    // ----------------------
    Route::prefix('columns')->group(function () {
        Route::get('/', [ColumnConfigurationController::class, 'index'])->name('prospectos.columns.index');
        Route::post('/', [ColumnConfigurationController::class, 'store'])->name('prospectos.columns.store');
        Route::put('/{id}', [ColumnConfigurationController::class, 'update'])->name('prospectos.columns.update');
        Route::delete('/{id}', [ColumnConfigurationController::class, 'destroy'])->name('prospectos.columns.destroy');
    });

    // ----------------------
    // Contactos Enviados
    // ----------------------
    Route::apiResource('contactos-enviados', ContactoEnviadoController::class);
    Route::get('prospectos/{prospecto}/contactos-enviados', [ContactoEnviadoController::class, 'byProspecto']);
    Route::get('contactos-enviados/{id}/download-contrato', [ContactoEnviadoController::class, 'downloadContrato']);
    Route::get('/contactos-enviados/today', [ContactoEnviadoController::class, 'today']);

    // ----------------------
    // Envío de Correos
    // ----------------------
    Route::post('/enviar-correo', [CorreoController::class, 'enviar']);

    // ----------------------
    // Detección de Duplicados
    // ----------------------
    Route::prefix('duplicates')->group(function () {
        Route::get('/', [DuplicateRecordController::class, 'index']);
        Route::post('/detect', [DuplicateRecordController::class, 'detect']);
        Route::post('/{duplicate}/action', [DuplicateRecordController::class, 'action'])->where('duplicate', '[0-9]+');
        Route::post('/bulk-action', [DuplicateRecordController::class, 'bulkAction']);
    });
});

// ----------------------
// Rutas públicas de prospectos
// ----------------------
Route::get('/prospectos/fichas/pendientes-public', [ProspectoController::class, 'pendientesAprobacion']);
