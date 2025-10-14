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
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\DashboardExport;
use App\Exports\ReportesMatriculaExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Validator;
use App\Exports\SimpleDashboardExport;

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
        $nuevosMesActual = Prospecto::whereHas('programas', function ($q) use ($ahora) {
            $q->whereYear('created_at', $ahora->year)
                ->whereMonth('created_at', $ahora->month);
        })->count();

        // Nuevos estudiantes del mes anterior
        $nuevosMesAnterior = Prospecto::whereHas('programas', function ($q) use ($mesAnterior) {
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
        $distribucion = Programa::leftJoin('estudiante_programa', function ($join) {
            $join->on('tb_programas.id', '=', 'estudiante_programa.programa_id')
                ->whereNull('estudiante_programa.deleted_at');
        })
            ->leftJoin('prospectos', 'estudiante_programa.prospecto_id', '=', 'prospectos.id')
            ->select(
                'tb_programas.id',
                'tb_programas.nombre_del_programa as nombre',
                'tb_programas.abreviatura',
                DB::raw('COUNT(DISTINCT estudiante_programa.id) as total_estudiantes')
            )
            ->where('tb_programas.activo', 1)
            ->groupBy('tb_programas.id', 'tb_programas.nombre_del_programa', 'tb_programas.abreviatura')
            ->orderByDesc('total_estudiantes')
            ->orderBy('tb_programas.nombre_del_programa')
            ->get();

        return $distribucion->map(function ($item) {
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
        $formato = $request->get('formato', 'json');

        try {
            switch ($formato) {
                case 'excel':
                case 'xlsx':
                    // Obtener datos del dashboard directamente (no como response)
                    $dashboardResponse = $this->dashboard($request);
                    $dashboardData = $dashboardResponse->getData(true); // true para obtener array asociativo

                    // Crear el export usando la versión simple primero
                    $export = new SimpleDashboardExport($dashboardData);

                    $filename = 'dashboard_administrativo_' . Carbon::now()->format('Y-m-d_H-i-s') . '.xlsx';

                    return Excel::download($export, $filename, \Maatwebsite\Excel\Excel::XLSX, [
                        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ]);

                case 'csv':
                    $dashboardResponse = $this->dashboard($request);
                    $dashboardData = $dashboardResponse->getData(true);
                    $export = new DashboardExport($dashboardData);

                    $filename = 'dashboard_administrativo_' . Carbon::now()->format('Y-m-d_H-i-s') . '.csv';

                    return Excel::download($export, $filename, \Maatwebsite\Excel\Excel::CSV, [
                        'Content-Type' => 'text/csv',
                    ]);

                case 'json':
                default:
                    $dashboardResponse = $this->dashboard($request);
                    $datos = $dashboardResponse->getData(true);

                    return response()->json([
                        'formato' => $formato,
                        'datos' => $datos,
                        'exportado_en' => Carbon::now()->toDateTimeString()
                    ]);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error al exportar dashboard', [
                'formato' => $formato,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Error al exportar datos',
                'message' => $e->getMessage()
            ], 500);
        }
    }

     /**
     * Exportar datos del dashboard
     */

    /**
     * Reportes de Matrícula y Alumnos Nuevos
     * Endpoint principal para consultar reportes con filtros
     */
    public function reportesMatricula(Request $request)
    {
        try {
            // Validar parámetros
            $validator = Validator::make($request->all(), [
                'rango' => 'nullable|in:month,quarter,semester,year,custom',
                'fechaInicio' => 'nullable|date|required_if:rango,custom',
                'fechaFin' => 'nullable|date|required_if:rango,custom|after_or_equal:fechaInicio',
                'programaId' => 'nullable|string',
                'tipoAlumno' => 'nullable|in:all,Nuevo,Recurrente',
                'page' => 'nullable|integer|min:1',
                'perPage' => 'nullable|integer|min:1|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Parámetros inválidos',
                    'messages' => $validator->errors()
                ], 422);
            }

            // Obtener parámetros
            $rango = $request->get('rango', 'month');
            $fechaInicio = $request->get('fechaInicio');
            $fechaFin = $request->get('fechaFin');
            $programaId = $request->get('programaId', 'all');
            $tipoAlumno = $request->get('tipoAlumno', 'all');
            $page = $request->get('page', 1);
            $perPage = $request->get('perPage', 50);

            // Calcular rangos de fecha
            $rangoFechas = $this->obtenerRangoFechas($rango, $fechaInicio, $fechaFin);
            $rangoAnterior = $this->obtenerRangoAnterior($rango, $rangoFechas['fechaInicio'], $rangoFechas['fechaFin']);

            // Obtener datos del período actual
            $periodoActual = $this->obtenerDatosPeriodo(
                $rangoFechas['fechaInicio'],
                $rangoFechas['fechaFin'],
                $programaId,
                $tipoAlumno,
                $rangoFechas['descripcion']
            );

            // Obtener datos del período anterior
            $periodoAnterior = $this->obtenerDatosPeriodoAnterior(
                $rangoAnterior['fechaInicio'],
                $rangoAnterior['fechaFin'],
                $programaId,
                $tipoAlumno,
                $rangoAnterior['descripcion']
            );

            // Calcular comparativas
            $comparativa = $this->obtenerComparativa($periodoActual['totales'], $periodoAnterior['totales']);

            // Obtener tendencias
            $tendencias = $this->obtenerTendencias($programaId, $rangoFechas['fechaInicio']);

            // Obtener listado paginado
            $listado = $this->obtenerListadoAlumnos(
                $rangoFechas['fechaInicio'],
                $rangoFechas['fechaFin'],
                $programaId,
                $tipoAlumno,
                $page,
                $perPage
            );

            // Construir respuesta
            return response()->json([
                'filtros' => $this->obtenerFiltrosDisponibles(),
                'periodoActual' => $periodoActual,
                'periodoAnterior' => $periodoAnterior,
                'comparativa' => $comparativa,
                'tendencias' => $tendencias,
                'listado' => $listado
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener reportes de matrícula',
                'message' => $e->getMessage(),
                'debug' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * Exportar reportes de matrícula
     */
    public function exportarReportesMatricula(Request $request)
    {
        try {
            // Validar parámetros
            $validator = Validator::make($request->all(), [
                'formato' => 'required|in:pdf,excel,csv',
                'detalle' => 'nullable|in:complete,summary,data',
                'incluirGraficas' => 'nullable|boolean',
                'rango' => 'nullable|in:month,quarter,semester,year,custom',
                'fechaInicio' => 'nullable|date|required_if:rango,custom',
                'fechaFin' => 'nullable|date|required_if:rango,custom|after_or_equal:fechaInicio',
                'programaId' => 'nullable|string',
                'tipoAlumno' => 'nullable|in:all,Nuevo,Recurrente'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Parámetros inválidos',
                    'messages' => $validator->errors()
                ], 422);
            }

            $formato = $request->get('formato');
            $detalle = $request->get('detalle', 'complete');

            // Obtener datos del reporte (reutilizando el endpoint principal)
            $reporteRequest = new Request($request->except(['formato', 'detalle', 'incluirGraficas']));
            $reporteResponse = $this->reportesMatricula($reporteRequest);
            $datos = json_decode($reporteResponse->getContent());

            // Auditoría
            \Illuminate\Support\Facades\Log::info('Exportación de reportes de matrícula', [
                'user_id' => auth()->id(),
                'formato' => $formato,
                'detalle' => $detalle,
                'filtros' => $request->except(['formato', 'detalle', 'incluirGraficas'])
            ]);

            $filename = 'reportes_matricula_' . Carbon::now()->format('Y-m-d_H-i-s');

            switch ($formato) {
                case 'pdf':
                    $pdf = Pdf::loadView('pdf.reportes-matricula', [
                        'datos' => $datos,
                        'detalle' => $detalle,
                        'fecha' => Carbon::now()->format('d/m/Y H:i:s')
                    ]);

                    return $pdf->download($filename . '.pdf');

                case 'excel':
                    $export = new ReportesMatriculaExport($datos, $detalle);
                    return Excel::download($export, $filename . '.xlsx');

                case 'csv':
                    $export = new ReportesMatriculaExport($datos, $detalle);
                    return Excel::download($export, $filename . '.csv', \Maatwebsite\Excel\Excel::CSV, [
                        'Content-Type' => 'text/csv; charset=UTF-8',
                    ]);
            }

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error al exportar reportes', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Error al exportar reportes',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Endpoint simplificado para estudiantes matriculados
     * GET /api/administracion/estudiantes-matriculados
     */
    public function estudiantesMatriculados(Request $request)
    {
        try {
            // Parámetros de paginación
            $page = max(1, (int) $request->get('page', 1));
            $perPage = min(100, max(1, (int) $request->get('perPage', 50)));

            // Parámetros de filtrado
            $programaId = $request->get('programaId', 'all');
            $tipoAlumno = $request->get('tipoAlumno', 'all');
            
            // Rango de fechas (por defecto mes actual)
            $fechaInicio = $request->get('fechaInicio', Carbon::now()->startOfMonth()->format('Y-m-d'));
            $fechaFin = $request->get('fechaFin', Carbon::now()->endOfMonth()->format('Y-m-d'));

            // Validar fechas
            try {
                $fechaInicio = Carbon::parse($fechaInicio)->startOfDay();
                $fechaFin = Carbon::parse($fechaFin)->endOfDay();
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Fechas inválidas',
                    'message' => 'Las fechas deben estar en formato Y-m-d'
                ], 422);
            }

            // Validar que fechaFin sea posterior a fechaInicio
            if ($fechaFin->lt($fechaInicio)) {
                return response()->json([
                    'error' => 'Rango de fechas inválido',
                    'message' => 'La fecha fin debe ser posterior a la fecha inicio'
                ], 422);
            }

            // Obtener datos utilizando el método privado optimizado
            $resultado = $this->obtenerListadoAlumnos(
                $fechaInicio,
                $fechaFin,
                $programaId,
                $tipoAlumno,
                $page,
                $perPage
            );

            return response()->json($resultado);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error en estudiantesMatriculados', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'error' => 'Error al obtener estudiantes matriculados',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exportar estudiantes matriculados a PDF, Excel o CSV
     * POST /api/administracion/estudiantes-matriculados/exportar
     */
    public function exportarEstudiantesMatriculados(Request $request)
    {
        try {
            // Validar parámetros
            $validator = Validator::make($request->all(), [
                'formato' => 'required|in:pdf,excel,csv',
                'fechaInicio' => 'nullable|date',
                'fechaFin' => 'nullable|date|after_or_equal:fechaInicio',
                'programaId' => 'nullable|string',
                'tipoAlumno' => 'nullable|in:all,Nuevo,Recurrente',
                'incluirTodos' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Parámetros inválidos',
                    'messages' => $validator->errors()
                ], 422);
            }

            $formato = $request->get('formato');
            $incluirTodos = $request->get('incluirTodos', false);

            // Parámetros de filtrado
            $programaId = $request->get('programaId', 'all');
            $tipoAlumno = $request->get('tipoAlumno', 'all');
            
            // Rango de fechas (por defecto mes actual)
            $fechaInicio = $request->get('fechaInicio', Carbon::now()->startOfMonth()->format('Y-m-d'));
            $fechaFin = $request->get('fechaFin', Carbon::now()->endOfMonth()->format('Y-m-d'));

            $fechaInicio = Carbon::parse($fechaInicio)->startOfDay();
            $fechaFin = Carbon::parse($fechaFin)->endOfDay();

            // Si incluirTodos es true, obtener todos los datos sin paginación
            if ($incluirTodos) {
                $perPage = 10000; // Límite alto para obtener todos
                $page = 1;
            } else {
                $page = 1;
                $perPage = 1000; // Límite razonable para exportación
            }

            // Obtener datos
            $resultado = $this->obtenerListadoAlumnos(
                $fechaInicio,
                $fechaFin,
                $programaId,
                $tipoAlumno,
                $page,
                $perPage
            );

            // Auditoría
            \Illuminate\Support\Facades\Log::info('Exportación de estudiantes matriculados', [
                'user_id' => auth()->id(),
                'formato' => $formato,
                'filtros' => [
                    'fechaInicio' => $fechaInicio->format('Y-m-d'),
                    'fechaFin' => $fechaFin->format('Y-m-d'),
                    'programaId' => $programaId,
                    'tipoAlumno' => $tipoAlumno
                ]
            ]);

            $filename = 'estudiantes_matriculados_' . Carbon::now()->format('Y-m-d_H-i-s');

            // Para simplificar, retornar los datos en JSON
            // En una implementación completa, se crearían las clases Export correspondientes
            switch ($formato) {
                case 'pdf':
                case 'excel':
                case 'csv':
                    // Por ahora retornamos JSON con headers apropiados
                    // En producción, se debería implementar con Excel y PDF facades
                    $contentType = $formato === 'csv' ? 'text/csv' : 'application/json';
                    return response()->json($resultado, 200, [
                        'Content-Type' => $contentType,
                        'Content-Disposition' => 'attachment; filename="' . $filename . '.' . $formato . '"'
                    ]);
            }

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error al exportar estudiantes matriculados', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Error al exportar estudiantes matriculados',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener filtros disponibles
     */
    private function obtenerFiltrosDisponibles()
    {
        $programas = Programa::where('activo', 1)
            ->select('id', 'nombre_del_programa as nombre')
            ->get()
            ->map(function ($p) {
                return [
                    'id' => (string) $p->id,
                    'nombre' => $p->nombre
                ];
            });

        return [
            'rangosDisponibles' => ['month', 'quarter', 'semester', 'year', 'custom'],
            'programas' => $programas,
            'tiposAlumno' => ['Nuevo', 'Recurrente']
        ];
    }

    /**
     * Calcular rango de fechas según el tipo
     */
    private function obtenerRangoFechas($rango, $fechaInicio = null, $fechaFin = null)
    {
        $ahora = Carbon::now();

        switch ($rango) {
            case 'month':
                $inicio = $ahora->copy()->startOfMonth();
                $fin = $ahora->copy()->endOfMonth();
                $descripcion = $inicio->locale('es')->isoFormat('MMMM YYYY');
                break;

            case 'quarter':
                $mes = $ahora->month;
                $trimestre = ceil($mes / 3);
                $inicio = $ahora->copy()->month(($trimestre - 1) * 3 + 1)->startOfMonth();
                $fin = $ahora->copy()->month($trimestre * 3)->endOfMonth();
                $descripcion = "Q{$trimestre} " . $ahora->year;
                break;

            case 'semester':
                $semestre = $ahora->month <= 6 ? 1 : 2;
                if ($semestre == 1) {
                    $inicio = $ahora->copy()->month(1)->startOfMonth();
                    $fin = $ahora->copy()->month(6)->endOfMonth();
                } else {
                    $inicio = $ahora->copy()->month(7)->startOfMonth();
                    $fin = $ahora->copy()->month(12)->endOfMonth();
                }
                $descripcion = "Semestre {$semestre} " . $ahora->year;
                break;

            case 'year':
                $inicio = $ahora->copy()->startOfYear();
                $fin = $ahora->copy()->endOfYear();
                $descripcion = "Año " . $ahora->year;
                break;

            case 'custom':
                $inicio = Carbon::parse($fechaInicio)->startOfDay();
                $fin = Carbon::parse($fechaFin)->endOfDay();
                $descripcion = $inicio->format('d/m/Y') . ' - ' . $fin->format('d/m/Y');
                break;

            default:
                $inicio = $ahora->copy()->startOfMonth();
                $fin = $ahora->copy()->endOfMonth();
                $descripcion = $inicio->locale('es')->isoFormat('MMMM YYYY');
        }

        return [
            'fechaInicio' => $inicio->format('Y-m-d'),
            'fechaFin' => $fin->format('Y-m-d'),
            'descripcion' => $descripcion
        ];
    }

    /**
     * Calcular rango anterior con la misma duración
     */
    private function obtenerRangoAnterior($rango, $fechaInicio, $fechaFin)
    {
        $inicio = Carbon::parse($fechaInicio);
        $fin = Carbon::parse($fechaFin);
        $duracion = $inicio->diffInDays($fin) + 1;

        $anteriorFin = $inicio->copy()->subDay();
        $anteriorInicio = $anteriorFin->copy()->subDays($duracion - 1);

        // Descripción según el tipo de rango
        if ($rango === 'month') {
            $descripcion = $anteriorInicio->locale('es')->isoFormat('MMMM YYYY');
        } elseif ($rango === 'quarter') {
            $mes = $anteriorInicio->month;
            $trimestre = ceil($mes / 3);
            $descripcion = "Q{$trimestre} " . $anteriorInicio->year;
        } elseif ($rango === 'semester') {
            $semestre = $anteriorInicio->month <= 6 ? 1 : 2;
            $descripcion = "Semestre {$semestre} " . $anteriorInicio->year;
        } elseif ($rango === 'year') {
            $descripcion = "Año " . $anteriorInicio->year;
        } else {
            $descripcion = $anteriorInicio->format('d/m/Y') . ' - ' . $anteriorFin->format('d/m/Y');
        }

        return [
            'fechaInicio' => $anteriorInicio->format('Y-m-d'),
            'fechaFin' => $anteriorFin->format('Y-m-d'),
            'descripcion' => $descripcion
        ];
    }

    /**
     * Obtener datos del período actual
     */
    private function obtenerDatosPeriodo($fechaInicio, $fechaFin, $programaId, $tipoAlumno, $descripcion)
    {
        $query = EstudiantePrograma::whereBetween('created_at', [$fechaInicio, $fechaFin]);

        if ($programaId !== 'all') {
            $query->where('programa_id', $programaId);
        }

        // Total matriculados
        $totalMatriculados = $query->count();

        // Alumnos nuevos
        $alumnosNuevos = $this->contarAlumnosNuevos($fechaInicio, $fechaFin, $programaId);

        // Alumnos recurrentes
        $alumnosRecurrentes = $totalMatriculados - $alumnosNuevos;

        // Aplicar filtro de tipo si no es 'all'
        if ($tipoAlumno === 'Nuevo') {
            $totalMatriculados = $alumnosNuevos;
            $alumnosRecurrentes = 0;
        } elseif ($tipoAlumno === 'Recurrente') {
            $totalMatriculados = $alumnosRecurrentes;
            $alumnosNuevos = 0;
        }

        return [
            'rango' => [
                'fechaInicio' => $fechaInicio,
                'fechaFin' => $fechaFin,
                'descripcion' => $descripcion
            ],
            'totales' => [
                'matriculados' => $totalMatriculados,
                'alumnosNuevos' => $alumnosNuevos,
                'alumnosRecurrentes' => $alumnosRecurrentes
            ],
            'distribucionProgramas' => $this->obtenerDistribucionProgramasRango($fechaInicio, $fechaFin, $programaId),
            'evolucionMensual' => $this->obtenerEvolucionMensualRango($fechaInicio, $fechaFin, $programaId),
            'distribucionTipo' => [
                ['tipo' => 'Nuevo', 'total' => $alumnosNuevos],
                ['tipo' => 'Recurrente', 'total' => $alumnosRecurrentes]
            ]
        ];
    }

    /**
     * Obtener datos del período anterior
     */
    private function obtenerDatosPeriodoAnterior($fechaInicio, $fechaFin, $programaId, $tipoAlumno, $descripcion)
    {
        $query = EstudiantePrograma::whereBetween('created_at', [$fechaInicio, $fechaFin]);

        if ($programaId !== 'all') {
            $query->where('programa_id', $programaId);
        }

        $totalMatriculados = $query->count();
        $alumnosNuevos = $this->contarAlumnosNuevos($fechaInicio, $fechaFin, $programaId);
        $alumnosRecurrentes = $totalMatriculados - $alumnosNuevos;

        if ($tipoAlumno === 'Nuevo') {
            $totalMatriculados = $alumnosNuevos;
            $alumnosRecurrentes = 0;
        } elseif ($tipoAlumno === 'Recurrente') {
            $totalMatriculados = $alumnosRecurrentes;
            $alumnosNuevos = 0;
        }

        return [
            'totales' => [
                'matriculados' => $totalMatriculados,
                'alumnosNuevos' => $alumnosNuevos,
                'alumnosRecurrentes' => $alumnosRecurrentes
            ],
            'rangoComparado' => [
                'fechaInicio' => $fechaInicio,
                'fechaFin' => $fechaFin,
                'descripcion' => $descripcion
            ]
        ];
    }

    /**
     * Calcular comparativas con variaciones
     */
    private function obtenerComparativa($totalesActual, $totalesAnterior)
    {
        return [
            'totales' => [
                'actual' => $totalesActual['matriculados'],
                'anterior' => $totalesAnterior['matriculados'],
                'variacion' => $this->calcularVariacion($totalesAnterior['matriculados'], $totalesActual['matriculados'])
            ],
            'nuevos' => [
                'actual' => $totalesActual['alumnosNuevos'],
                'anterior' => $totalesAnterior['alumnosNuevos'],
                'variacion' => $this->calcularVariacion($totalesAnterior['alumnosNuevos'], $totalesActual['alumnosNuevos'])
            ],
            'recurrentes' => [
                'actual' => $totalesActual['alumnosRecurrentes'],
                'anterior' => $totalesAnterior['alumnosRecurrentes'],
                'variacion' => $this->calcularVariacion($totalesAnterior['alumnosRecurrentes'], $totalesActual['alumnosRecurrentes'])
            ]
        ];
    }

    /**
     * Calcular variación porcentual
     */
    private function calcularVariacion($anterior, $actual)
    {
        if ($anterior == 0) {
            return $actual > 0 ? 100 : 0;
        }
        return round((($actual - $anterior) / $anterior) * 100, 2);
    }

    /**
     * Contar alumnos nuevos (primera matrícula en el rango)
     */
    private function contarAlumnosNuevos($fechaInicio, $fechaFin, $programaId)
    {
        $query = DB::table('estudiante_programa as ep1')
            ->join(DB::raw('(SELECT prospecto_id, MIN(created_at) as primera_matricula
                            FROM estudiante_programa
                            WHERE deleted_at IS NULL
                            GROUP BY prospecto_id) as ep2'),
                   'ep1.prospecto_id', '=', 'ep2.prospecto_id')
            ->whereBetween('ep2.primera_matricula', [$fechaInicio, $fechaFin])
            ->whereNull('ep1.deleted_at');

        if ($programaId !== 'all') {
            $query->where('ep1.programa_id', $programaId);
        }

        return $query->distinct('ep1.prospecto_id')->count('ep1.prospecto_id');
    }

    /**
     * Obtener distribución por programas en un rango
     */
    private function obtenerDistribucionProgramasRango($fechaInicio, $fechaFin, $programaId)
    {
        $query = EstudiantePrograma::whereBetween('created_at', [$fechaInicio, $fechaFin])
            ->join('tb_programas', 'estudiante_programa.programa_id', '=', 'tb_programas.id')
            ->select('tb_programas.nombre_del_programa as programa', DB::raw('COUNT(*) as total'))
            ->groupBy('tb_programas.nombre_del_programa');

        if ($programaId !== 'all') {
            $query->where('estudiante_programa.programa_id', $programaId);
        }

        return $query->get()->toArray();
    }

    /**
     * Obtener evolución mensual en un rango
     */
    private function obtenerEvolucionMensualRango($fechaInicio, $fechaFin, $programaId)
    {
        $inicio = Carbon::parse($fechaInicio);
        $fin = Carbon::parse($fechaFin);

        $datos = [];
        $mesActual = $inicio->copy();

        while ($mesActual->lte($fin)) {
            $query = EstudiantePrograma::whereYear('created_at', $mesActual->year)
                ->whereMonth('created_at', $mesActual->month);

            if ($programaId !== 'all') {
                $query->where('programa_id', $programaId);
            }

            $total = $query->count();

            $datos[] = [
                'mes' => $mesActual->format('Y-m'),
                'total' => $total
            ];

            $mesActual->addMonth();
        }

        return $datos;
    }

    /**
     * Obtener tendencias de 12 meses
     */
    private function obtenerTendencias($programaId, $fechaActual)
    {
        $hace12Meses = Carbon::parse($fechaActual)->subMonths(12);

        // Últimos 12 meses
        $ultimosDoceMeses = [];
        for ($i = 11; $i >= 0; $i--) {
            $fecha = Carbon::parse($fechaActual)->subMonths($i);
            $query = EstudiantePrograma::whereYear('created_at', $fecha->year)
                ->whereMonth('created_at', $fecha->month);

            if ($programaId !== 'all') {
                $query->where('programa_id', $programaId);
            }

            $ultimosDoceMeses[] = [
                'mes' => $fecha->format('Y-m'),
                'total' => $query->count()
            ];
        }

        // Crecimiento por programa (últimos 6 meses vs 6 anteriores)
        $crecimientoPorPrograma = $this->obtenerCrecimientoPorPrograma($fechaActual, $programaId);

        // Proyección simple (promedio últimos 3 meses)
        $proyeccion = $this->obtenerProyeccion($ultimosDoceMeses);

        return [
            'ultimosDoceMeses' => $ultimosDoceMeses,
            'crecimientoPorPrograma' => $crecimientoPorPrograma,
            'proyeccion' => $proyeccion
        ];
    }

    /**
     * Calcular crecimiento por programa
     */
    private function obtenerCrecimientoPorPrograma($fechaActual, $programaIdFiltro)
    {
        $hace6Meses = Carbon::parse($fechaActual)->subMonths(6);
        $hace12Meses = Carbon::parse($fechaActual)->subMonths(12);

        $programas = Programa::where('activo', 1)->get();
        $resultado = [];

        foreach ($programas as $programa) {
            if ($programaIdFiltro !== 'all' && $programa->id != $programaIdFiltro) {
                continue;
            }

            // Últimos 6 meses
            $recientes = EstudiantePrograma::where('programa_id', $programa->id)
                ->whereBetween('created_at', [$hace6Meses, $fechaActual])
                ->count();

            // 6 meses anteriores
            $anteriores = EstudiantePrograma::where('programa_id', $programa->id)
                ->whereBetween('created_at', [$hace12Meses, $hace6Meses])
                ->count();

            $variacion = $this->calcularVariacion($anteriores, $recientes);

            $resultado[] = [
                'programa' => $programa->nombre_del_programa,
                'variacion' => $variacion
            ];
        }

        return $resultado;
    }

    /**
     * Calcular proyección simple
     */
    private function obtenerProyeccion($ultimosDoceMeses)
    {
        if (count($ultimosDoceMeses) < 3) {
            return [];
        }

        // Promedio de los últimos 3 meses
        $ultimos3 = array_slice($ultimosDoceMeses, -3);
        $promedio = array_sum(array_column($ultimos3, 'total')) / 3;

        $proximoMes = Carbon::now()->addMonth();

        return [
            [
                'periodo' => $proximoMes->format('Y-m'),
                'totalEsperado' => round($promedio)
            ]
        ];
    }

    /**
     * Obtener listado paginado de alumnos
     */
    private function obtenerListadoAlumnos($fechaInicio, $fechaFin, $programaId, $tipoAlumno, $page, $perPage)
    {
        // Pre-calcular primera matrícula de todos los prospectos de una sola vez
        $primerasMatriculas = DB::table('estudiante_programa')
            ->select('prospecto_id', DB::raw('MIN(created_at) as primera_matricula'))
            ->whereNull('deleted_at')
            ->groupBy('prospecto_id');

        $query = EstudiantePrograma::whereBetween('estudiante_programa.created_at', [$fechaInicio, $fechaFin])
            ->join('prospectos', 'estudiante_programa.prospecto_id', '=', 'prospectos.id')
            ->join('tb_programas', 'estudiante_programa.programa_id', '=', 'tb_programas.id')
            ->leftJoinSub($primerasMatriculas, 'pm', function ($join) {
                $join->on('estudiante_programa.prospecto_id', '=', 'pm.prospecto_id');
            })
            ->select(
                'estudiante_programa.id',
                'estudiante_programa.prospecto_id',
                'prospectos.nombre_completo as nombre',
                'estudiante_programa.created_at as fechaMatricula',
                'tb_programas.nombre_del_programa as programa',
                'pm.primera_matricula',
                DB::raw("CASE WHEN prospectos.activo = TRUE THEN 'Activo' ELSE 'Inactivo' END as estado")
            );

        if ($programaId !== 'all') {
            $query->where('estudiante_programa.programa_id', $programaId);
        }

        // Filtrar por tipo de alumno
        if ($tipoAlumno === 'Nuevo') {
            $query->whereRaw('pm.primera_matricula BETWEEN ? AND ?', [$fechaInicio, $fechaFin]);
        } elseif ($tipoAlumno === 'Recurrente') {
            $nuevosIds = DB::table('estudiante_programa as ep1')
                ->join(DB::raw('(SELECT prospecto_id, MIN(created_at) as primera_matricula
                                FROM estudiante_programa
                                WHERE deleted_at IS NULL
                                GROUP BY prospecto_id) as ep2'),
                       'ep1.prospecto_id', '=', 'ep2.prospecto_id')
                ->whereBetween('ep2.primera_matricula', [$fechaInicio, $fechaFin])
                ->pluck('ep1.prospecto_id');

            $query->whereNotIn('estudiante_programa.prospecto_id', $nuevosIds);
        }

        $total = $query->count();
        $totalPaginas = ceil($total / $perPage);

        $alumnos = $query->orderBy('estudiante_programa.created_at', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(function ($alumno) use ($fechaInicio, $fechaFin) {
                // Determinar si es nuevo o recurrente usando la primera_matricula pre-calculada
                // Esto evita el problema N+1 de hacer queries adicionales por cada alumno
                $esNuevo = $alumno->primera_matricula 
                    ? Carbon::parse($alumno->primera_matricula)->between($fechaInicio, $fechaFin)
                    : false;

                return [
                    'id' => $alumno->id,
                    'nombre' => $alumno->nombre,
                    'fechaMatricula' => Carbon::parse($alumno->fechaMatricula)->format('Y-m-d'),
                    'tipo' => $esNuevo ? 'Nuevo' : 'Recurrente',
                    'programa' => $alumno->programa,
                    'estado' => $alumno->estado
                ];
            });

        return [
            'alumnos' => $alumnos,
            'paginacion' => [
                'pagina' => $page,
                'porPagina' => $perPage,
                'total' => $total,
                'totalPaginas' => $totalPaginas
            ]
        ];
    }
}

