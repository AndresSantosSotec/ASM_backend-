<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Prospecto;
use App\Models\User;
use App\Models\Course;
use Illuminate\Support\Facades\Auth;

class EstudiantesController extends Controller
{
    /**
     * GET /api/estudiantes/mi-informacion
     * Obtiene la información completa del prospecto logueado
     */
    public function miInformacion(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user || !$user->carnet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado o sin carnet asignado'
                ], 404);
            }

            // Buscar el prospecto por carnet
            $prospecto = Prospecto::with([
                'programas.programa',
                'programas.cuotas' => function($query) {
                    $query->orderBy('fecha_vencimiento', 'asc');
                },
                'kardexPagos' => function($query) {
                    $query->where('estado_pago', 'aprobado')
                          ->orderBy('fecha_pago', 'desc');
                },
                'convenio',
                'documentos',
                'courses'
            ])->where('carnet', $user->carnet)->first();

            if (!$prospecto) {
                return response()->json([
                    'success' => false,
                    'message' => 'Información del estudiante no encontrada'
                ], 404);
            }

            // Calcular estadísticas financieras
            $totalDeuda = $prospecto->cuotas()->sum('monto');
            $totalPagado = $prospecto->kardexPagos()
                ->where('estado_pago', 'aprobado')
                ->sum('monto_pagado');
            $saldoPendiente = $totalDeuda - $totalPagado;

            // Cuotas por estado
            $cuotasPendientes = $prospecto->cuotas()->where('estado', 'pendiente')->count();
            $cuotasPagadas = $prospecto->cuotas()->where('estado', 'pagado')->count();
            $cuotasVencidas = $prospecto->cuotas()
                ->where('estado', 'pendiente')
                ->where('fecha_vencimiento', '<', now())
                ->count();

            // Información académica
            $programasActivos = $prospecto->programas()
                ->where('estado', 'activo')
                ->count();

            $cursosInscritos = $prospecto->courses()->count();

            // Próxima cuota a vencer
            $proximaCuota = $prospecto->cuotas()
                ->where('estado', 'pendiente')
                ->where('fecha_vencimiento', '>=', now())
                ->orderBy('fecha_vencimiento', 'asc')
                ->first();

            // Último pago realizado
            $ultimoPago = $prospecto->kardexPagos()
                ->where('estado_pago', 'aprobado')
                ->orderBy('fecha_pago', 'desc')
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    // Información personal
                    'informacion_personal' => [
                        'carnet' => $prospecto->carnet,
                        'nombre_completo' => $prospecto->nombre_completo,
                        'correo_electronico' => $prospecto->correo_electronico,
                        'telefono' => $prospecto->telefono,
                        'numero_identificacion' => $prospecto->numero_identificacion,
                        'fecha_nacimiento' => $prospecto->fecha_nacimiento,
                        'genero' => $prospecto->genero,
                        'pais_origen' => $prospecto->pais_origen,
                        'pais_residencia' => $prospecto->pais_residencia,
                        'direccion_residencia' => $prospecto->direccion_residencia,
                    ],

                    // Información académica
                    'informacion_academica' => [
                        'programas_activos' => $programasActivos,
                        'cursos_inscritos' => $cursosInscritos,
                        'ultimo_titulo' => $prospecto->ultimo_titulo_obtenido,
                        'institucion_titulo' => $prospecto->institucion_titulo,
                        'anio_graduacion' => $prospecto->anio_graduacion,
                        'programas' => $prospecto->programas->map(function($ep) {
                            return [
                                'id' => $ep->id,
                                'programa' => $ep->programa->nombre_del_programa ?? 'Sin programa',
                                'modalidad' => $ep->programa->modalidad ?? 'N/A',
                                'estado' => $ep->estado,
                                'fecha_inscripcion' => $ep->created_at,
                            ];
                        })
                    ],

                    // Información financiera
                    'informacion_financiera' => [
                        'saldo_pendiente' => number_format($saldoPendiente, 2),
                        'total_deuda' => number_format($totalDeuda, 2),
                        'total_pagado' => number_format($totalPagado, 2),
                        'cuotas_pendientes' => $cuotasPendientes,
                        'cuotas_pagadas' => $cuotasPagadas,
                        'cuotas_vencidas' => $cuotasVencidas,
                        'esta_bloqueado' => $prospecto->isBlockedWithExceptions(),
                        'convenio' => $prospecto->convenio ? [
                            'nombre' => $prospecto->convenio->nombre,
                            'tipo' => $prospecto->convenio->tipo,
                            'descuento' => $prospecto->convenio->descuento_porcentaje,
                        ] : null,
                        'proxima_cuota' => $proximaCuota ? [
                            'monto' => number_format($proximaCuota->monto, 2),
                            'fecha_vencimiento' => $proximaCuota->fecha_vencimiento,
                            'dias_para_vencer' => now()->diffInDays($proximaCuota->fecha_vencimiento, false),
                        ] : null,
                        'ultimo_pago' => $ultimoPago ? [
                            'monto' => number_format($ultimoPago->monto_pagado, 2),
                            'fecha' => $ultimoPago->fecha_pago,
                            'banco' => $ultimoPago->banco,
                            'numero_boleta' => $ultimoPago->numero_boleta,
                        ] : null,
                    ],

                    // Información laboral
                    'informacion_laboral' => [
                        'empresa' => $prospecto->empresa_donde_labora_actualmente,
                        'puesto' => $prospecto->puesto,
                        'telefono_corporativo' => $prospecto->telefono_corporativo,
                        'correo_corporativo' => $prospecto->correo_corporativo,
                        'direccion_empresa' => $prospecto->direccion_empresa,
                    ],

                    // Estadísticas generales
                    'estadisticas' => [
                        'documentos_subidos' => $prospecto->documentos()->count(),
                        'cursos_completados' => $prospecto->cantidad_cursos_aprobados ?? 0,
                        'fecha_registro' => $prospecto->created_at,
                        'ultima_actualizacion' => $prospecto->updated_at,
                        'estado_general' => $prospecto->status,
                    ],

                    // Excepciones activas (si las tiene)
                    'excepciones_activas' => $prospecto->activeExceptionCategories()->get()->map(function($exception) {
                        return [
                            'categoria' => $exception->name,
                            'descripcion' => $exception->description,
                            'vigente_hasta' => $exception->pivot->effective_until,
                        ];
                    }),
                ],
                'message' => 'Información del estudiante obtenida exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la información: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/estudiantes/resumen-rapido
     * Resumen rápido de información clave
     */
    public function resumenRapido(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user || !$user->carnet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ], 404);
            }

            $prospecto = Prospecto::where('carnet', $user->carnet)->first();

            if (!$prospecto) {
                return response()->json([
                    'success' => false,
                    'message' => 'Estudiante no encontrado'
                ], 404);
            }

            $saldo = $prospecto->getBalance();
            $bloqueado = $prospecto->isBlockedWithExceptions();

            return response()->json([
                'success' => true,
                'data' => [
                    'nombre' => $prospecto->nombre_completo,
                    'carnet' => $prospecto->carnet,
                    'saldo_pendiente' => number_format($saldo, 2),
                    'esta_bloqueado' => $bloqueado,
                    'cuotas_vencidas' => $prospecto->cuotas()
                        ->where('estado', 'pendiente')
                        ->where('fecha_vencimiento', '<', now())
                        ->count(),
                    'programas_activos' => $prospecto->programas()
                        ->where('estado', 'activo')
                        ->count(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}
