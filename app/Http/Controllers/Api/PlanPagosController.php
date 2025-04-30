<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\EstudiantePrograma;
use App\Models\CuotaProgramaEstudiante;
use App\Models\KardexPago;
use Carbon\Carbon;

class PlanPagosController extends Controller
{
    /**
     * Genera el plan de pagos mensual en base a la tabla estudiante_programa
     */
    public function generar(Request $request)
    {
        $data = $request->validate([
            'estudiante_programa_id' => 'required|exists:estudiante_programa,id'
        ]);

        $est = EstudiantePrograma::findOrFail($data['estudiante_programa_id']);

        $cuotas = [];
        $fechaInicio = Carbon::parse($est->fecha_inicio)->startOfMonth();

        DB::beginTransaction();
        try {
            for ($i = 0; $i < $est->duracion_meses; $i++) {
                $fechaCuota = $fechaInicio->copy()->addMonths($i);
                $cuota = CuotaProgramaEstudiante::create([
                    'estudiante_programa_id' => $est->id,
                    'mes' => $fechaCuota->month,
                    'anio' => $fechaCuota->year,
                    'fecha_vencimiento' => $fechaCuota->copy()->day(5),
                    'monto_cuota' => $est->cuota_mensual,
                    'estado' => 'pendiente',
                    'created_by' => Auth::id(),
                ]);
                $cuotas[] = $cuota;
            }

            DB::commit();

            return response()->json([
                'message' => 'Plan de pagos generado correctamente.',
                'cuotas' => $cuotas,
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Error al generar el plan de pagos.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
