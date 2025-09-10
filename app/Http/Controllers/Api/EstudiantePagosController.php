<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\CuotaProgramaEstudiante;
use App\Models\KardexPago;
use App\Models\PaymentRule;
use Carbon\Carbon;

class EstudiantePagosController extends Controller
{
    /**
     * Normaliza el número de boleta (quita espacios, símbolos y uppercases)
     */
    protected function normalizeReceiptNumber(string $n): string
    {
        $n = mb_strtoupper($n, 'UTF-8');
        return preg_replace('/[^A-Z0-9]/u', '', $n);
    }

    /**
     * Normaliza banco a un conjunto controlado (mapea alias comunes)
     */
    protected function normalizeBank(string $bank): string
    {
        $b = mb_strtoupper(trim($bank), 'UTF-8');

        $map = [
            'BANCO INDUSTRIAL' => ['BI', 'BANCO INDUSTRIAL', 'INDUSTRIAL'],
            'BANRURAL'         => ['BANRURAL', 'BAN RURAL', 'RURAL'],
            'BAM'              => ['BAM', 'BANCO AGROMERCANTIL'],
            'G&T CONTINENTAL'  => ['G&T', 'G Y T', 'GYT', 'G&T CONTINENTAL'],
            'PROMERICA'        => ['PROMERICA'],
        ];

        foreach ($map as $canon => $aliases) {
            if (in_array($b, $aliases, true)) {
                return $canon;
            }
        }

        return $b;
    }

    /**
     * Calcula mora y total esperado según PaymentRule
     */
    protected function computeExpectedWithLate(CuotaProgramaEstudiante $cuota, ?PaymentRule $rule, Carbon $now): array
    {
        $lateFeePerMonth = $rule ? (float) $rule->late_fee_amount : 0.0;

        $venc = Carbon::parse($cuota->fecha_vencimiento);
        $isOverdue = $venc->lt($now);

        $monthsOverdue = 0;
        if ($isOverdue) {
            $monthsOverdue = $venc->diffInMonths($now);
            if ($monthsOverdue === 0 && $venc->diffInDays($now) > 0) {
                $monthsOverdue = 1;
            }
        }

        $lateFeeTotal = 0.0;
        if ($monthsOverdue > 0 && $lateFeePerMonth > 0) {
            if ($lateFeePerMonth <= 1) {
                $lateFeeTotal = (float) $cuota->monto * $lateFeePerMonth * $monthsOverdue;
            } else {
                $lateFeeTotal = $lateFeePerMonth * $monthsOverdue;
            }
        }

        $expected = (float) $cuota->monto + (float) $lateFeeTotal;

        return [
            'expected_total'  => round($expected, 2),
            'late_fee_total'  => round($lateFeeTotal, 2),
            'months_overdue'  => $monthsOverdue,
        ];
    }

    /**
     * GET /api/estudiante/pagos/pendientes
     */
    public function pagosPendientes()
    {
        $user = Auth::user();

        if (!$user || !$user->carnet) {
            return response()->json([
                'message' => 'Usuario sin carnet asignado',
                'data' => []
            ], 400);
        }

        $cuotasPendientes = $user->cuotas_pendientes;

        $rule = PaymentRule::first();
        $blockAfterMonths = $rule ? (int) $rule->block_after_months : null;

        $now = Carbon::now();
        $inicioMesActual = $now->copy()->startOfMonth();
        $finMesProximo = $now->copy()->addMonth()->endOfMonth();

        $cuotasDetalladas = $cuotasPendientes->map(function ($cuota) use ($rule, $blockAfterMonths, $now) {
            $calc = $this->computeExpectedWithLate($cuota, $rule, $now);
            $fechaVencimiento = Carbon::parse($cuota->fecha_vencimiento);
            $isOverdue = $fechaVencimiento->lt($now);
            $urgent = $isOverdue && $blockAfterMonths && ($calc['months_overdue'] >= $blockAfterMonths);

            return array_merge($cuota->toArray(), [
                'is_overdue' => $isOverdue,
                'months_overdue' => $calc['months_overdue'],
                'late_fee_total' => $calc['late_fee_total'],
                'total_with_late_fee' => $calc['expected_total'],
                'urgent' => (bool) $urgent,
            ]);
        });

        $pagosMesActualYProximo = $cuotasDetalladas->filter(function ($cuota) use ($inicioMesActual, $finMesProximo) {
            $fechaVencimiento = Carbon::parse($cuota['fecha_vencimiento']);
            return $fechaVencimiento->between($inicioMesActual, $finMesProximo);
        })->values();

        $pagosAtrasados = $cuotasDetalladas->filter(function ($cuota) use ($now) {
            return Carbon::parse($cuota['fecha_vencimiento'])->lt($now);
        })->values();

        $totalPendiente = (float) $cuotasPendientes->sum('monto');
        $totalConMora = (float) $cuotasDetalladas->sum('total_with_late_fee');

        return response()->json([
            'pagos' => $cuotasDetalladas->values(),
            'pagos_mes_actual_proximo' => $pagosMesActualYProximo,
            'pagos_atrasados' => $pagosAtrasados,
            'total_pendiente' => round($totalPendiente, 2),
            'total_con_mora' => round($totalConMora, 2),
            'total_cuotas_pendientes' => $cuotasPendientes->count()
        ]);
    }

    /**
     * GET /api/estudiante/pagos/historial
     */
    public function historialPagos()
    {
        $user = Auth::user();

        if (!$user || !$user->carnet) {
            return response()->json([
                'message' => 'Usuario sin carnet asignado',
                'data' => []
            ], 400);
        }

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
     * GET /api/estudiante/pagos/estado-cuenta
     */
    public function estadoCuenta()
    {
        $user = Auth::user();

        if (!$user || !$user->carnet) {
            return response()->json([
                'message' => 'Usuario sin carnet asignado'
            ], 400);
        }

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

    /**
     * POST /api/estudiante/pagos/prevalidar-recibo
     */
    public function prevalidarRecibo(Request $request)
    {
        $request->validate([
            'numero_boleta' => 'required|string|max:120',
            'banco'         => 'required|string|max:120',
        ]);

        $numeroNormalizado = $this->normalizeReceiptNumber($request->numero_boleta);
        $bancoNormalizado  = $this->normalizeBank($request->banco);
        $fingerprint       = hash('sha256', $bancoNormalizado.'|'.$numeroNormalizado);

        $existingPayment = KardexPago::where('boleta_fingerprint', $fingerprint)
            ->whereIn('estado_pago', ['pendiente_revision','aprobado'])
            ->with(['cuota', 'estudiantePrograma.programa'])
            ->first();

        $response = [
            'duplicate' => (bool) $existingPayment,
        ];

        if ($existingPayment) {
            $response['existing_payment'] = [
                'fecha_pago' => $existingPayment->fecha_pago->format('Y-m-d H:i:s'),
                'monto_pagado' => $existingPayment->monto_pagado,
                'estado_pago' => $existingPayment->estado_pago,
                'cuota_numero' => $existingPayment->cuota ? $existingPayment->cuota->numero_cuota : 'N/A',
                'programa' => $existingPayment->estudiantePrograma->programa->nombre_del_programa ?? 'N/A'
            ];
        }

        return response()->json($response);
    }

    /**
     * POST /api/estudiante/pagos/subir-recibo
     * VERSIÓN MEJORADA con hotfix de duplicados de archivo
     */
    public function subirReciboPago(Request $request)
    {
        $request->validate([
            'cuota_id'       => 'required|exists:cuotas_programa_estudiante,id',
            'numero_boleta'  => 'required|string|max:120',
            'banco'          => 'required|string|max:120',
            'monto'          => 'required|numeric|min:0',
            'comprobante'    => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $user = Auth::user();
        if (!$user || !$user->carnet) {
            return response()->json([
                'success' => false,
                'code' => 'UNAUTHORIZED',
                'message' => 'Usuario no autenticado o sin carnet'
            ], 401);
        }

        $now         = Carbon::now();
        $rule        = PaymentRule::first();
        $numeroNorm  = $this->normalizeReceiptNumber($request->numero_boleta);
        $bancoNorm   = $this->normalizeBank($request->banco);
        $boletaFp    = hash('sha256', $bancoNorm.'|'.$numeroNorm);

        $file        = $request->file('comprobante');
        $archivoHash = hash_file('sha256', $file->getRealPath());

        $STRICT_DUP_FILE_BLOCK = (bool) env('STRICT_DUP_FILE_BLOCK', false); // [HOTFIX] flag

        return DB::transaction(function () use (
            $request, $user, $now, $rule, $numeroNorm, $bancoNorm, $boletaFp, $file, $archivoHash, $STRICT_DUP_FILE_BLOCK
        ) {
            // 1) Verificar cuota pertenece al estudiante y está pendiente
            $cuota = CuotaProgramaEstudiante::whereHas('estudiantePrograma.prospecto', function ($q) use ($user) {
                    $q->where('carnet', $user->carnet);
                })
                ->where('id', $request->cuota_id)
                ->lockForUpdate()
                ->first();

            if (!$cuota || $cuota->estado !== 'pendiente') {
                return response()->json([
                    'success' => false,
                    'code' => 'CUOTA_NOT_AVAILABLE',
                    'message' => 'La cuota no está disponible para pago o ya fue pagada',
                ], 404);
            }

            // 2) Verificar si ya hay un pago aprobado para esta cuota
            $yaAprobado = KardexPago::where('cuota_id', $cuota->id)
                ->where('estado_pago', 'aprobado')
                ->exists();

            if ($yaAprobado) {
                return response()->json([
                    'success' => false,
                    'code' => 'CUOTA_ALREADY_PAID',
                    'message' => 'Esta cuota ya fue pagada y aprobada anteriormente',
                ], 409);
            }

            // 3) VALIDACIÓN PRINCIPAL: boleta duplicada (siempre estricta)
            $existingPayment = KardexPago::where('boleta_fingerprint', $boletaFp)
                ->whereIn('estado_pago', ['pendiente_revision', 'aprobado'])
                ->with(['cuota', 'estudiantePrograma.programa'])
                ->first();

            if ($existingPayment) {
                return response()->json([
                    'success' => false,
                    'code' => 'DUPLICATE_RECEIPT_NUMBER',
                    'message' => 'Esta boleta ya fue utilizada anteriormente',
                    'error_details' => [
                        'tipo_error' => 'BOLETA_DUPLICADA',
                        'boleta_original' => [
                            'numero_boleta' => $existingPayment->numero_boleta,
                            'banco' => $existingPayment->banco,
                            'fecha_uso' => $existingPayment->fecha_pago->format('d/m/Y H:i'),
                            'monto_original' => $existingPayment->monto_pagado,
                            'estado' => $existingPayment->estado_pago,
                            'cuota_numero' => $existingPayment->cuota ? $existingPayment->cuota->numero_cuota : 'N/A',
                            'programa' => $existingPayment->estudiantePrograma->programa->nombre_del_programa ?? 'N/A'
                        ]
                    ],
                    'user_message' => 'El número de boleta "' . $request->numero_boleta . '" del banco ' . $request->banco . ' ya fue utilizado el ' . $existingPayment->fecha_pago->format('d/m/Y') . '. Por favor, verifique el número de boleta o use un comprobante diferente.'
                ], 409);
            }

            // 4) [HOTFIX] Verificar archivo duplicado con política relajada
            //    - Si STRICT_DUP_FILE_BLOCK = true -> bloquea cualquier duplicado (comportamiento original).
            //    - Si false -> solo bloquea si el archivo ya fue usado por otro estudiante u otra cuota.
            $dupArchivoQuery = KardexPago::where('archivo_hash', $archivoHash)
                ->whereIn('estado_pago', ['pendiente_revision','aprobado']);

            $dupArchivo = $dupArchivoQuery->first();

            if ($dupArchivo) {
                $esMismoEstudiante = ($dupArchivo->estudiante_programa_id === $cuota->estudiante_programa_id);
                $esMismaCuota      = ($dupArchivo->cuota_id === $cuota->id);

                if ($STRICT_DUP_FILE_BLOCK) {
                    // Política estricta: siempre bloquea
                    return response()->json([
                        'success' => false,
                        'code' => 'DUPLICATE_RECEIPT_FILE',
                        'message' => 'Este archivo ya fue presentado anteriormente',
                        'error_details' => [
                            'tipo_error' => 'ARCHIVO_DUPLICADO',
                            'archivo_original' => [
                                'pago_id' => $dupArchivo->id,
                                'estudiante_programa_id' => $dupArchivo->estudiante_programa_id,
                                'cuota_id' => $dupArchivo->cuota_id,
                                'fecha_uso' => $dupArchivo->fecha_pago->format('d/m/Y H:i'),
                                'boleta_numero' => $dupArchivo->numero_boleta,
                                'monto_original' => $dupArchivo->monto_pagado,
                            ]
                        ],
                        'user_message' => 'Este comprobante ya fue presentado anteriormente. Por favor, use un archivo diferente.'
                    ], 409);
                }

                // Política relajada:
                // - Si es el MISMO estudiante y MISMA cuota:
                //   -> si el anterior está pendiente_revision, lo reusamos (lo actualizamos);
                //   -> si está aprobado, entonces ya quedó pagado y no se debe re-subir (que caiga por CUOTA_ALREADY_PAID arriba).
                if ($esMismoEstudiante && $esMismaCuota) {
                    if ($dupArchivo->estado_pago === 'pendiente_revision') {
                        // Reusar: actualizamos el registro existente en vez de crear otro
                        $nombreArchivo = 'recibo_' . $user->carnet . '_' . $cuota->id . '_' . time() . '.' . $file->getClientOriginalExtension();
                        $rutaArchivo   = $file->storeAs('recibos_pago', $nombreArchivo, 'public');

                        // Recalcular expected
                        $calc = $this->computeExpectedWithLate($cuota, $rule, $now);
                        $expectedTotal = $calc['expected_total'];
                        $tolerance     = 0.05;
                        $montoCliente  = (float) $request->monto;
                        $difference    = round(abs($montoCliente - $expectedTotal), 2);
                        $autoApprove   = ($difference <= $tolerance);

                        $dupArchivo->update([
                            'fecha_pago'          => $now,
                            'monto_pagado'        => $montoCliente,
                            'metodo_pago'         => 'transferencia_bancaria',
                            'numero_boleta'       => $request->numero_boleta,
                            'banco'               => $request->banco,
                            'archivo_comprobante' => $rutaArchivo,
                            'estado_pago'         => $autoApprove ? 'aprobado' : 'pendiente_revision',
                            'observaciones'       => $autoApprove
                                ? 'Pago aprobado automáticamente (monto validado) [reupload same cuota]'
                                : 'Diferencia vs. monto esperado: Q' . number_format($difference, 2) . ' [reupload same cuota]',
                            'numero_boleta_normalizada' => $numeroNorm,
                            'banco_normalizado'         => $bancoNorm,
                            'boleta_fingerprint'        => $boletaFp,
                            'archivo_hash'              => $archivoHash,
                        ]);

                        if ($autoApprove) {
                            $cuota->update([
                                'estado'     => 'pagado',
                                'fecha_pago' => $now,
                            ]);

                            return response()->json([
                                'success' => true,
                                'code' => 'PAYMENT_APPROVED',
                                'message' => 'Pago procesado y aprobado automáticamente',
                                'pago_id' => $dupArchivo->id,
                                'estado_cuota' => 'pagado',
                                'expected_total' => $expectedTotal,
                                'late_fee_total' => $calc['late_fee_total'],
                                'months_overdue' => $calc['months_overdue'],
                                'fecha_procesamiento' => $now->format('Y-m-d H:i:s'),
                            ], 201);
                        }

                        return response()->json([
                            'success' => false,
                            'code' => 'AMOUNT_MISMATCH_UNDER_REVIEW',
                            'message' => 'Recibo actualizado, pero el monto no coincide exactamente. Se revisará manualmente',
                            'pago_id' => $dupArchivo->id,
                            'estado_pago' => 'pendiente_revision',
                            'expected_total' => $expectedTotal,
                            'monto_enviado' => $montoCliente,
                            'difference' => $difference,
                            'late_fee_total' => $calc['late_fee_total'],
                            'months_overdue' => $calc['months_overdue'],
                        ], 202);
                    }

                    // Si está aprobado para esa misma cuota, ya se habría detenido por CUOTA_ALREADY_PAID.
                } else {
                    // Es otro estudiante u otra cuota -> sí bloqueamos
                    return response()->json([
                        'success' => false,
                        'code' => 'DUPLICATE_RECEIPT_FILE',
                        'message' => 'Este archivo ya fue presentado anteriormente',
                        'error_details' => [
                            'tipo_error' => 'ARCHIVO_DUPLICADO',
                            'archivo_original' => [
                                'pago_id' => $dupArchivo->id,
                                'estudiante_programa_id' => $dupArchivo->estudiante_programa_id,
                                'cuota_id' => $dupArchivo->cuota_id,
                                'fecha_uso' => $dupArchivo->fecha_pago->format('d/m/Y H:i'),
                                'boleta_numero' => $dupArchivo->numero_boleta,
                                'monto_original' => $dupArchivo->monto_pagado,
                            ]
                        ],
                        'user_message' => 'Este comprobante ya fue presentado anteriormente. Por favor, use un archivo diferente.'
                    ], 409);
                }
            }

            // 5) Cálculo de mora / total esperado
            $calc = $this->computeExpectedWithLate($cuota, $rule, $now);
            $expectedTotal = $calc['expected_total'];
            $tolerance     = 0.05; // Q0.05 de tolerancia

            $montoCliente = (float) $request->monto;
            $difference   = round(abs($montoCliente - $expectedTotal), 2);

            // 6) Guardar archivo
            $nombreArchivo = 'recibo_' . $user->carnet . '_' . $cuota->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $rutaArchivo   = $file->storeAs('recibos_pago', $nombreArchivo, 'public');

            // 7) Determinar estado del pago
            $autoApprove = ($difference <= $tolerance);

            $pago = KardexPago::create([
                'estudiante_programa_id'    => $cuota->estudiante_programa_id,
                'cuota_id'                  => $cuota->id,
                'fecha_pago'                => $now,
                'monto_pagado'              => $montoCliente,
                'metodo_pago'               => 'transferencia_bancaria',
                'numero_boleta'             => $request->numero_boleta,
                'banco'                     => $request->banco,
                'archivo_comprobante'       => $rutaArchivo,
                'estado_pago'               => $autoApprove ? 'aprobado' : 'pendiente_revision',
                'observaciones'             => $autoApprove
                    ? 'Pago aprobado automáticamente (monto validado)'
                    : 'Diferencia vs. monto esperado: Q' . number_format($difference, 2),
                'numero_boleta_normalizada' => $numeroNorm,
                'banco_normalizado'         => $bancoNorm,
                'boleta_fingerprint'        => $boletaFp,
                'archivo_hash'              => $archivoHash,
            ]);

            if ($autoApprove) {
                // Marcar cuota como pagada
                $cuota->update([
                    'estado'     => 'pagado',
                    'fecha_pago' => $now,
                ]);

                return response()->json([
                    'success' => true,
                    'code' => 'PAYMENT_APPROVED',
                    'message' => 'Pago procesado y aprobado automáticamente',
                    'pago_id' => $pago->id,
                    'estado_cuota' => 'pagado',
                    'expected_total' => $expectedTotal,
                    'late_fee_total' => $calc['late_fee_total'],
                    'months_overdue' => $calc['months_overdue'],
                    'fecha_procesamiento' => $now->format('Y-m-d H:i:s'),
                ], 201);
            }

            // En revisión
            return response()->json([
                'success' => false,
                'code' => 'AMOUNT_MISMATCH_UNDER_REVIEW',
                'message' => 'Recibo recibido, pero el monto no coincide exactamente. Se revisará manualmente',
                'pago_id' => $pago->id,
                'estado_pago' => 'pendiente_revision',
                'expected_total' => $expectedTotal,
                'monto_enviado' => $montoCliente,
                'difference' => $difference,
                'late_fee_total' => $calc['late_fee_total'],
                'months_overdue' => $calc['months_overdue'],
            ], 202);
        });
    }
}
