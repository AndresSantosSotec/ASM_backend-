<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
     * Registra un pago con subida de recibo - APROBACIÓN AUTOMÁTICA
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

        // 🔥 NUEVO: Validaciones de duplicado antes de guardar archivo
        $existingReceipt = KardexPago::receiptExists($request->banco, $request->numero_boleta);
        if ($existingReceipt) {
            $isOwnPayment = $existingReceipt->estudiantePrograma && 
                           $existingReceipt->estudiantePrograma->prospecto && 
                           $existingReceipt->estudiantePrograma->prospecto->carnet === $user->carnet;
            
            if ($isOwnPayment) {
                return response()->json([
                    'message' => "Esta boleta ya fue registrada por usted el {$existingReceipt->created_at->format('d/m/Y')} (estado: {$existingReceipt->estado_pago})."
                ], 400);
            } else {
                return response()->json([
                    'message' => 'Esta boleta ya fue utilizada por otro estudiante.'
                ], 400);
            }
        }

        // Guardar el archivo temporalmente para calcular hash
        $archivo = $request->file('comprobante');
        $tempPath = $archivo->store('temp');
        $fullTempPath = storage_path('app/' . $tempPath);
        
        try {
            // 🔥 NUEVO: Calcular hash del archivo
            $fileHash = KardexPago::calculateFileHash($fullTempPath);
            
            // 🔥 NUEVO: Verificar duplicado de archivo
            $existingFile = KardexPago::fileHashExists($cuota->estudiante_programa_id, $fileHash);
            if ($existingFile) {
                return response()->json([
                    'message' => 'Este comprobante ya fue cargado previamente.'
                ], 400);
            }

            // Mover archivo a ubicación final
            $nombreArchivo = 'recibo_' . $user->carnet . '_' . $cuota->id . '_' . time() . '.' . $archivo->getClientOriginalExtension();
            $rutaArchivo = $archivo->storeAs('recibos_pago', $nombreArchivo, 'public');
            
        } finally {
            // Limpiar archivo temporal siempre
            if (\Storage::exists($tempPath)) {
                \Storage::delete($tempPath);
            }
        }

        // 🔥 TRANSACCIÓN: Crear registro de pago con valores normalizados
        $pago = null;
        \DB::transaction(function () use ($cuota, $request, $rutaArchivo, $fileHash, &$pago) {
            $pago = KardexPago::create([
                'estudiante_programa_id' => $cuota->estudiante_programa_id,
                'cuota_id' => $cuota->id,
                'fecha_pago' => now(),
                'monto_pagado' => $request->monto,
                'metodo_pago' => 'transferencia_bancaria',
                'numero_boleta' => $request->numero_boleta,
                'banco' => $request->banco,
                'banco_norm' => KardexPago::normalizeBanco($request->banco),
                'numero_boleta_norm' => KardexPago::normalizeNumeroBoleta($request->numero_boleta),
                'archivo_comprobante' => $rutaArchivo,
                'file_sha256' => $fileHash,
                'estado_pago' => 'aprobado', 
                'observaciones' => 'Pago procesado automáticamente',
                'fecha_aprobacion' => now(),
                'aprobado_por' => 'sistema_automatico',
            ]);

            // 🔥 CAMBIO: Cambiar estado de la cuota directamente a "pagado"
            $cuota->update([
                'estado' => 'pagado',
                'fecha_pago' => now()
            ]);
        });

        return response()->json([
            'message' => 'Pago procesado exitosamente. Su cuota ha sido marcada como pagada.',
            'pago_id' => $pago->id,
            'estado_cuota' => 'pagado',
            'fecha_procesamiento' => now()->format('Y-m-d H:i:s')
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

    /**
     * Preflight verification for bank receipt to check for duplicates
     */
    public function verifyBoleta(Request $request)
    {
        $request->validate([
            'banco' => 'required|string|max:100',
            'numero_boleta' => 'required|string|max:100',
        ]);

        $existingPayment = KardexPago::receiptExists($request->banco, $request->numero_boleta);

        if (!$existingPayment) {
            return response()->json([
                'exists' => false,
                'message' => 'Esta boleta está disponible para uso.'
            ]);
        }

        $user = Auth::user();
        $isOwnPayment = $existingPayment->estudiantePrograma && 
                       $existingPayment->estudiantePrograma->prospecto && 
                       $existingPayment->estudiantePrograma->prospecto->carnet === $user->carnet;

        if ($isOwnPayment) {
            return response()->json([
                'exists' => true,
                'is_own' => true,
                'message' => "Esta boleta ya fue registrada por usted el {$existingPayment->created_at->format('d/m/Y')} (estado: {$existingPayment->estado_pago}).",
                'fecha_registro' => $existingPayment->created_at->format('d/m/Y'),
                'estado' => $existingPayment->estado_pago
            ]);
        }

        return response()->json([
            'exists' => true,
            'is_own' => false,
            'message' => 'Esta boleta ya fue utilizada por otro estudiante.'
        ]);
    }
}
