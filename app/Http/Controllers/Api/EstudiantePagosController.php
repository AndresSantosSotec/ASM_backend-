<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\CuotaProgramaEstudiante;
use App\Models\KardexPago;
use App\Models\PaymentRequest;
use App\Http\Requests\SubirReciboRequest;
use App\Support\Boletas;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EstudiantePagosController extends Controller
{
    /**
     * Obtiene los pagos pendientes del estudiante autenticado
     */
    public function pagosPendientes()
    {
        $user = Auth::user();

        if (!$user->carnet) {
            return response()->json([
                'message' => 'Usuario sin carnet asignado',
                'data' => []
            ], 400);
        }

        // 🔥 USAR EL ATRIBUTO (accessor) DEL MODELO USER
        $cuotasPendientes = $user->cuotas_pendientes;

        // Separar por categorías
        $now = Carbon::now();
        $inicioMesActual = $now->startOfMonth();
        $finMesProximo = $now->copy()->addMonth()->endOfMonth();

        $pagosMesActualYProximo = $cuotasPendientes->filter(function ($cuota) use ($inicioMesActual, $finMesProximo) {
            $fechaVencimiento = Carbon::parse($cuota->fecha_vencimiento);
            return $fechaVencimiento->between($inicioMesActual, $finMesProximo);
        });

        $pagosAtrasados = $cuotasPendientes->filter(function ($cuota) use ($now) {
            return Carbon::parse($cuota->fecha_vencimiento)->lt($now);
        });

        return response()->json([
            'pagos_mes_actual_proximo' => $pagosMesActualYProximo->values(),
            'pagos_atrasados' => $pagosAtrasados->values(),
            'total_pendiente' => $cuotasPendientes->sum('monto'),
            'total_cuotas_pendientes' => $cuotasPendientes->count()
        ]);
    }

    /**
     * Obtiene el historial de pagos del estudiante
     */
    public function historialPagos()
    {
        $user = Auth::user();

        if (!$user->carnet) {
            return response()->json([
                'message' => 'Usuario sin carnet asignado',
                'data' => []
            ], 400);
        }

        // 🔥 CONSULTA DIRECTA PARA KARDEX_PAGO
        $historialPagos = KardexPago::whereHas('estudiantePrograma.prospecto', function ($query) use ($user) {
            $query->where('carnet', $user->carnet);
        })
            ->with(['cuota', 'estudiantePrograma.programa'])
            ->orderBy('fecha_pago', 'desc')
            ->get();

        return response()->json([
            'historial_pagos' => $historialPagos,
            'total_pagado' => $historialPagos->sum('monto_pagado')
        ]);
    }

    /**
     * Registra un pago con subida de recibo - ROBUSTECIDO
     */
    public function subirReciboPago(SubirReciboRequest $request)
    {
        $user = Auth::user();
        $ipAddress = $request->ip();
        $userAgent = $request->userAgent();

        // Handle idempotency
        $idempotencyKey = $request->header('Idempotency-Key');
        if ($idempotencyKey) {
            $existingRequest = PaymentRequest::where('idempotency_key', $idempotencyKey)->first();
            if ($existingRequest) {
                return response()->json($existingRequest->response_payload, $existingRequest->response_status);
            }
        }

        try {
            return DB::transaction(function () use ($request, $user, $ipAddress, $userAgent, $idempotencyKey) {
                $cuota = $request->cuota_model;
                $boletaNorm = $request->numero_boleta_norm;
                $bancoNorm = $request->banco_norm;
                $fileHash = $request->file_hash;

                // Calculate expected amount (including late fees)
                $expectedAmount = $this->calculateExpectedAmount($cuota);
                $paidAmount = $request->monto;
                $tolerance = config('payment.amount_tolerance', 0.01);
                $amountDifference = abs($expectedAmount - $paidAmount);

                // Determine if payment should be auto-approved
                $shouldAutoApprove = config('payment.auto_approve_exact_amount', true) 
                    && $amountDifference <= $tolerance;

                // Save the file with secure naming
                $archivo = $request->file('comprobante');
                $fileName = $this->generateSecureFileName($user->carnet, $cuota->id, $archivo->getClientOriginalExtension());
                $filePath = $archivo->storeAs('recibos_pago', $fileName, 'public');

                // Create payment record
                $estadoPago = $shouldAutoApprove ? 'aprobado' : 'en_revision';
                $fechaAprobacion = $shouldAutoApprove ? now() : null;
                $aprobadoPor = $shouldAutoApprove ? 'sistema_automatico' : null;

                $pago = KardexPago::create([
                    'estudiante_programa_id' => $cuota->estudiante_programa_id,
                    'cuota_id' => $cuota->id,
                    'fecha_pago' => now(),
                    'monto_pagado' => $paidAmount,
                    'metodo_pago' => 'transferencia_bancaria',
                    'numero_boleta' => $request->numero_boleta,
                    'banco' => $request->banco,
                    'numero_boleta_norm' => $boletaNorm,
                    'banco_norm' => $bancoNorm,
                    'archivo_comprobante' => $filePath,
                    'file_sha256' => $fileHash,
                    'estado_pago' => $estadoPago,
                    'fecha_aprobacion' => $fechaAprobacion,
                    'aprobado_por' => $aprobadoPor,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                    'observaciones' => $shouldAutoApprove 
                        ? 'Pago procesado automáticamente - monto exacto' 
                        : "Pago en revisión - diferencia de monto: $" . number_format($amountDifference, 2),
                ]);

                // Update cuota status only if auto-approved
                $estadoCuota = 'pendiente';
                if ($shouldAutoApprove) {
                    $cuota->update([
                        'estado' => 'pagado',
                        'fecha_pago' => now()
                    ]);
                    $estadoCuota = 'pagado';
                }

                // Log payment attempt
                Log::info('Payment uploaded', [
                    'user_id' => $user->id,
                    'carnet' => $user->carnet,
                    'pago_id' => $pago->id,
                    'cuota_id' => $cuota->id,
                    'monto' => $paidAmount,
                    'expected_amount' => $expectedAmount,
                    'estado' => $estadoPago,
                    'ip_address' => $ipAddress,
                    'boleta_norm' => $boletaNorm,
                    'banco_norm' => $bancoNorm,
                ]);

                $responseData = [
                    'success' => true,
                    'message' => $shouldAutoApprove 
                        ? 'Pago procesado exitosamente. Su cuota ha sido marcada como pagada.'
                        : 'Pago recibido y enviado a revisión debido a diferencias en el monto.',
                    'pago_id' => $pago->id,
                    'estado_cuota' => $estadoCuota,
                    'estado_pago' => $estadoPago,
                    'fecha_procesamiento' => now()->format('Y-m-d H:i:s'),
                    'monto_esperado' => $expectedAmount,
                    'monto_recibido' => $paidAmount,
                ];

                $responseStatus = 201;

                // Store idempotency record if key provided
                if ($idempotencyKey) {
                    PaymentRequest::create([
                        'idempotency_key' => $idempotencyKey,
                        'user_id' => $user->id,
                        'request_payload' => $request->all(),
                        'response_payload' => $responseData,
                        'response_status' => $responseStatus,
                        'ip_address' => $ipAddress,
                        'user_agent' => $userAgent,
                    ]);
                }

                return response()->json($responseData, $responseStatus);
            });

        } catch (\Illuminate\Database\QueryException $e) {
            // Handle unique constraint violations
            if (str_contains($e->getMessage(), 'unique_boleta_per_student_bank')) {
                return response()->json([
                    'success' => false,
                    'field_errors' => [
                        'numero_boleta' => ['Esta boleta ya ha sido registrada anteriormente.']
                    ]
                ], 422);
            }
            
            if (str_contains($e->getMessage(), 'unique_file_per_student')) {
                return response()->json([
                    'success' => false,
                    'field_errors' => [
                        'comprobante' => ['Este archivo ya ha sido utilizado anteriormente.']
                    ]
                ], 422);
            }
            
            Log::error('Payment upload database error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'ip_address' => $ipAddress,
            ]);
            
            throw $e;
        } catch (\Exception $e) {
            Log::error('Payment upload error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'ip_address' => $ipAddress,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor. Por favor, inténtelo nuevamente.'
            ], 500);
        }
    }

    /**
     * Calculate expected payment amount including late fees
     */
    private function calculateExpectedAmount(CuotaProgramaEstudiante $cuota): float
    {
        $baseAmount = $cuota->monto;
        $dueDate = Carbon::parse($cuota->fecha_vencimiento);
        $today = Carbon::now();

        if ($today->gt($dueDate)) {
            // Calculate late fee (example: 2% per month overdue)
            $monthsLate = $today->diffInMonths($dueDate);
            $lateFeePercentage = 0.02; // 2% per month
            $lateFee = $baseAmount * $lateFeePercentage * $monthsLate;
            return $baseAmount + $lateFee;
        }

        return $baseAmount;
    }

    /**
     * Generate secure file name for uploaded receipts
     */
    private function generateSecureFileName(string $carnet, int $cuotaId, string $extension): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $random = Str::random(8);
        return "recibo_{$carnet}_{$cuotaId}_{$timestamp}_{$random}.{$extension}";
    }

    /**
     * Obtiene el estado de cuenta completo del estudiante
     */
    public function estadoCuenta()
    {
        $user = Auth::user();

        if (!$user->carnet) {
            return response()->json([
                'message' => 'Usuario sin carnet asignado'
            ], 400);
        }

        // 🔥 USAR EL ATRIBUTO (accessor) PARA TODAS LAS CUOTAS
        $cuotas = $user->todas_las_cuotas;

        $resumen = [
            'total_cuotas' => $cuotas->count(),
            'cuotas_pagadas' => $cuotas->where('estado', 'pagado')->count(),
            'cuotas_pendientes' => $cuotas->where('estado', 'pendiente')->count(),
            'cuotas_en_revision' => $cuotas->where('estado', 'en_revision')->count(),
            'monto_total' => $cuotas->sum('monto'),
            'monto_pagado' => $cuotas->where('estado', 'pagado')->sum('monto'),
            'monto_pendiente' => $cuotas->whereIn('estado', ['pendiente', 'en_revision'])->sum('monto'),
        ];

        return response()->json([
            'cuotas' => $cuotas,
            'resumen' => $resumen
        ]);
    }
}
