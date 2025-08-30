<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\EstudiantePrograma;
use App\Models\CuotaProgramaEstudiante;
use Carbon\Carbon;

class PlanPagosController extends Controller
{
    /**
     * Genera el plan de pagos mensual en base a la tabla estudiante_programa
     * Incluye la inscripción como cuota #0
     */
    public function generar(Request $request)
    {
        $data = $request->validate([
            'estudiante_programa_id' => 'required|exists:estudiante_programa,id'
        ]);

        $est = EstudiantePrograma::findOrFail($data['estudiante_programa_id']);

        // Verificar si ya existe un plan de pagos
        $existingCuotas = CuotaProgramaEstudiante::where('estudiante_programa_id', $est->id)->count();
        if ($existingCuotas > 0) {
            return response()->json([
                'message' => 'Ya existe un plan de pagos para este estudiante.',
            ], 400);
        }

        $cuotas = [];
        $fechaInicio = Carbon::parse($est->fecha_inicio);

        DB::beginTransaction();
        try {
            // SOLO CREAR LAS CUOTAS MENSUALES (sin cuota de inscripción)
            $fechaInicioMensual = $fechaInicio->copy()->startOfMonth();

            for ($i = 0; $i < $est->duracion_meses; $i++) {
                $fechaCuota = $fechaInicioMensual->copy()->addMonths($i);
                $cuota = CuotaProgramaEstudiante::create([
                    'estudiante_programa_id' => $est->id,
                    'numero_cuota'           => $i + 1, // Cuotas mensuales: 1, 2, 3...
                    'fecha_vencimiento'      => $fechaCuota->copy()->day(5), // Día 5 de cada mes
                    'monto'                  => $est->cuota_mensual,
                    'estado'                 => 'pendiente',
                ]);

                $cuotas[] = $cuota;
            }

            DB::commit();

            return response()->json([
                'message' => 'Plan de pagos generado correctamente.',
                'cuotas' => $cuotas,
                'resumen' => [
                    'total_cuotas' => count($cuotas),
                    'inscripcion' => 0,
                    'cuotas_mensuales' => $est->duracion_meses,
                    'monto_total' => $est->cuota_mensual * $est->duracion_meses
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Error al generar el plan de pagos.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtiene el plan de pagos de un estudiante
     */
    public function obtenerPlan($estudiante_programa_id)
    {
        $cuotas = CuotaProgramaEstudiante::where('estudiante_programa_id', $estudiante_programa_id)
                    ->orderBy('numero_cuota')
                    ->get();

        if ($cuotas->isEmpty()) {
            return response()->json([
                'message' => 'No se encontró plan de pagos para este estudiante.',
            ], 404);
        }

        $resumen = [
            'total_cuotas' => $cuotas->count(),
            'cuotas_pagadas' => $cuotas->where('estado', 'pagado')->count(),
            'cuotas_pendientes' => $cuotas->where('estado', 'pendiente')->count(),
            'monto_total' => $cuotas->sum('monto'),
            'monto_pagado' => $cuotas->where('estado', 'pagado')->sum('monto'),
            'monto_pendiente' => $cuotas->where('estado', 'pendiente')->sum('monto'),
        ];

        return response()->json([
            'cuotas' => $cuotas,
            'resumen' => $resumen
        ]);
    }
}
