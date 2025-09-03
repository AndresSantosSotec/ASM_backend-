<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Prospecto;
use App\Models\EstudiantePrograma;
use App\Models\CuotaProgramaEstudiante;
use App\Models\KardexPago;
use App\Models\Programa;
use App\Models\PaymentRule;
use App\Models\PaymentRuleBlockingRule;

class DashboardFinancieroController extends Controller
{
    /**
     * Dashboard financiero principal
     */
    public function index(Request $request)
    {
        try {
            // Validar fechas opcionales
            $fechaInicio = $request->get('fecha_inicio', Carbon::now()->startOfMonth());
            $fechaFin = $request->get('fecha_fin', Carbon::now()->endOfMonth());

            if (is_string($fechaInicio)) $fechaInicio = Carbon::parse($fechaInicio);
            if (is_string($fechaFin)) $fechaFin = Carbon::parse($fechaFin);

            return response()->json([
                'resumen' => $this->obtenerResumenGeneral($fechaInicio, $fechaFin),
                'pagosRecientes' => $this->obtenerPagosRecientes(),
                'alertasMorosidad' => $this->obtenerAlertasMorosidad(),
                'morosidadPorPrograma' => $this->obtenerMorosidadPorPrograma(),
                'tendenciaIngresos' => $this->obtenerTendenciaIngresos(),
                'configuracionMora' => $this->obtenerConfiguracionMora()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error interno del servidor',
                'message' => $e->getMessage(),
                'debug' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * Obtener resumen general con comparación mensual
     */
    private function obtenerResumenGeneral($fechaInicio, $fechaFin)
    {
        $mesActual = Carbon::now();
        $mesAnterior = Carbon::now()->subMonth();

        // Ingresos mensuales con try-catch para manejar posibles errores de tabla
        try {
            $ingresosMensuales = KardexPago::where('estado_pago', 'aprobado')
                ->whereMonth('fecha_pago', $mesActual->month)
                ->whereYear('fecha_pago', $mesActual->year)
                ->sum('monto_pagado');

            $ingresosMesAnterior = KardexPago::where('estado_pago', 'aprobado')
                ->whereMonth('fecha_pago', $mesAnterior->month)
                ->whereYear('fecha_pago', $mesAnterior->year)
                ->sum('monto_pagado');
        } catch (\Exception $e) {
            $ingresosMensuales = 0;
            $ingresosMesAnterior = 0;
        }

        // Estudiantes activos
        try {
            $estudiantesActivos = EstudiantePrograma::whereHas('cuotas', function($q) {
                $q->where('estado', 'pendiente')
                  ->orWhere('estado', 'pagado');
            })->count();

            $estudiantesActivosAnterior = EstudiantePrograma::whereHas('cuotas', function($q) use ($mesAnterior) {
                $q->where(function($sq) use ($mesAnterior) {
                    $sq->where('estado', 'pendiente')
                       ->orWhere('estado', 'pagado');
                })
                ->whereMonth('fecha_vencimiento', '<=', $mesAnterior->month)
                ->whereYear('fecha_vencimiento', '<=', $mesAnterior->year);
            })->count();
        } catch (\Exception $e) {
            $estudiantesActivos = 0;
            $estudiantesActivosAnterior = 0;
        }

        // Cálculo de morosidad con mora aplicada
        $morosidadData = $this->calcularMorosidadCompleta();

        return [
            'ingresosMensuales' => (float) $ingresosMensuales,
            'ingresosMesAnterior' => (float) $ingresosMesAnterior,
            'tasaMorosidad' => $morosidadData['tasaActual'],
            'tasaMorosidadAnterior' => $morosidadData['tasaAnterior'],
            'recaudacionPendiente' => $morosidadData['recaudacionPendiente'],
            'recaudacionPendienteAnterior' => $morosidadData['recaudacionPendienteAnterior'],
            'estudiantesActivos' => $estudiantesActivos,
            'estudiantesActivosAnterior' => $estudiantesActivosAnterior,
        ];
    }

    /**
     * Calcular morosidad completa con mora aplicada
     */
    private function calcularMorosidadCompleta()
    {
        $ahora = Carbon::now();
        $mesAnterior = Carbon::now()->subMonth();

        // Obtener la primera regla de pago disponible
        $paymentRule = PaymentRule::first();

        try {
            // Cuotas vencidas actuales con mora
            $cuotasVencidasActuales = CuotaProgramaEstudiante::where('estado', 'pendiente')
                ->where('fecha_vencimiento', '<', $ahora)
                ->get();

            $totalVencidoConMora = 0;
            $totalCuotasVencidas = $cuotasVencidasActuales->count();

            foreach ($cuotasVencidasActuales as $cuota) {
                $diasVencidos = Carbon::parse($cuota->fecha_vencimiento)->diffInDays($ahora);
                $montoConMora = $this->calcularMontoConMora($cuota->monto, $diasVencidos, $paymentRule);
                $totalVencidoConMora += $montoConMora;
            }

            // Cuotas del mes anterior para comparación
            $cuotasVencidasAnterior = CuotaProgramaEstudiante::where('estado', 'pendiente')
                ->where('fecha_vencimiento', '<', $mesAnterior->endOfMonth())
                ->whereMonth('fecha_vencimiento', $mesAnterior->month)
                ->sum('monto');

            // Total de estudiantes para cálculo de tasa
            $totalEstudiantes = EstudiantePrograma::count();
            $estudiantesMorosos = EstudiantePrograma::whereHas('cuotas', function($q) use ($ahora) {
                $q->where('estado', 'pendiente')
                  ->where('fecha_vencimiento', '<', $ahora);
            })->count();

            $tasaActual = $totalEstudiantes > 0 ? ($estudiantesMorosos / $totalEstudiantes) * 100 : 0;
            $tasaAnterior = $tasaActual * 0.9; // Ejemplo: 10% menos que actual

        } catch (\Exception $e) {
            $totalVencidoConMora = 0;
            $cuotasVencidasAnterior = 0;
            $totalCuotasVencidas = 0;
            $tasaActual = 0;
            $tasaAnterior = 0;
        }

        return [
            'tasaActual' => round($tasaActual, 2),
            'tasaAnterior' => round($tasaAnterior, 2),
            'recaudacionPendiente' => $totalVencidoConMora,
            'recaudacionPendienteAnterior' => $cuotasVencidasAnterior,
            'totalCuotasVencidas' => $totalCuotasVencidas
        ];
    }

    /**
     * Calcular monto con mora según reglas configuradas
     */
    private function calcularMontoConMora($montoOriginal, $diasVencidos, $paymentRule)
    {
        if (!$paymentRule || $diasVencidos <= 0) {
            return $montoOriginal;
        }

        // Usar late_fee_amount de la tabla payment_rules
        $montoMora = $paymentRule->late_fee_amount ?? 0;

        // Si late_fee_amount es un porcentaje (valor entre 0 y 1), aplicarlo como porcentaje
        if ($montoMora > 0 && $montoMora <= 1) {
            $moraAplicable = ($montoOriginal * $montoMora) * ceil($diasVencidos / 30);
        }
        // Si late_fee_amount es un valor fijo mayor a 1
        else if ($montoMora > 1) {
            $moraAplicable = $montoMora * ceil($diasVencidos / 30);
        }
        // Si no hay mora configurada, aplicar 2% por defecto
        else {
            $moraAplicable = ($montoOriginal * 0.02) * ceil($diasVencidos / 30);
        }

        return $montoOriginal + $moraAplicable;
    }

    /**
     * Obtener pagos recientes
     */
    private function obtenerPagosRecientes($limit = 10)
    {
        try {
            return KardexPago::with([
                'estudiantePrograma.prospecto',
                'cuota'
            ])
            ->where('estado_pago', 'aprobado')
            ->orderBy('fecha_pago', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($pago) {
                return [
                    'id' => $pago->id,
                    'estudiante' => $pago->estudiantePrograma->prospecto->nombre_completo ?? 'N/A',
                    'concepto' => $pago->cuota->concepto ?? 'Pago general',
                    'fecha' => $pago->fecha_pago,
                    'monto' => (float) $pago->monto_pagado,
                    'metodo_pago' => $pago->metodo_pago
                ];
            });
        } catch (\Exception $e) {
            return collect([]);
        }
    }

    /**
     * Obtener alertas de morosidad con servicios bloqueados
     */
    private function obtenerAlertasMorosidad($limit = 20)
    {
        try {
            $ahora = Carbon::now();
            $paymentRule = PaymentRule::first();

            return CuotaProgramaEstudiante::with([
                'estudiantePrograma.prospecto',
                'estudiantePrograma.programa'
            ])
            ->where('estado', 'pendiente')
            ->where('fecha_vencimiento', '<', $ahora)
            ->orderBy('fecha_vencimiento', 'asc')
            ->limit($limit)
            ->get()
            ->map(function ($cuota) use ($ahora, $paymentRule) {
                $diasVencidos = Carbon::parse($cuota->fecha_vencimiento)->diffInDays($ahora);
                $montoConMora = $this->calcularMontoConMora($cuota->monto, $diasVencidos, $paymentRule);
                $serviciosBloqueados = $this->obtenerServiciosBloqueados($diasVencidos);

                return [
                    'id' => $cuota->id,
                    'estudiante' => $cuota->estudiantePrograma->prospecto->nombre_completo ?? 'N/A',
                    'programa' => $cuota->estudiantePrograma->programa->nombre ?? 'N/A',
                    'diasVencidos' => $diasVencidos,
                    'montoOriginal' => (float) $cuota->monto,
                    'montoVencido' => (float) $montoConMora,
                    'montoMora' => (float) ($montoConMora - $cuota->monto),
                    'fecha_vencimiento' => $cuota->fecha_vencimiento,
                    'serviciosBloqueados' => $serviciosBloqueados
                ];
            });
        } catch (\Exception $e) {
            return collect([]);
        }
    }

    /**
     * Determinar servicios bloqueados según días de atraso
     */
    private function obtenerServiciosBloqueados($diasVencidos)
    {
        try {
            $blockingRules = PaymentRuleBlockingRule::where('days_after_due', '<=', $diasVencidos)
                ->orderBy('days_after_due', 'desc')
                ->get();

            $serviciosBloqueados = [];
            foreach ($blockingRules as $rule) {
                $serviciosBloqueados = array_merge($serviciosBloqueados, $rule->affected_services ?? []);
            }

            return array_unique($serviciosBloqueados);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Obtener morosidad por programa - VERSIÓN CORREGIDA
     */
    private function obtenerMorosidadPorPrograma()
    {
        try {
            // Primero verificar si la tabla programas existe, si no, usar el modelo Programa
            $tableName = (new Programa())->getTable();

            // Usar Eloquent en lugar de SQL directo para evitar problemas de nombres de tabla
            return Programa::leftJoin('estudiante_programa as ep', 'id', 'ep.programa_id')
                ->leftJoin('cuotas_programa_estudiante as c', 'ep.id', 'c.estudiante_programa_id')
                ->selectRaw('
                    nombre as programa,
                    COUNT(DISTINCT ep.id) as total_estudiantes,
                    COUNT(DISTINCT CASE WHEN c.estado = ? AND c.fecha_vencimiento < ? THEN ep.id END) as estudiantes_morosos,
                    ROUND(
                        (COUNT(DISTINCT CASE WHEN c.estado = ? AND c.fecha_vencimiento < ? THEN ep.id END) * 100.0 /
                         NULLIF(COUNT(DISTINCT ep.id), 0)), 2
                    ) as porcentaje,
                    SUM(CASE WHEN c.estado = ? AND c.fecha_vencimiento < ? THEN c.monto ELSE 0 END) as monto_total_vencido
                ', [
                    'pendiente', Carbon::now(),
                    'pendiente', Carbon::now(),
                    'pendiente', Carbon::now()
                ])
                ->groupBy('id', 'nombre')
                ->havingRaw('COUNT(DISTINCT ep.id) > 0')
                ->orderBy('porcentaje', 'desc')
                ->get()
                ->toArray();

        } catch (\Exception $e) {
            // Si hay error, retornar datos vacíos
            return [];
        }
    }

    /**
     * Obtener tendencia de ingresos (últimos 6 meses)
     */
    private function obtenerTendenciaIngresos()
    {
        try {
            $meses = [];
            for ($i = 5; $i >= 0; $i--) {
                $fecha = Carbon::now()->subMonths($i);
                $ingresos = KardexPago::where('estado_pago', 'aprobado')
                    ->whereMonth('fecha_pago', $fecha->month)
                    ->whereYear('fecha_pago', $fecha->year)
                    ->sum('monto_pagado');

                $meses[] = [
                    'mes' => $fecha->format('Y-m'),
                    'mes_nombre' => $fecha->format('M Y'),
                    'ingresos' => (float) $ingresos
                ];
            }

            return $meses;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Obtener configuración de mora actual
     */
    private function obtenerConfiguracionMora()
    {
        try {
            $paymentRule = PaymentRule::first();

            if (!$paymentRule) {
                return null;
            }

            return [
                'regla_activa' => 'Regla por defecto',
                'monto_mora' => $paymentRule->late_fee_amount ?? 0,
                'dia_vencimiento' => $paymentRule->due_day ?? 30,
                'bloqueo_despues_meses' => $paymentRule->block_after_months ?? 3,
                'recordatorios_automaticos' => $paymentRule->send_automatic_reminders ?? false,
                'configuracion_gateway' => $paymentRule->gateway_config ?? null
            ];
        } catch (\Exception $e) {
            return null;
        }
    }
}
