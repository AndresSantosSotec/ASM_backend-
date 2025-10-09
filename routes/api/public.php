<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\PlanPagosController;
use App\Http\Controllers\InscripcionController;
use App\Http\Controllers\Api\ProspectoController;
use App\Http\Controllers\Api\EmailController;
use App\Http\Controllers\Api\AdminEstudiantePagosController;

/**
 * ==========================================
 * RUTAS PÚBLICAS (Sin autenticación)
 * ==========================================
 */

// ----------------------
// Health Check y Status
// ----------------------
Route::get('/health', function () {
    try {
        DB::connection()->getPdo();

        $basic = [
            'status' => 'API is healthy',
            'ok'     => true,
            'time'   => now()->toDateTimeString(),
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

// ----------------------
// Autenticación
// ----------------------
Route::post('/login', [LoginController::class, 'login'])->name('login');

// ----------------------
// Inscripciones y Pagos (Público)
// ----------------------
Route::post('/plan-pagos/generar', [PlanPagosController::class, 'generar']);
Route::post('/inscripciones/finalizar', [InscripcionController::class, 'finalizar']);

// ----------------------
// Prospectos (Consulta Pública)
// ----------------------
Route::get('/prospectos/{id}', [ProspectoController::class, 'show']);

// ----------------------
// Fichas (Solo IDs numéricos y con autenticación)
// ----------------------
Route::get('/fichas/{id}', [InscripcionController::class, 'show'])
    ->middleware('auth:sanctum')
    ->where('id', '[0-9]+');

// ----------------------
// Email (Público)
// ----------------------
Route::post('/emails/send', [EmailController::class, 'send']);
Route::post('/emails/send-queued', [EmailController::class, 'sendQueued']);

// ----------------------
// Admin Prospectos (Público)
// ----------------------
Route::prefix('admin')->group(function () {
    Route::get('/prospectos', [AdminEstudiantePagosController::class, 'index']);
    Route::get('/prospectos/{id}/estado-cuenta', [AdminEstudiantePagosController::class, 'estadoCuenta']);
    Route::get('/prospectos/{id}/historial', [AdminEstudiantePagosController::class, 'historial']);
});
