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
                    // Obtener datos del dashboard
                    $dashboardData = $this->dashboard($request)->getData();

                    // Crear el export
                    $export = new DashboardExport($dashboardData);

                    $filename = 'dashboard_administrativo_' . Carbon::now()->format('Y-m-d_H-i-s') . '.xlsx';

                    return Excel::download($export, $filename);

                case 'csv':
                    $dashboardData = $this->dashboard($request)->getData();
                    $export = new DashboardExport($dashboardData);

                    $filename = 'dashboard_administrativo_' . Carbon::now()->format('Y-m-d_H-i-s') . '.csv';

                    return Excel::download($export, $filename, \Maatwebsite\Excel\Excel::CSV, [
                        'Content-Type' => 'text/csv',
                    ]);

                case 'json':
                default:
                    $datos = $this->dashboard($request)->getData();

                    return response()->json([
                        'formato' => $formato,
                        'datos' => $datos,
                        'exportado_en' => Carbon::now()->toDateTimeString()
                    ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al exportar datos',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reportes de Matrícula y Alumnos Nuevos
     * Endpoint principal para módulo /admin/reportes-matricula
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function reportesMatricula(Request $request)
    {
        try {
            // Validar parámetros de entrada
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
            $programaId = $request->get('programaId', 'all');
            $tipoAlumno = $request->get('tipoAlumno', 'all');
            $page = $request->get('page', 1);
            $perPage = $request->get('perPage', 50);

            // Calcular rangos de fechas
            $rangoActual = $this->obtenerRangoFechas($rango, $request->get('fechaInicio'), $request->get('fechaFin'));
            $rangoAnterior = $this->obtenerRangoAnterior($rango, $rangoActual);

            // Construir respuesta
            return response()->json([
                'filtros' => $this->obtenerFiltrosDisponibles(),
                'periodoActual' => $this->obtenerDatosPeriodo($rangoActual, $programaId, $tipoAlumno),
                'periodoAnterior' => $this->obtenerDatosPeriodoAnterior($rangoAnterior, $programaId, $tipoAlumno),
                'comparativa' => $this->obtenerComparativa($rangoActual, $rangoAnterior, $programaId, $tipoAlumno),
                'tendencias' => $this->obtenerTendencias($programaId, $tipoAlumno),
                'listado' => $this->obtenerListadoAlumnos($rangoActual, $programaId, $tipoAlumno, $page, $perPage)
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
     * Exportar reportes de matrícula en diferentes formatos
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
                'fechaFin' => 'nullable|date|required_if:rango,custom',
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
            $incluirGraficas = $request->get('incluirGraficas', false);

            // Obtener datos para exportar
            $datos = $this->reportesMatricula($request)->getData();

            // Registrar auditoría de exportación
            \Illuminate\Support\Facades\Log::info('Exportación de reportes de matrícula', [
                'user_id' => auth()->id(),
                'formato' => $formato,
                'detalle' => $detalle,
                'filtros' => [
                    'rango' => $request->get('rango', 'month'),
                    'programaId' => $request->get('programaId', 'all'),
                    'tipoAlumno' => $request->get('tipoAlumno', 'all')
                ]
            ]);

            $filename = 'reportes_matricula_' . Carbon::now()->format('Y-m-d_H-i-s');

            switch ($formato) {
                case 'pdf':
                    $pdf = Pdf::loadView('pdf.reportes-matricula', [
                        'datos' => $datos,
                        'detalle' => $detalle,
                        'incluirGraficas' => $incluirGraficas,
                        'fecha' => Carbon::now()->format('d/m/Y H:i')
                    ]);
                    
                    return $pdf->download($filename . '.pdf');

                case 'excel':
                    $export = new ReportesMatriculaExport($datos, $detalle);
                    return Excel::download($export, $filename . '.xlsx');

                case 'csv':
                    $export = new ReportesMatriculaExport($datos, $detalle);
                    return Excel::download($export, $filename . '.csv', \Maatwebsite\Excel\Excel::CSV);

                default:
                    return response()->json([
                        'error' => 'Formato no soportado'
                    ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al exportar reportes',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener filtros disponibles para el módulo de reportes
     */
    private function obtenerFiltrosDisponibles()
    {
        $programas = Programa::where('activo', 1)
            ->select('id', 'nombre_del_programa as nombre')
            ->orderBy('nombre_del_programa')
            ->get()
            ->map(function ($programa) {
                return [
                    'id' => (string) $programa->id,
                    'nombre' => $programa->nombre
                ];
            });

        return [
            'rangosDisponibles' => ['month', 'quarter', 'semester', 'year', 'custom'],
            'programas' => $programas,
            'tiposAlumno' => ['Nuevo', 'Recurrente']
        ];
    }

    /**
     * Calcular rango de fechas según el parámetro 'rango'
     */
    private function obtenerRangoFechas($rango, $fechaInicio = null, $fechaFin = null)
    {
        $ahora = Carbon::now();

        switch ($rango) {
            case 'custom':
                if (!$fechaInicio || !$fechaFin) {
                    throw new \Exception('fechaInicio y fechaFin son requeridos para rango custom');
                }
                return [
                    'fechaInicio' => Carbon::parse($fechaInicio)->startOfDay(),
                    'fechaFin' => Carbon::parse($fechaFin)->endOfDay(),
                    'descripcion' => Carbon::parse($fechaInicio)->format('d/m/Y') . ' - ' . Carbon::parse($fechaFin)->format('d/m/Y')
                ];

            case 'quarter':
                $inicio = $ahora->copy()->firstOfQuarter()->startOfDay();
                $fin = $ahora->copy()->lastOfQuarter()->endOfDay();
                return [
                    'fechaInicio' => $inicio,
                    'fechaFin' => $fin,
                    'descripcion' => 'Q' . $ahora->quarter . ' ' . $ahora->year
                ];

            case 'semester':
                if ($ahora->month <= 6) {
                    $inicio = $ahora->copy()->month(1)->startOfMonth()->startOfDay();
                    $fin = $ahora->copy()->month(6)->endOfMonth()->endOfDay();
                    $descripcion = 'Primer Semestre ' . $ahora->year;
                } else {
                    $inicio = $ahora->copy()->month(7)->startOfMonth()->startOfDay();
                    $fin = $ahora->copy()->month(12)->endOfMonth()->endOfDay();
                    $descripcion = 'Segundo Semestre ' . $ahora->year;
                }
                return [
                    'fechaInicio' => $inicio,
                    'fechaFin' => $fin,
                    'descripcion' => $descripcion
                ];

            case 'year':
                $inicio = $ahora->copy()->startOfYear()->startOfDay();
                $fin = $ahora->copy()->endOfYear()->endOfDay();
                return [
                    'fechaInicio' => $inicio,
                    'fechaFin' => $fin,
                    'descripcion' => 'Año ' . $ahora->year
                ];

            case 'month':
            default:
                $inicio = $ahora->copy()->startOfMonth()->startOfDay();
                $fin = $ahora->copy()->endOfMonth()->endOfDay();
                return [
                    'fechaInicio' => $inicio,
                    'fechaFin' => $fin,
                    'descripcion' => $ahora->locale('es')->isoFormat('MMMM YYYY')
                ];
        }
    }

    /**
     * Obtener rango anterior basado en el rango actual
     */
    private function obtenerRangoAnterior($rango, $rangoActual)
    {
        $fechaInicio = $rangoActual['fechaInicio'];
        $fechaFin = $rangoActual['fechaFin'];
        $diff = $fechaInicio->diffInDays($fechaFin) + 1;

        $inicioAnterior = $fechaInicio->copy()->subDays($diff);
        $finAnterior = $fechaInicio->copy()->subDay()->endOfDay();

        $descripcion = '';
        switch ($rango) {
            case 'month':
                $descripcion = $inicioAnterior->locale('es')->isoFormat('MMMM YYYY');
                break;
            case 'quarter':
                $descripcion = 'Q' . $inicioAnterior->quarter . ' ' . $inicioAnterior->year;
                break;
            case 'semester':
                $descripcion = ($inicioAnterior->month <= 6 ? 'Primer' : 'Segundo') . ' Semestre ' . $inicioAnterior->year;
                break;
            case 'year':
                $descripcion = 'Año ' . $inicioAnterior->year;
                break;
            default:
                $descripcion = $inicioAnterior->format('d/m/Y') . ' - ' . $finAnterior->format('d/m/Y');
        }

        return [
            'fechaInicio' => $inicioAnterior,
            'fechaFin' => $finAnterior,
            'descripcion' => $descripcion
        ];
    }

    /**
     * Obtener datos del período actual
     */
    private function obtenerDatosPeriodo($rango, $programaId, $tipoAlumno)
    {
        $fechaInicio = $rango['fechaInicio'];
        $fechaFin = $rango['fechaFin'];

        // Query base para matrículas
        $queryMatriculas = EstudiantePrograma::whereBetween('created_at', [$fechaInicio, $fechaFin]);
        
        if ($programaId !== 'all') {
            $queryMatriculas->where('programa_id', $programaId);
        }

        $matriculados = $queryMatriculas->count();

        // Alumnos nuevos vs recurrentes
        $alumnosNuevos = $this->contarAlumnosNuevos($fechaInicio, $fechaFin, $programaId);
        $alumnosRecurrentes = $matriculados - $alumnosNuevos;

        // Filtrar por tipo si es necesario
        if ($tipoAlumno === 'Nuevo') {
            $totalFiltrado = $alumnosNuevos;
        } elseif ($tipoAlumno === 'Recurrente') {
            $totalFiltrado = $alumnosRecurrentes;
        } else {
            $totalFiltrado = $matriculados;
        }

        return [
            'rango' => [
                'fechaInicio' => $fechaInicio->format('Y-m-d'),
                'fechaFin' => $fechaFin->format('Y-m-d'),
                'descripcion' => $rango['descripcion']
            ],
            'totales' => [
                'matriculados' => $totalFiltrado,
                'alumnosNuevos' => $alumnosNuevos,
                'alumnosRecurrentes' => $alumnosRecurrentes
            ],
            'distribucionProgramas' => $this->obtenerDistribucionProgramasRango($fechaInicio, $fechaFin, $tipoAlumno),
            'evolucionMensual' => $this->obtenerEvolucionMensualRango($fechaInicio, $fechaFin, $programaId, $tipoAlumno),
            'distribucionTipo' => [
                ['tipo' => 'Nuevo', 'total' => $alumnosNuevos],
                ['tipo' => 'Recurrente', 'total' => $alumnosRecurrentes]
            ]
        ];
    }

    /**
     * Obtener datos del período anterior (simplificado)
     */
    private function obtenerDatosPeriodoAnterior($rango, $programaId, $tipoAlumno)
    {
        $fechaInicio = $rango['fechaInicio'];
        $fechaFin = $rango['fechaFin'];

        $queryMatriculas = EstudiantePrograma::whereBetween('created_at', [$fechaInicio, $fechaFin]);
        
        if ($programaId !== 'all') {
            $queryMatriculas->where('programa_id', $programaId);
        }

        $matriculados = $queryMatriculas->count();
        $alumnosNuevos = $this->contarAlumnosNuevos($fechaInicio, $fechaFin, $programaId);
        $alumnosRecurrentes = $matriculados - $alumnosNuevos;

        if ($tipoAlumno === 'Nuevo') {
            $totalFiltrado = $alumnosNuevos;
        } elseif ($tipoAlumno === 'Recurrente') {
            $totalFiltrado = $alumnosRecurrentes;
        } else {
            $totalFiltrado = $matriculados;
        }

        return [
            'totales' => [
                'matriculados' => $totalFiltrado,
                'alumnosNuevos' => $alumnosNuevos,
                'alumnosRecurrentes' => $alumnosRecurrentes
            ],
            'rangoComparado' => [
                'fechaInicio' => $fechaInicio->format('Y-m-d'),
                'fechaFin' => $fechaFin->format('Y-m-d'),
                'descripcion' => $rango['descripcion']
            ]
        ];
    }

    /**
     * Calcular comparativa entre períodos
     */
    private function obtenerComparativa($rangoActual, $rangoAnterior, $programaId, $tipoAlumno)
    {
        $actual = $this->obtenerDatosPeriodo($rangoActual, $programaId, $tipoAlumno);
        $anterior = $this->obtenerDatosPeriodoAnterior($rangoAnterior, $programaId, $tipoAlumno);

        return [
            'totales' => [
                'actual' => $actual['totales']['matriculados'],
                'anterior' => $anterior['totales']['matriculados'],
                'variacion' => $this->calcularVariacion(
                    $anterior['totales']['matriculados'],
                    $actual['totales']['matriculados']
                )
            ],
            'nuevos' => [
                'actual' => $actual['totales']['alumnosNuevos'],
                'anterior' => $anterior['totales']['alumnosNuevos'],
                'variacion' => $this->calcularVariacion(
                    $anterior['totales']['alumnosNuevos'],
                    $actual['totales']['alumnosNuevos']
                )
            ],
            'recurrentes' => [
                'actual' => $actual['totales']['alumnosRecurrentes'],
                'anterior' => $anterior['totales']['alumnosRecurrentes'],
                'variacion' => $this->calcularVariacion(
                    $anterior['totales']['alumnosRecurrentes'],
                    $actual['totales']['alumnosRecurrentes']
                )
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
     * Contar alumnos nuevos (primera matrícula en el sistema)
     */
    private function contarAlumnosNuevos($fechaInicio, $fechaFin, $programaId = 'all')
    {
        // Un alumno es "nuevo" si su primera matrícula está dentro del rango
        $query = DB::table('estudiante_programa as ep1')
            ->join(DB::raw('(SELECT prospecto_id, MIN(created_at) as primera_matricula 
                            FROM estudiante_programa 
                            WHERE deleted_at IS NULL 
                            GROUP BY prospecto_id) as ep2'), 'ep1.prospecto_id', '=', 'ep2.prospecto_id')
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
    private function obtenerDistribucionProgramasRango($fechaInicio, $fechaFin, $tipoAlumno = 'all')
    {
        $query = DB::table('estudiante_programa as ep')
            ->join('tb_programas as p', 'ep.programa_id', '=', 'p.id')
            ->whereBetween('ep.created_at', [$fechaInicio, $fechaFin])
            ->whereNull('ep.deleted_at')
            ->select('p.nombre_del_programa as programa', DB::raw('COUNT(*) as total'))
            ->groupBy('p.nombre_del_programa')
            ->orderByDesc('total');

        // Filtrar por tipo de alumno si es necesario
        if ($tipoAlumno === 'Nuevo') {
            $query->whereIn('ep.prospecto_id', function ($q) use ($fechaInicio, $fechaFin) {
                $q->select('prospecto_id')
                    ->from('estudiante_programa')
                    ->whereNull('deleted_at')
                    ->groupBy('prospecto_id')
                    ->havingRaw('MIN(created_at) BETWEEN ? AND ?', [$fechaInicio, $fechaFin]);
            });
        }

        return $query->get()->toArray();
    }

    /**
     * Obtener evolución mensual dentro de un rango
     */
    private function obtenerEvolucionMensualRango($fechaInicio, $fechaFin, $programaId, $tipoAlumno)
    {
        $inicio = $fechaInicio->copy()->startOfMonth();
        $fin = $fechaFin->copy()->endOfMonth();
        
        $meses = [];
        $current = $inicio->copy();
        
        while ($current <= $fin) {
            $mesInicio = $current->copy()->startOfMonth();
            $mesFin = $current->copy()->endOfMonth();
            
            $query = EstudiantePrograma::whereBetween('created_at', [$mesInicio, $mesFin]);
            
            if ($programaId !== 'all') {
                $query->where('programa_id', $programaId);
            }

            $total = $query->count();

            $meses[] = [
                'mes' => $current->format('Y-m'),
                'total' => $total
            ];

            $current->addMonth();
        }

        return $meses;
    }

    /**
     * Obtener tendencias de los últimos 12 meses
     */
    private function obtenerTendencias($programaId, $tipoAlumno)
    {
        $ahora = Carbon::now();
        $hace12Meses = $ahora->copy()->subMonths(12)->startOfMonth();

        // Últimos 12 meses
        $ultimosDoceMeses = [];
        for ($i = 11; $i >= 0; $i--) {
            $mes = $ahora->copy()->subMonths($i);
            $inicio = $mes->copy()->startOfMonth();
            $fin = $mes->copy()->endOfMonth();

            $query = EstudiantePrograma::whereBetween('created_at', [$inicio, $fin]);
            
            if ($programaId !== 'all') {
                $query->where('programa_id', $programaId);
            }

            $ultimosDoceMeses[] = [
                'mes' => $mes->format('Y-m'),
                'total' => $query->count()
            ];
        }

        // Crecimiento por programa (comparando últimos 6 meses vs 6 anteriores)
        $crecimientoPorPrograma = $this->obtenerCrecimientoPorPrograma();

        // Proyección simple basada en promedio de últimos 3 meses
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
    private function obtenerCrecimientoPorPrograma()
    {
        $ahora = Carbon::now();
        
        // Últimos 6 meses
        $inicioReciente = $ahora->copy()->subMonths(6)->startOfMonth();
        $finReciente = $ahora->copy()->endOfMonth();
        
        // 6 meses anteriores
        $inicioAnterior = $ahora->copy()->subMonths(12)->startOfMonth();
        $finAnterior = $ahora->copy()->subMonths(7)->endOfMonth();

        $programas = Programa::where('activo', 1)->get();
        $crecimiento = [];

        foreach ($programas as $programa) {
            $totalReciente = EstudiantePrograma::where('programa_id', $programa->id)
                ->whereBetween('created_at', [$inicioReciente, $finReciente])
                ->count();

            $totalAnterior = EstudiantePrograma::where('programa_id', $programa->id)
                ->whereBetween('created_at', [$inicioAnterior, $finAnterior])
                ->count();

            $variacion = $this->calcularVariacion($totalAnterior, $totalReciente);

            $crecimiento[] = [
                'programa' => $programa->nombre_del_programa,
                'variacion' => $variacion
            ];
        }

        return $crecimiento;
    }

    /**
     * Calcular proyección simple
     */
    private function obtenerProyeccion($ultimosDoceMeses)
    {
        // Tomar últimos 3 meses y promediar
        $ultimos3 = array_slice($ultimosDoceMeses, -3);
        $promedio = collect($ultimos3)->avg('total');

        $proximoMes = Carbon::now()->addMonth();
        
        return [
            [
                'periodo' => $proximoMes->format('Y-m'),
                'totalEsperado' => (int) round($promedio)
            ]
        ];
    }

    /**
     * Obtener listado paginado de alumnos
     */
    private function obtenerListadoAlumnos($rango, $programaId, $tipoAlumno, $page, $perPage)
    {
        $fechaInicio = $rango['fechaInicio'];
        $fechaFin = $rango['fechaFin'];

        $query = EstudiantePrograma::with(['prospecto', 'programa'])
            ->whereBetween('estudiante_programa.created_at', [$fechaInicio, $fechaFin]);

        if ($programaId !== 'all') {
            $query->where('programa_id', $programaId);
        }

        // Filtrar por tipo de alumno
        if ($tipoAlumno === 'Nuevo') {
            $query->whereIn('prospecto_id', function ($q) use ($fechaInicio, $fechaFin) {
                $q->select('prospecto_id')
                    ->from('estudiante_programa')
                    ->whereNull('deleted_at')
                    ->groupBy('prospecto_id')
                    ->havingRaw('MIN(created_at) BETWEEN ? AND ?', [$fechaInicio, $fechaFin]);
            });
        } elseif ($tipoAlumno === 'Recurrente') {
            $query->whereNotIn('prospecto_id', function ($q) use ($fechaInicio, $fechaFin) {
                $q->select('prospecto_id')
                    ->from('estudiante_programa')
                    ->whereNull('deleted_at')
                    ->groupBy('prospecto_id')
                    ->havingRaw('MIN(created_at) BETWEEN ? AND ?', [$fechaInicio, $fechaFin]);
            });
        }

        $total = $query->count();
        $alumnos = $query->orderBy('estudiante_programa.created_at', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(function ($ep) use ($fechaInicio, $fechaFin) {
                // Determinar si es nuevo o recurrente
                $primeraMatricula = EstudiantePrograma::where('prospecto_id', $ep->prospecto_id)
                    ->whereNull('deleted_at')
                    ->min('created_at');
                
                $esNuevo = Carbon::parse($primeraMatricula)->between($fechaInicio, $fechaFin);

                return [
                    'id' => $ep->id,
                    'nombre' => $ep->prospecto->nombre_completo ?? 'N/A',
                    'fechaMatricula' => $ep->created_at->format('Y-m-d'),
                    'tipo' => $esNuevo ? 'Nuevo' : 'Recurrente',
                    'programa' => $ep->programa->nombre_del_programa ?? 'N/A',
                    'estado' => 'Activo' // Puedes agregar lógica más compleja aquí
                ];
            });

        return [
            'alumnos' => $alumnos,
            'paginacion' => [
                'pagina' => $page,
                'porPagina' => $perPage,
                'total' => $total,
                'totalPaginas' => (int) ceil($total / $perPage)
            ]
        ];
    }
}

