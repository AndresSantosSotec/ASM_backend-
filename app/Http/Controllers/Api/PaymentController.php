<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KardexPago;
use App\Models\CuotaProgramaEstudiante;
use Illuminate\Http\Request;
use App\Http\Requests\StorePaymentRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = KardexPago::with(['estudiantePrograma.prospecto','cuota']);

        if ($request->filled('prospecto_id')) {
            $query->whereHas('estudiantePrograma', function ($q) use ($request) {
                $q->where('prospecto_id', $request->prospecto_id);
            });
        }
        if ($request->filled('estudiante_programa_id')) {
            $query->where('estudiante_programa_id', $request->estudiante_programa_id);
        }

        return response()->json(['data' => $query->orderBy('fecha_pago', 'desc')->get()]);
    }

    public function store(StorePaymentRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = Auth::id();

        DB::beginTransaction();
        try {
            $payment = KardexPago::create($data);

            // Actualizar estado de la cuota si existe
            if ($payment->cuota_id) {
                $cuota = CuotaProgramaEstudiante::findOrFail($payment->cuota_id);
                $cuota->update([
                    'estado' => 'pagado',
                    'paid_at' => $payment->fecha_pago,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Pago registrado correctamente.',
                'data' => $payment->load(['cuota', 'estudiantePrograma.prospecto'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Error al registrar el pago.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtiene el estado de cuenta de un estudiante
     */
    public function estadoCuenta($estudiante_programa_id)
    {
        $cuotas = CuotaProgramaEstudiante::where('estudiante_programa_id', $estudiante_programa_id)
                    ->with(['pagos'])
                    ->orderBy('numero_cuota')
                    ->get();

        $pagos = KardexPago::where('estudiante_programa_id', $estudiante_programa_id)
                    ->orderBy('fecha_pago', 'desc')
                    ->get();

        return response()->json([
            'cuotas' => $cuotas,
            'pagos' => $pagos,
            'resumen' => [
                'total_cuotas' => $cuotas->count(),
                'cuotas_pagadas' => $cuotas->where('estado', 'pagado')->count(),
                'monto_total' => $cuotas->sum('monto'),
                'monto_pagado' => $pagos->sum('monto_pagado'),
            ]
        ]);
    }
}
