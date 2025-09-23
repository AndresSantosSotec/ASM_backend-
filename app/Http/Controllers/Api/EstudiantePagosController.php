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
            'BANCO INDUSTRIAL' => ['BI','BANCO INDUSTRIAL','INDUSTRIAL'],
            'BANRURAL'         => ['BANRURAL','BAN RURAL','RURAL'],
            'BAM'              => ['BAM','BANCO AGROMERCANTIL'],
            'G&T CONTINENTAL'  => ['G&T','G Y T','GYT','G&T CONTINENTAL'],
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
                // porcentaje mensual
                $lateFeeTotal = (float) $cuota->monto * $lateFeePerMonth * $monthsOverdue;
            } else {
                // monto fijo mensual
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
     * VERSIÓN MEJORADA: fecha_recibo y siempre en revisión manual.
     * Además, actualiza la cuota a 'en_revision' en cualquier caso de alta/reuso.
     */
    public function subirReciboPago(Request $request)
    {
        $request->validate([
            'cuota_id'       => 'required|exists:cuotas_programa_estudiante,id',
            'numero_boleta'  => 'required|string|max:120',
            'banco'          => 'required|string|max:120',
            'monto'          => 'required|numeric|min:0',
            'fecha_recibo'   => 'required|date',
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

        $STRICT_DUP_FILE_BLOCK = (bool) env('STRICT_DUP_FILE_BLOCK', false);

        return DB::transaction(function () use (
            $request, $user, $now, $rule, $numeroNorm, $bancoNorm, $boletaFp, $file, $archivoHash, $STRICT_DUP_FILE_BLOCK
        ) {
            // 1) Verificar cuota pertenece al estudiante y NO está pagada
            $cuota = CuotaProgramaEstudiante::whereHas('estudiantePrograma.prospecto', function ($q) use ($user) {
                    $q->where('carnet', $user->carnet);
                })
                ->where('id', $request->cuota_id)
                ->lockForUpdate()
                ->first();

            if (!$cuota || $cuota->estado === 'pagado') {
                return response()->json([
                    'success' => false,
                    'code' => 'CUOTA_NOT_AVAILABLE',
                    'message' => 'La cuota no está disponible para pago o ya fue pagada',
                ], 404);
            }

            // 2) Evitar pagos duplicados aprobados
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

            // 3) Duplicado de BOLETA (estricto)
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

            // 4) Duplicado de ARCHIVO (relajado por flag)
            $dupArchivo = KardexPago::where('archivo_hash', $archivoHash)
                ->whereIn('estado_pago', ['pendiente_revision','aprobado'])
                ->first();

            if ($dupArchivo) {
                $esMismoEstudiante = ($dupArchivo->estudiante_programa_id === $cuota->estudiante_programa_id);
                $esMismaCuota      = ($dupArchivo->cuota_id === $cuota->id);

                if ($STRICT_DUP_FILE_BLOCK) {
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

                // Reuso permitido: mismo estudiante y misma cuota, y el registro anterior sigue en revisión
                if ($esMismoEstudiante && $esMismaCuota && $dupArchivo->estado_pago === 'pendiente_revision') {
                    $nombreArchivo = 'recibo_' . $user->carnet . '_' . $cuota->id . '_' . time() . '.' . $file->getClientOriginalExtension();
                    $rutaArchivo   = $file->storeAs('recibos_pago', $nombreArchivo, 'public');

                    // Info de cálculo (solo informativo)
                    $calc = $this->computeExpectedWithLate($cuota, $rule, $now);
                    $expectedTotal = $calc['expected_total'];
                    $montoCliente  = (float) $request->monto;
                    $difference    = round(abs($montoCliente - $expectedTotal), 2);

                    $dupArchivo->update([
                        'fecha_pago'          => $now,
                        'fecha_recibo'        => $request->fecha_recibo,
                        'monto_pagado'        => $montoCliente,
                        'metodo_pago'         => 'transferencia_bancaria',
                        'numero_boleta'       => $request->numero_boleta,
                        'banco'               => $request->banco,
                        'archivo_comprobante' => $rutaArchivo,
                        'estado_pago'         => 'pendiente_revision',
                        'observaciones'       => 'Recibo resubido. En espera de revisión manual. Diferencia vs. monto esperado: Q' . number_format($difference, 2),
                        'numero_boleta_normalizada' => $numeroNorm,
                        'banco_normalizado'         => $bancoNorm,
                        'boleta_fingerprint'        => $boletaFp,
                        'archivo_hash'              => $archivoHash,
                    ]);

                    // 🔹 Siempre que haya un recibo subido o resubido, la cuota queda en revisión
                    $cuota->update([
                        'estado'  => 'en_revision',
                        'paid_at' => null,
                    ]);

                    return response()->json([
                        'success' => true,
                        'code' => 'RECEIPT_UPDATED',
                        'message' => 'Recibo actualizado y enviado a revisión',
                        'pago_id' => $dupArchivo->id,
                        'estado_pago' => 'pendiente_revision',
                        'estado_cuota' => 'en_revision',
                        'expected_total' => $expectedTotal,
                        'monto_enviado' => $montoCliente,
                        'difference' => $difference,
                        'late_fee_total' => $calc['late_fee_total'],
                        'months_overdue' => $calc['months_overdue'],
                        'fecha_procesamiento' => $now->format('Y-m-d H:i:s'),
                    ], 201);
                }

                // Otro estudiante u otra cuota => bloquea
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

            // 5) Calcular info (solo informativa)
            $calc = $this->computeExpectedWithLate($cuota, $rule, $now);
            $expectedTotal = $calc['expected_total'];
            $montoCliente  = (float) $request->monto;
            $difference    = round(abs($montoCliente - $expectedTotal), 2);

            // 6) Guardar archivo
            $nombreArchivo = 'recibo_' . $user->carnet . '_' . $cuota->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $rutaArchivo   = $file->storeAs('recibos_pago', $nombreArchivo, 'public');

            // 7) Crear KardexPago (siempre en revisión)
            $pago = KardexPago::create([
                'estudiante_programa_id'    => $cuota->estudiante_programa_id,
                'cuota_id'                  => $cuota->id,
                'fecha_pago'                => $now,
                'fecha_recibo'              => $request->fecha_recibo,
                'monto_pagado'              => $montoCliente,
                'metodo_pago'               => 'transferencia_bancaria',
                'numero_boleta'             => $request->numero_boleta,
                'banco'                     => $request->banco,
                'archivo_comprobante'       => $rutaArchivo,
                'estado_pago'               => 'pendiente_revision',
                'observaciones'             => 'Recibo ingresado. En espera de revisión manual. Diferencia vs. monto esperado: Q' . number_format($difference, 2),
                'numero_boleta_normalizada' => $numeroNorm,
                'banco_normalizado'         => $bancoNorm,
                'boleta_fingerprint'        => $boletaFp,
                'archivo_hash'              => $archivoHash,
            ]);

            // 8) Dejar la cuota en revisión también aquí
            $cuota->update([
                'estado'  => 'en_revision',
                'paid_at' => null,
            ]);

            // 9) Respuesta
            return response()->json([
                'success' => true,
                'code' => 'RECEIPT_SUBMITTED',
                'message' => 'Recibo registrado y enviado a revisión',
                'pago_id' => $pago->id,
                'estado_pago' => 'pendiente_revision',
                'estado_cuota' => 'en_revision',
                'expected_total' => $expectedTotal,
                'monto_enviado' => $montoCliente,
                'difference' => $difference,
                'late_fee_total' => $calc['late_fee_total'],
                'months_overdue' => $calc['months_overdue'],
                'fecha_procesamiento' => $now->format('Y-m-d H:i:s'),
            ], 201);
        });
    }
}
