<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KardexPago;
use App\Models\CuotaProgramaEstudiante;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class KardexPagoController extends Controller
{
    public function index(Request $request)
    {
        $q = KardexPago::with(['estudiantePrograma.prospecto','cuota']);

        if ($request->filled('prospecto_id')) {
            $q->whereHas('estudiantePrograma', function ($s) use ($request) {
                $s->where('prospecto_id', $request->prospecto_id);
            });
        }
        if ($request->filled('estudiante_programa_id')) {
            $q->where('estudiante_programa_id', $request->estudiante_programa_id);
        }

        return response()->json($q->get());
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
        $pago = KardexPago::create($data);

        if ($pago->cuota_id) {
            CuotaProgramaEstudiante::where('id', $pago->cuota_id)->update([
                'estado' => 'pagado',
                'paid_at' => $pago->fecha_pago,
            ]);
        }

        return response()->json($pago, 201);
    }
}
