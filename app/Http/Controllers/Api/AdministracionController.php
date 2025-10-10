<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\EstudiantePrograma;
use App\Models\Programa;
use App\Models\Course;
use App\Models\Prospecto;
use App\Models\PeriodoInscripcion;
use App\Models\InscripcionPeriodo;

class AdministracionController extends Controller
{
    /**
     * Dashboard administrativo principal
     * Retorna estadísticas generales del sistema académico
     */
    public function dashboard(Request $request)
    {
        try {
            $ahora = Carbon::now();
            $mesAnterior = Carbon::now()->subMonth();
            
            return response()->json([
                'matriculas' => $this->obtenerMatriculas($ahora, $mesAnterior),
                'alumnosNuevos' => $this->obtenerAlumnosNuevos($ahora, $mesAnterior),
                'proximosInicios' => $this->obtenerProximosInicios($ahora),
                'graduaciones' => $this->obtenerGraduaciones($ahora),
                'evolucionMatricula' => $this->obtenerEvolucionMatricula($request->get('periodo', '6meses')),
                'distribucionProgramas' => $this->obtenerDistribucionProgramas(),
                'notificaciones' => $this->obtenerNotificaciones($ahora),
                'estadisticas' => $this->obtenerEstadisticasGenerales()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener datos del dashboard',
                'message' => $e->getMessage(),
                'debug' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * Obtener matrículas del mes actual vs mes anterior
     */
    private function obtenerMatriculas($ahora, $mesAnterior)
    {
        // Matrículas del mes actual
        $matriculasMesActual = EstudiantePrograma::whereYear('created_at', $ahora->year)
            ->whereMonth('created_at', $ahora->month)
            ->count();

        // Matrículas del mes anterior
        $matriculasMesAnterior = EstudiantePrograma::whereYear('created_at', $mesAnterior->year)
            ->whereMonth('created_at', $mesAnterior->month)
            ->count();

        // Calcular porcentaje de cambio
        $porcentajeCambio = $matriculasMesAnterior > 0 
            ? round((($matriculasMesActual - $matriculasMesAnterior) / $matriculasMesAnterior) * 100, 2)
            : 0;

        return [
            'total' => $matriculasMesActual,
            'mesAnterior' => $matriculasMesAnterior,
            'porcentajeCambio' => $porcentajeCambio
        ];
    }

    /**
     * Obtener alumnos nuevos (prospectos convertidos) del mes
     */
    private function obtenerAlumnosNuevos($ahora, $mesAnterior)
    {
        // Nuevos estudiantes del mes actual
        $nuevosMesActual = Prospecto::whereHas('programas', function($q) use ($ahora) {
            $q->whereYear('created_at', $ahora->year)
              ->whereMonth('created_at', $ahora->month);
        })->count();

        // Nuevos estudiantes del mes anterior
        $nuevosMesAnterior = Prospecto::whereHas('programas', function($q) use ($mesAnterior) {
            $q->whereYear('created_at', $mesAnterior->year)
              ->whereMonth('created_at', $mesAnterior->month);
        })->count();

        $porcentajeCambio = $nuevosMesAnterior > 0 
            ? round((($nuevosMesActual - $nuevosMesAnterior) / $nuevosMesAnterior) * 100, 2)
            : 0;

        return [
            'total' => $nuevosMesActual,
            'mesAnterior' => $nuevosMesAnterior,
            'porcentajeCambio' => $porcentajeCambio
        ];
    }

    /**
     * Obtener próximos inicios de cursos en los próximos 30 días
     */
    private function obtenerProximosInicios($ahora)
    {
        $fechaLimite = $ahora->copy()->addDays(30);

        $proximosInicios = Course::whereBetween('start_date', [$ahora, $fechaLimite])
            ->where('status', '!=', 'cancelled')
            ->count();

        // También considerar periodos de inscripción próximos
        $periodosProximos = PeriodoInscripcion::where('activo', true)
            ->whereBetween('fecha_inicio', [$ahora, $fechaLimite])
            ->count();

        return [
            'total' => $proximosInicios + $periodosProximos,
            'cursos' => $proximosInicios,
            'periodos' => $periodosProximos,
            'proximos30Dias' => true
        ];
    }

    /**
     * Obtener graduaciones próximas (próximo trimestre)
     */
    private function obtenerGraduaciones($ahora)
    {
        $finTrimestre = $ahora->copy()->addMonths(3);

        // Estudiantes que finalizan su programa en el próximo trimestre
        $graduacionesProximas = EstudiantePrograma::whereBetween('fecha_fin', [$ahora, $finTrimestre])
            ->whereNotNull('fecha_fin')
            ->count();

        return [
            'total' => $graduacionesProximas,
            'proximoTrimestre' => true,
            'fechaInicio' => $ahora->format('Y-m-d'),
            'fechaFin' => $finTrimestre->format('Y-m-d')
        ];
    }

    /**
     * Obtener evolución de matrícula por periodo
     */
    private function obtenerEvolucionMatricula($periodo)
    {
        $ahora = Carbon::now();
        $mesesAtras = 6; // Por defecto 6 meses

        switch ($periodo) {
            case '1año':
            case '1año':
                $mesesAtras = 12;
                break;
            case 'todo':
                $mesesAtras = 60; // 5 años
                break;
        }

        $datos = [];
        for ($i = $mesesAtras - 1; $i >= 0; $i--) {
            $fecha = $ahora->copy()->subMonths($i);
            $count = EstudiantePrograma::whereYear('created_at', $fecha->year)
                ->whereMonth('created_at', $fecha->month)
                ->count();

            $datos[] = [
                'mes' => $fecha->locale('es')->isoFormat('MMM YYYY'),
                'total' => $count
            ];
        }

        return $datos;
    }

    /**
     * Obtener distribución de alumnos por programas
     */
    private function obtenerDistribucionProgramas()
    {
        $distribucion = Programa::leftJoin('estudiante_programa', 'tb_programas.id', '=', 'estudiante_programa.programa_id')
            ->select(
                'tb_programas.nombre_del_programa as nombre',
                'tb_programas.abreviatura',
                DB::raw('COUNT(estudiante_programa.id) as total_estudiantes')
            )
            ->groupBy('tb_programas.id', 'tb_programas.nombre_del_programa', 'tb_programas.abreviatura')
            ->orderByDesc('total_estudiantes')
            ->get();

        return $distribucion->map(function($item) {
            return [
                'programa' => $item->nombre,
                'abreviatura' => $item->abreviatura,
                'totalEstudiantes' => (int) $item->total_estudiantes
            ];
        });
    }

    /**
     * Obtener notificaciones importantes
     */
    private function obtenerNotificaciones($ahora)
    {
        // Solicitudes pendientes (prospectos en proceso)
        $solicitudesPendientes = Prospecto::where('status', 'Seguimiento')
            ->orWhere('status', 'En Proceso')
            ->count();

        // Graduaciones próximas
        $graduacionesProximas = EstudiantePrograma::whereBetween('fecha_fin', [
            $ahora,
            $ahora->copy()->addMonths(3)
        ])->count();

        // Cursos por finalizar en próximos 15 días
        $cursosPorFinalizar = Course::whereBetween('end_date', [
            $ahora,
            $ahora->copy()->addDays(15)
        ])->where('status', '!=', 'cancelled')
          ->count();

        return [
            'solicitudesPendientes' => [
                'total' => $solicitudesPendientes,
                'mensaje' => "Hay {$solicitudesPendientes} solicitudes pendientes de revisión"
            ],
            'graduacionesProximas' => [
                'total' => $graduacionesProximas,
                'mensaje' => "{$graduacionesProximas} alumnos se graduarán en el próximo trimestre"
            ],
            'cursosPorFinalizar' => [
                'total' => $cursosPorFinalizar,
                'mensaje' => "{$cursosPorFinalizar} cursos finalizarán en los próximos 15 días"
            ]
        ];
    }

    /**
     * Obtener estadísticas generales adicionales
     */
    private function obtenerEstadisticasGenerales()
    {
        // Estudiantes inscritos en múltiples programas
        $estudiantesMultiplesProgramas = DB::table('estudiante_programa')
            ->select('prospecto_id', DB::raw('COUNT(*) as total_programas'))
            ->groupBy('prospecto_id')
            ->havingRaw('COUNT(*) > ?', [1])
            ->get();

        $topEstudiantes = $estudiantesMultiplesProgramas->sortByDesc('total_programas')->take(5);

        return [
            'totalEstudiantes' => Prospecto::whereHas('programas')->count(),
            'totalProgramas' => Programa::where('activo', 1)->count(),
            'totalCursos' => Course::count(),
            'estudiantesEnMultiplesProgramas' => [
                'total' => $estudiantesMultiplesProgramas->count(),
                'promedio' => $estudiantesMultiplesProgramas->avg('total_programas'),
                'maximo' => $estudiantesMultiplesProgramas->max('total_programas'),
                'top5' => $topEstudiantes->values()
            ]
        ];
    }

    /**
     * Exportar datos del dashboard
     */
    public function exportar(Request $request)
    {
        $formato = $request->get('formato', 'json'); // json, excel, pdf
        
        // Por ahora retornamos JSON
        // En el futuro se puede implementar Excel y PDF
        $datos = $this->dashboard($request)->getData();
        
        return response()->json([
            'formato' => $formato,
            'datos' => $datos,
            'exportado_en' => Carbon::now()->toDateTimeString()
        ]);
    }
}
