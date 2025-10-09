<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReportsController;
use App\Http\Controllers\Api\ReconciliationController;
use App\Http\Controllers\Api\RuleController;
use App\Http\Controllers\Api\CollectionLogController;
use App\Http\Controllers\Api\PaymentRuleNotificationController;
use App\Http\Controllers\Api\PaymentRuleBlockingRuleController;
use App\Http\Controllers\Api\PaymentExceptionCategoryController;
use App\Http\Controllers\Api\PaymentGatewayController;
use App\Http\Controllers\Api\EstudiantePagosController;
use App\Http\Controllers\Api\DashboardFinancieroController;
use App\Http\Controllers\Api\GestionPagosController;

/**
 * ==========================================
 * FINANCIERO - Pagos, Facturas, Conciliación
 * ==========================================
 * Todas las rutas requieren autenticación (auth:sanctum)
 */

Route::middleware('auth:sanctum')->group(function () {

    // ----------------------
    // Dashboard Financiero
    // ----------------------
    Route::get('/dashboard-financiero', [DashboardFinancieroController::class, 'index'])->name('dashboard.financiero');

    // ----------------------
    // Facturas
    // ----------------------
    Route::get('/invoices', [InvoiceController::class, 'index']);
    Route::post('/invoices', [InvoiceController::class, 'store']);
    Route::put('/invoices/{invoice}', [InvoiceController::class, 'update']);
    Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy']);

    // ----------------------
    // Pagos
    // ----------------------
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::post('/payments', [PaymentController::class, 'store']);

    // ----------------------
    // Reglas de Pago
    // ----------------------
    Route::get('/payment-rules', [RuleController::class, 'index']);
    Route::post('/payment-rules', [RuleController::class, 'store']);
    Route::get('/payment-rules/{rule}', [RuleController::class, 'show']);
    Route::put('/payment-rules/{rule}', [RuleController::class, 'update']);
    Route::get('/payment-rules-current', [RuleController::class, 'current']);

    // ----------------------
    // Notificaciones de Reglas de Pago
    // ----------------------
    Route::prefix('payment-rules/{rule}/notifications')->group(function () {
        Route::get('/', [PaymentRuleNotificationController::class, 'index']);
        Route::post('/', [PaymentRuleNotificationController::class, 'store']);
        Route::get('/{notification}', [PaymentRuleNotificationController::class, 'show']);
        Route::put('/{notification}', [PaymentRuleNotificationController::class, 'update']);
        Route::delete('/{notification}', [PaymentRuleNotificationController::class, 'destroy']);
    });

    // ----------------------
    // Reglas de Bloqueo
    // ----------------------
    Route::prefix('payment-rules/{rule}/blocking-rules')->group(function () {
        Route::get('/', [PaymentRuleBlockingRuleController::class, 'index']);
        Route::post('/', [PaymentRuleBlockingRuleController::class, 'store']);
        Route::get('/{blockingRule}', [PaymentRuleBlockingRuleController::class, 'show']);
        Route::put('/{blockingRule}', [PaymentRuleBlockingRuleController::class, 'update']);
        Route::delete('/{blockingRule}', [PaymentRuleBlockingRuleController::class, 'destroy']);
        Route::patch('/{blockingRule}/toggle-status', [PaymentRuleBlockingRuleController::class, 'toggleStatus']);
    });
    Route::get('/payment-rules/{rule}/blocking-rules/applicable', [PaymentRuleBlockingRuleController::class, 'getApplicableRules']);

    // ----------------------
    // Pasarelas de Pago
    // ----------------------
    Route::prefix('payment-gateways')->group(function () {
        Route::get('/', [PaymentGatewayController::class, 'index']);
        Route::post('/', [PaymentGatewayController::class, 'store']);
        Route::get('/active', [PaymentGatewayController::class, 'activeGateways']);
        Route::get('/{gateway}', [PaymentGatewayController::class, 'show']);
        Route::put('/{gateway}', [PaymentGatewayController::class, 'update']);
        Route::delete('/{gateway}', [PaymentGatewayController::class, 'destroy']);
        Route::patch('/{gateway}/toggle-status', [PaymentGatewayController::class, 'toggleStatus']);
    });

    // ----------------------
    // Categorías de Excepción
    // ----------------------
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

    // ----------------------
    // Reportes Financieros
    // ----------------------
    Route::get('/reports/summary', [ReportsController::class, 'summary']);
    Route::get('/reports/export', [ReportsController::class, 'export']);
    Route::get('/financial-reports', [ReportsController::class, 'index']);

    // Financial reports (alias legacy)
    Route::get('/finance/collections', [GestionPagosController::class, 'index']);

    // ----------------------
    // Conciliación / Reconciliation
    // ----------------------
    Route::prefix('reconciliation')->group(function () {
        Route::post('/upload', [ReconciliationController::class, 'upload']);
        Route::get('/pending', [ReconciliationController::class, 'pending']);
        Route::post('/process', [ReconciliationController::class, 'process']);
    });

    // Rutas con prefijo "conciliacion" (mantener compatibilidad)
    Route::prefix('conciliacion')->group(function () {
        Route::post('/import', [ReconciliationController::class, 'import']);
        Route::get('/template', [ReconciliationController::class, 'downloadTemplate']);
        Route::get('/export', [ReconciliationController::class, 'export']);
        Route::get('/pendientes-desde-kardex', [ReconciliationController::class, 'kardexNoConciliados']);
        Route::get('/conciliados-desde-kardex', [ReconciliationController::class, 'kardexConciliados']);
        Route::post('/import-kardex', [ReconciliationController::class, 'ImportarPagosKardex']);
        Route::post('/preview', [ReconciliationController::class, 'preview']);
        Route::post('/confirm', [ReconciliationController::class, 'confirm']);
        Route::post('/reject', [ReconciliationController::class, 'reject']);
    });

    // ----------------------
    // Bitácoras de Cobranza
    // ----------------------
    Route::prefix('collection-logs')->group(function () {
        Route::get('/', [CollectionLogController::class, 'index']);
        Route::post('/', [CollectionLogController::class, 'store']);
        Route::get('/{id}', [CollectionLogController::class, 'show']);
        Route::put('/{id}', [CollectionLogController::class, 'update']);
        Route::delete('/{id}', [CollectionLogController::class, 'destroy']);
    });

    // ----------------------
    // Gestión de Cobranzas
    // ----------------------
    Route::prefix('collections')->group(function () {
        Route::get('/late-payments', [GestionPagosController::class, 'latePayments']);
        Route::get('/students/{epId}/snapshot', [GestionPagosController::class, 'studentSnapshot'])->whereNumber('epId');
        Route::get('/payment-plans', [GestionPagosController::class, 'paymentPlansOverview']);
        Route::post('/payment-plans/preview', [GestionPagosController::class, 'previewPaymentPlan']);
        Route::post('/payment-plans', [GestionPagosController::class, 'createPaymentPlan']);
    });
    Route::get('/finance/collections', [GestionPagosController::class, 'index']);

    // ----------------------
    // Kardex y Cuotas
    // ----------------------
    Route::get('/kardex-pagos', [\App\Http\Controllers\Api\KardexPagoController::class, 'index']);
    Route::post('/kardex-pagos', [\App\Http\Controllers\Api\KardexPagoController::class, 'store']);
    Route::get('/prospectos/{id}/cuotas', [\App\Http\Controllers\Api\CuotaController::class, 'byProspecto']);
    Route::get('/estudiante-programa/{id}/cuotas', [\App\Http\Controllers\Api\CuotaController::class, 'byPrograma']);

    // ----------------------
    // Pagos de Estudiante (Portal del Estudiante)
    // ----------------------
    Route::prefix('estudiante/pagos')->group(function () {
        Route::get('/pendientes', [EstudiantePagosController::class, 'pagosPendientes']);
        Route::get('/historial', [EstudiantePagosController::class, 'historialPagos']);
        Route::get('/estado-cuenta', [EstudiantePagosController::class, 'estadoCuenta']);
        Route::post('/subir-recibo', [EstudiantePagosController::class, 'subirReciboPago']);
        Route::post('/prevalidar-recibo', [EstudiantePagosController::class, 'prevalidarRecibo'])->name('prevalidar_recibo');
    });

    // ----------------------
    // Rules API Resource (Legacy)
    // ----------------------
    Route::apiResource('rules', RuleController::class);
});
