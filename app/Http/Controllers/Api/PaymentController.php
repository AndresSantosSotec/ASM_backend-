<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KardexPago;
use App\Models\CuotaProgramaEstudiante;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'estudiante_programa_id' => 'required|exists:estudiante_programa,id',
            'cuota_id' => 'nullable|exists:cuotas_programa_estudiante,id',
            'fecha_pago' => 'required|date',
            'monto_pagado' => 'required|numeric',
            'metodo_pago' => 'nullable|string',
            'observaciones' => 'nullable|string',
        ]);

        $data['created_by'] = Auth::id();
        $payment = KardexPago::create($data);

        if ($payment->cuota_id) {
            CuotaProgramaEstudiante::where('id', $payment->cuota_id)->update([
                'estado' => 'pagado',
                'paid_at' => $payment->fecha_pago,
            ]);
        }

        return response()->json(['data' => $payment], 201);
    }
}
