<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\CuotaProgramaEstudiante;
use App\Models\KardexPago;
use Carbon\Carbon;

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
     * Registra un pago con subida de recibo
     */
    public function subirReciboPago(Request $request)
    {
        $request->validate([
            'cuota_id' => 'required|exists:cuotas_programa_estudiante,id',
            'numero_boleta' => 'required|string|max:100',
            'banco' => 'required|string|max:100',
            'monto' => 'required|numeric|min:0',
            'comprobante' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB máx
        ]);

        $user = Auth::user();

        // 🔥 VERIFICAR QUE LA CUOTA PERTENECE AL ESTUDIANTE
        $cuota = CuotaProgramaEstudiante::whereHas('estudiantePrograma.prospecto', function ($query) use ($user) {
            $query->where('carnet', $user->carnet);
        })
            ->where('id', $request->cuota_id)
            ->where('estado', 'pendiente')
            ->first();

        if (!$cuota) {
            return response()->json([
                'message' => 'Cuota no encontrada o ya está pagada'
            ], 404);
        }

        // Guardar el archivo
        $archivo = $request->file('comprobante');
        $nombreArchivo = 'recibo_' . $user->carnet . '_' . $cuota->id . '_' . time() . '.' . $archivo->getClientOriginalExtension();
        $rutaArchivo = $archivo->storeAs('recibos_pago', $nombreArchivo, 'public');

        // Crear registro de pago (pendiente de aprobación)
        $pago = KardexPago::create([
            'estudiante_programa_id' => $cuota->estudiante_programa_id,
            'cuota_id' => $cuota->id,
            'fecha_pago' => now(),
            'monto_pagado' => $request->monto,
            'metodo_pago' => 'transferencia_bancaria',
            'numero_boleta' => $request->numero_boleta,
            'banco' => $request->banco,
            'archivo_comprobante' => $rutaArchivo,
            'estado_pago' => 'pendiente_revision',
            'observaciones' => 'Pago subido por estudiante',
            'created_by' => $user->id,
        ]);

        // Cambiar estado de la cuota a "en_revision"
        $cuota->update([
            'estado' => 'en_revision'
        ]);

        return response()->json([
            'message' => 'Recibo de pago enviado correctamente. Se revisará en las próximas 48 horas.',
            'pago_id' => $pago->id
        ], 201);
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
