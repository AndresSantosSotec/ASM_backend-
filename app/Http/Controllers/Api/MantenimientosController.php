<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\KardexPago;
use App\Models\ReconciliationRecord;
use App\Models\CuotaProgramaEstudiante;
use App\Models\EstudiantePrograma;
use App\Models\Prospecto;
use App\Models\PaymentPlan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Programa;
use App\Models\Convenio;
use App\Models\User;
use App\Models\ProspectoAdicional;

class MantenimientosController extends Controller
{
    /**
     * Resumen general del dashboard de Kardex y conciliaciones.
     */
    public function kardexDashboard(Request $request)
    {
        try {
            $filters = $this->extractCommonFilters($request);

            $kardexBase = $this->buildKardexBaseQuery($filters);
            $reconciliationBase = $this->buildReconciliationBaseQuery($filters);
            $cuotasBase = $this->buildCuotasBaseQuery($filters);

            $now = Carbon::now();

            if (config('app.debug')) {
                DB::enableQueryLog();
            }

            $kardexSummary = [
                'movimientos_registrados' => (clone $kardexBase)->count(),
                'monto_neto' => (float) (clone $kardexBase)->sum('monto_pagado'),
                'aplicados' => (clone $kardexBase)->where('estado_pago', 'aprobado')->count(),
                'pendientes' => (clone $kardexBase)->where('estado_pago', 'pendiente_revision')->count(),
                'rechazados' => (clone $kardexBase)->where('estado_pago', 'rechazado')->count(),
            ];

            $reconciliationSummary = [
                'total' => (clone $reconciliationBase)->count(),
                'monto_total' => (float) (clone $reconciliationBase)->sum('amount'),
                'conciliados' => (clone $reconciliationBase)->where('status', 'conciliado')->count(),
                'rechazados' => (clone $reconciliationBase)->where('status', 'rechazado')->count(),
                'pendientes' => (clone $reconciliationBase)
                    ->where(function ($q) {
                        $q->whereNull('status')
                            ->orWhereIn('status', ['imported', 'sin_coincidencia']);
                    })
                    ->count(),
            ];

            $cuotasPendientesBase = (clone $cuotasBase)->where(function ($q) {
                $q->whereNull('estado')->orWhere('estado', '!=', 'pagado');
            });

            $cuotasSummary = [
                'total' => (clone $cuotasBase)->count(),
                'pendientes' => (clone $cuotasPendientesBase)->count(),
                'en_mora' => (clone $cuotasPendientesBase)->whereDate('fecha_vencimiento', '<', $now->toDateString())->count(),
                'monto_pendiente' => (float) (clone $cuotasPendientesBase)->sum('monto'),
            ];

            if (config('app.debug')) {
                Log::info('Queries ejecutadas en kardexDashboard', ['queries' => DB::getQueryLog()]);
            }

            return response()->json([
                'timestamp' => $now->toIso8601String(),
                'filters' => $filters,
                'kardex' => $kardexSummary,
                'reconciliaciones' => $reconciliationSummary,
                'cuotas' => $cuotasSummary,
            ]);
        } catch (\Throwable $th) {
            Log::error('Error en kardexDashboard', [
                'message' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
                'trace' => $th->getTraceAsString(),
                'filters' => $filters ?? null,
            ]);

            return $this->errorResponse('Error al generar el dashboard de Kardex', $th);
        }
    }

    /**
     * Datos tabulares para Kardex, conciliaciones y cuotas.
     */
    public function kardexData(Request $request)
    {
        try {
            $filters = $this->extractCommonFilters($request);
            $limit = $this->resolveLimit($request);

            if (config('app.debug')) {
                DB::enableQueryLog();
            }

            $kardexQuery = $this->buildKardexBaseQuery($filters)
                ->with([
                    'estudiantePrograma:id,prospecto_id,programa_id',
                    'estudiantePrograma.prospecto:id,nombre_completo,carnet,correo_electronico,activo',
                    'estudiantePrograma.programa:id,nombre_del_programa as nombre',
                ]);

            if (method_exists(KardexPago::class, 'cuota')) {
                $kardexQuery->with(['cuota:id,estudiante_programa_id,numero_cuota,fecha_vencimiento,monto,estado,paid_at']);
            }

            if (method_exists(KardexPago::class, 'reconciliationRecords')) {
                $kardexQuery->with(['reconciliationRecords:id,kardex_pago_id,bank,reference,amount,date,status']);
            }

            $kardex = $kardexQuery
                ->orderByDesc('fecha_pago')
                ->limit($limit)
                ->get()
                ->map(function (KardexPago $pago) {
                    $estudiantePrograma = $pago->estudiantePrograma;
                    $prospecto = optional($estudiantePrograma)->prospecto;
                    $programa = optional($estudiantePrograma)->programa;

                    $cuota = method_exists($pago, 'cuota') ? $pago->cuota : null;
                    $reconciliaciones = method_exists($pago, 'reconciliationRecords')
                        ? $pago->reconciliationRecords
                        : collect([]);

                    return [
                        'id' => $pago->id,
                        'fecha_pago' => optional($pago->fecha_pago)->format('Y-m-d'),
                        'fecha_recibo' => optional($pago->fecha_recibo)->format('Y-m-d'),
                        'monto_pagado' => (float) $pago->monto_pagado,
                        'metodo_pago' => $pago->metodo_pago,
                        'estado_pago' => $pago->estado_pago,
                        'numero_boleta' => $pago->numero_boleta,
                        'banco' => $pago->banco,
                        'observaciones' => $pago->observaciones,
                        'prospecto' => $prospecto ? [
                            'id' => $prospecto->id,
                            'nombre' => $prospecto->nombre_completo,
                            'carnet' => $prospecto->carnet,
                            'correo' => $prospecto->correo_electronico,
                        ] : null,
                        'programa' => $programa ? [
                            'id' => $programa->id,
                            'nombre' => $programa->nombre,
                        ] : null,
                        'cuota' => $cuota ? [
                            'id' => $cuota->id,
                            'numero_cuota' => $cuota->numero_cuota,
                            'fecha_vencimiento' => optional($cuota->fecha_vencimiento)->format('Y-m-d'),
                            'monto' => (float) $cuota->monto,
                            'estado' => $cuota->estado,
                            'paid_at' => optional($cuota->paid_at)->format('Y-m-d H:i:s'),
                        ] : null,
                        'reconciliaciones' => $reconciliaciones->map(function ($record) {
                            return [
                                'id' => $record->id,
                                'bank' => $record->bank,
                                'reference' => $record->reference,
                                'amount' => (float) $record->amount,
                                'date' => optional($record->date)->format('Y-m-d'),
                                'status' => $record->status,
                            ];
                        })->values(),
                    ];
                });

            $reconciliaciones = $this->buildReconciliationBaseQuery($filters)
                ->with([
                    'prospecto:id,nombre_completo,carnet,correo_electronico,activo',
                    'kardexPago:id,estudiante_programa_id,monto_pagado,fecha_pago,estado_pago',
                    'kardexPago.estudiantePrograma:id,prospecto_id,programa_id',
                    'kardexPago.estudiantePrograma.programa:id,nombre_del_programa as nombre',
                ])
                ->orderByDesc('date')
                ->limit($limit)
                ->get()
                ->map(function (ReconciliationRecord $record) {
                    $prospecto = $record->prospecto;
                    $kardex = $record->kardexPago;
                    $programa = optional(optional($kardex)->estudiantePrograma)->programa;

                    return [
                        'id' => $record->id,
                        'bank' => $record->bank,
                        'reference' => $record->reference,
                        'amount' => (float) $record->amount,
                        'date' => optional($record->date)->format('Y-m-d'),
                        'status' => $record->status,
                        'prospecto' => $prospecto ? [
                            'id' => $prospecto->id,
                            'nombre' => $prospecto->nombre_completo,
                            'carnet' => $prospecto->carnet,
                            'correo' => $prospecto->correo_electronico,
                        ] : null,
                        'kardex' => $kardex ? [
                            'id' => $kardex->id,
                            'fecha_pago' => optional($kardex->fecha_pago)->format('Y-m-d'),
                            'monto_pagado' => (float) $kardex->monto_pagado,
                            'estado_pago' => $kardex->estado_pago,
                        ] : null,
                        'programa' => $programa ? [
                            'id' => $programa->id,
                            'nombre' => $programa->nombre,
                        ] : null,
                    ];
                });

            $cuotas = $this->buildCuotasBaseQuery($filters)
                ->with([
                    'estudiantePrograma:id,prospecto_id,programa_id',
                    'estudiantePrograma.prospecto:id,nombre_completo,carnet,correo_electronico,activo',
                    'estudiantePrograma.programa:id,nombre_del_programa as nombre',
                ])
                ->orderBy('fecha_vencimiento')
                ->limit($limit)
                ->get()
                ->map(function (CuotaProgramaEstudiante $cuota) {
                    $estudiantePrograma = $cuota->estudiantePrograma;
                    $prospecto = optional($estudiantePrograma)->prospecto;
                    $programa = optional($estudiantePrograma)->programa;

                    return [
                        'id' => $cuota->id,
                        'numero_cuota' => $cuota->numero_cuota,
                        'fecha_vencimiento' => optional($cuota->fecha_vencimiento)->format('Y-m-d'),
                        'monto' => (float) $cuota->monto,
                        'estado' => $cuota->estado,
                        'paid_at' => optional($cuota->paid_at)->format('Y-m-d H:i:s'),
                        'prospecto' => $prospecto ? [
                            'id' => $prospecto->id,
                            'nombre' => $prospecto->nombre_completo,
                            'carnet' => $prospecto->carnet,
                            'correo' => $prospecto->correo_electronico,
                        ] : null,
                        'programa' => $programa ? [
                            'id' => $programa->id,
                            'nombre' => $programa->nombre,
                        ] : null,
                    ];
                });

            if (config('app.debug')) {
                Log::info('Queries ejecutadas en kardexData', ['queries' => DB::getQueryLog()]);
            }

            return response()->json([
                'timestamp' => Carbon::now()->toIso8601String(),
                'filters' => array_merge($filters, ['limit' => $limit]),
                'kardex' => $kardex,
                'reconciliaciones' => $reconciliaciones,
                'cuotas' => $cuotas,
            ]);
        } catch (\Throwable $th) {
            Log::error('Error en kardexData', [
                'message' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
                'trace' => $th->getTraceAsString(),
                'filters' => $filters ?? null,
            ]);

            return $this->errorResponse('Error al obtener los datos de Kardex', $th);
        }
    }

    /**
     * Dashboard para cuotas por estudiante (restaurado y funcional).
     */
    public function cuotasDashboard(Request $request)
    {
        try {
            $filters = $this->extractCommonFilters($request);
            $limit = $this->resolveLimit($request);
            $now = Carbon::now();

            $cuotasBase = $this->buildCuotasBaseQuery($filters);

            $estudiantesActivos = EstudiantePrograma::query()
                ->whereHas('prospecto', fn($q) => $q->where('activo', true))
                ->when($filters['programa_id'] ?? null, fn($q, $id) => $q->where('programa_id', $id))
                ->when($filters['prospecto_id'] ?? null, fn($q, $id) => $q->where('prospecto_id', $id))
                ->count();

            $saldoPendiente = (float) (clone $cuotasBase)
                ->where(fn($q) => $q->whereNull('estado')->orWhere('estado', '!=', 'pagado'))
                ->sum('monto');

            $cuotasEnMora = (clone $cuotasBase)
                ->where(fn($q) => $q->whereNull('estado')->orWhere('estado', '!=', 'pagado'))
                ->whereDate('fecha_vencimiento', '<', $now->toDateString())
                ->count();

            $planesReestructurados = PaymentPlan::query()
                ->when($filters['prospecto_id'] ?? null, fn($q, $id) => $q->where('prospecto_id', $id))
                ->whereIn('status', ['restructurado', 'reestructurado', 'restructured'])
                ->count();

            $cuotas = $this->buildCuotasBaseQuery($filters)
                ->with([
                    'estudiantePrograma:id,prospecto_id,programa_id',
                    'estudiantePrograma.prospecto:id,nombre_completo,carnet,correo_electronico,telefono,activo',
                    'estudiantePrograma.programa:id,nombre_del_programa as nombre',
                ])
                ->orderBy('fecha_vencimiento')
                ->get();

            $estudiantes = $cuotas->groupBy('estudiante_programa_id')->values()
                ->map(function ($grupo) use ($limit) {
                    $cuotasEst = collect($grupo)->sortBy('fecha_vencimiento');
                    $primera = $cuotasEst->first();
                    $ep = $primera?->estudiantePrograma;
                    $prospecto = optional($ep)->prospecto;
                    $programa = optional($ep)->programa;
                    $pendientes = $cuotasEst->where('estado', '!=', 'pagado');
                    $proxima = $pendientes->first();

                    return [
                        'estudiante_programa_id' => $ep?->id,
                        'prospecto' => $prospecto ? [
                            'id' => $prospecto->id,
                            'nombre' => $prospecto->nombre_completo,
                            'carnet' => $prospecto->carnet,
                            'correo' => $prospecto->correo_electronico,
                            'telefono' => $prospecto->telefono,
                        ] : null,
                        'programa' => $programa ? [
                            'id' => $programa->id,
                            'nombre' => $programa->nombre,
                        ] : null,
                        'saldo_pendiente' => (float) $pendientes->sum('monto'),
                        'cuotas_pendientes' => $pendientes->count(),
                        'cuotas_pagadas' => $cuotasEst->count() - $pendientes->count(),
                        'proxima_cuota' => $proxima ? [
                            'id' => $proxima->id,
                            'numero_cuota' => $proxima->numero_cuota,
                            'fecha_vencimiento' => optional($proxima->fecha_vencimiento)->format('Y-m-d'),
                            'monto' => (float) $proxima->monto,
                            'estado' => $proxima->estado,
                        ] : null,
                        'cuotas' => $cuotasEst->take($limit)->map(fn($c) => [
                            'id' => $c->id,
                            'numero_cuota' => $c->numero_cuota,
                            'fecha_vencimiento' => optional($c->fecha_vencimiento)->format('Y-m-d'),
                            'monto' => (float) $c->monto,
                            'estado' => $c->estado,
                            'paid_at' => optional($c->paid_at)->format('Y-m-d H:i:s'),
                        ])->values(),
                    ];
                })->take($limit);

            return response()->json([
                'timestamp' => $now->toIso8601String(),
                'filters' => array_merge($filters, ['limit' => $limit]),
                'summary' => [
                    'estudiantes_activos' => $estudiantesActivos,
                    'saldo_estimado' => $saldoPendiente,
                    'en_mora' => $cuotasEnMora,
                    'planes_reestructurados' => $planesReestructurados,
                ],
                'estudiantes' => $estudiantes,
            ]);
        } catch (\Throwable $th) {
            return $this->errorResponse('Error al generar el dashboard de cuotas', $th);
        }
    }

    /**
     * Funciones base (queries y helpers)
     */
    private function buildKardexBaseQuery(array $filters)
    {
        $query = KardexPago::query();

        if (method_exists(KardexPago::class, 'estudiantePrograma')) {
            $query->whereHas('estudiantePrograma.prospecto', function ($q) use ($filters) {
                $q->where('activo', true);

                // Filtro por prospecto_id
                if (!empty($filters['prospecto_id'])) {
                    $q->where('id', $filters['prospecto_id']);
                }

                // Búsqueda general
                if (!empty($filters['search'])) {
                    $like = '%' . $filters['search'] . '%';
                    $q->where(fn($sq) => $sq
                        ->where('nombre_completo', 'like', $like)
                        ->orWhere('carnet', 'like', $like)
                        ->orWhere('correo_electronico', 'like', $like));
                }
            });

            // Filtro por programa_id
            if (!empty($filters['programa_id'])) {
                $query->whereHas('estudiantePrograma', function ($q) use ($filters) {
                    $q->where('programa_id', $filters['programa_id']);
                });
            }
        }

        // Filtro por rango de fechas
        if (!empty($filters['fecha_inicio'])) {
            $query->whereDate('fecha_pago', '>=', $filters['fecha_inicio']);
        }
        if (!empty($filters['fecha_fin'])) {
            $query->whereDate('fecha_pago', '<=', $filters['fecha_fin']);
        }

        return $query;
    }

    private function buildReconciliationBaseQuery(array $filters)
    {
        $query = ReconciliationRecord::query();

        // Filtro por prospecto_id
        if (!empty($filters['prospecto_id'])) {
            $query->where('prospecto_id', $filters['prospecto_id']);
        }

        // Filtro por rango de fechas
        if (!empty($filters['fecha_inicio'])) {
            $query->whereDate('date', '>=', $filters['fecha_inicio']);
        }
        if (!empty($filters['fecha_fin'])) {
            $query->whereDate('date', '<=', $filters['fecha_fin']);
        }

        // Búsqueda general (referencia, banco)
        if (!empty($filters['search'])) {
            $like = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($like) {
                $q->where('reference', 'like', $like)
                  ->orWhere('bank', 'like', $like);
            });
        }

        return $query;
    }

    private function buildCuotasBaseQuery(array $filters)
    {
        $query = CuotaProgramaEstudiante::query();

        // Filtro por prospecto_id
        if (!empty($filters['prospecto_id'])) {
            $query->whereHas('estudiantePrograma', function ($q) use ($filters) {
                $q->where('prospecto_id', $filters['prospecto_id']);
            });
        }

        // Filtro por programa_id
        if (!empty($filters['programa_id'])) {
            $query->where('estudiante_programa_id', function ($subquery) use ($filters) {
                $subquery->select('id')
                    ->from('estudiante_programas')
                    ->where('programa_id', $filters['programa_id']);
            });
        }

        // Búsqueda general (por prospecto)
        if (!empty($filters['search'])) {
            $like = '%' . $filters['search'] . '%';
            $query->whereHas('estudiantePrograma.prospecto', function ($q) use ($like) {
                $q->where('nombre_completo', 'like', $like)
                  ->orWhere('carnet', 'like', $like)
                  ->orWhere('correo_electronico', 'like', $like);
            });
        }

        // Filtro por rango de fechas de vencimiento
        if (!empty($filters['fecha_inicio'])) {
            $query->whereDate('fecha_vencimiento', '>=', $filters['fecha_inicio']);
        }
        if (!empty($filters['fecha_fin'])) {
            $query->whereDate('fecha_vencimiento', '<=', $filters['fecha_fin']);
        }

        return $query;
    }

    private function extractCommonFilters(Request $request): array
    {
        return [
            'prospecto_id' => $request->input('prospecto_id'),
            'programa_id' => $request->input('programa_id'),
            'search' => trim((string)$request->input('search')) ?: null,
            'fecha_inicio' => $this->parseDate($request->input('fecha_inicio')),
            'fecha_fin' => $this->parseDate($request->input('fecha_fin')),
        ];
    }

    private function parseDate($value): ?Carbon
    {
        if (empty($value)) return null;
        try {
            return Carbon::parse($value);
        } catch (\Throwable $th) {
            return null;
        }
    }

    private function resolveLimit(Request $request, int $default = 100, string $key = 'limit'): int
    {
        $limit = (int)$request->input($key, $default);
        return max(1, min($limit, 500));
    }

    private function errorResponse(string $message, \Throwable $th)
    {
        return response()->json([
            'message' => $message,
            'error' => config('app.debug') ? $th->getMessage() : 'Error interno del servidor',
            'trace' => config('app.debug') ? $th->getTraceAsString() : null,
        ], 500);
    }

    //obtener Estudaiantes

    public function estudiantesActivos(Request $request)
    {
        try {
            $limit = $this->resolveLimit($request, 200);

            $estudianteTable = (new EstudiantePrograma)->getTable();
            $prospectoTable  = (new Prospecto)->getTable();
            $addTable        = (new ProspectoAdicional)->getTable();

            $query = EstudiantePrograma::query()
                ->select([
                    "{$estudianteTable}.id as estudiante_programa_id",
                    "{$estudianteTable}.prospecto_id",
                    "{$prospectoTable}.nombre_completo",
                    "{$prospectoTable}.carnet",
                    "{$prospectoTable}.correo_electronico",
                    "{$addTable}.notas_pago",
                    "{$addTable}.nomenclatura",
                    "{$addTable}.status_actual",
                ])
                ->join($prospectoTable, "{$estudianteTable}.prospecto_id", '=', "{$prospectoTable}.id")
                ->leftJoin($addTable, "{$addTable}.id_estudiante_programa", '=', "{$estudianteTable}.id")
                ->where("{$prospectoTable}.activo", true);

            if ($request->filled('q')) {
                $q = $request->input('q');
                $query->where(function ($qb) use ($q, $prospectoTable) {
                    $qb->where("{$prospectoTable}.nombre_completo", 'like', "%{$q}%")
                        ->orWhere("{$prospectoTable}.carnet", 'like', "%{$q}%")
                        ->orWhere("{$prospectoTable}.correo_electronico", 'like', "%{$q}%");
                });
            }

            $rows = $query->limit($limit)->get();

            $data = $rows->map(function ($r) {
                return [
                    'estudiante_programa_id' => $r->estudiante_programa_id,
                    'prospecto_id'           => $r->prospecto_id,
                    'nombre_completo'        => $r->nombre_completo,
                    'carnet'                 => $r->carnet,
                    'correo_electronico'     => $r->correo_electronico,
                    'notas_pago'             => $r->notas_pago,
                    'nomenclatura'           => $r->nomenclatura,
                    'status_actual'          => $r->status_actual,
                ];
            });

            return response()->json([
                'timestamp' => Carbon::now()->toIso8601String(),
                'total_estudiantes_activos' => $data->count(),
                'estudiantes' => $data,
            ]);
        } catch (\Throwable $th) {
            return $this->errorResponse('Error al obtener los estudiantes activos', $th);
        }
    }

    // ==================== CRUD DE CUOTAS ====================

    /**
     * Listar cuotas con filtros (formato DataTable).
     * Filtros: carnet, prospecto_id, estudiante_programa_id, estado, fecha_vencimiento.
     */
    public function cuotasIndex(Request $request)
    {
        try {
            $limit = $this->resolveLimit($request, 100);
            $page = max(1, (int) $request->input('page', 1));
            $offset = ($page - 1) * $limit;

            $query = CuotaProgramaEstudiante::query()
                ->with([
                    'estudiantePrograma:id,prospecto_id,programa_id',
                    'estudiantePrograma.prospecto:id,nombre_completo,carnet,correo_electronico,telefono',
                    'estudiantePrograma.programa:id,nombre_del_programa',
                ]);

            // Filtrar por carnet (búsqueda en prospecto)
            if ($request->filled('carnet')) {
                $carnet = $request->input('carnet');
                $query->whereHas('estudiantePrograma.prospecto', function ($q) use ($carnet) {
                    $q->where('carnet', 'like', "%{$carnet}%");
                });
            }

            // Filtrar por prospecto_id
            if ($request->filled('prospecto_id')) {
                $prospectoId = $request->input('prospecto_id');
                $query->whereHas('estudiantePrograma', function ($q) use ($prospectoId) {
                    $q->where('prospecto_id', $prospectoId);
                });
            }

            // Filtrar por estudiante_programa_id
            if ($request->filled('estudiante_programa_id')) {
                $query->where('estudiante_programa_id', $request->input('estudiante_programa_id'));
            }

            // Filtrar por estado
            if ($request->filled('estado')) {
                $query->where('estado', $request->input('estado'));
            }

            // Filtrar por rango de fecha de vencimiento
            if ($request->filled('fecha_vencimiento_desde')) {
                $query->whereDate('fecha_vencimiento', '>=', $request->input('fecha_vencimiento_desde'));
            }
            if ($request->filled('fecha_vencimiento_hasta')) {
                $query->whereDate('fecha_vencimiento', '<=', $request->input('fecha_vencimiento_hasta'));
            }

            // Búsqueda general (nombre o carnet)
            if ($request->filled('q')) {
                $q = $request->input('q');
                $query->whereHas('estudiantePrograma.prospecto', function ($qb) use ($q) {
                    $qb->where('nombre_completo', 'like', "%{$q}%")
                       ->orWhere('carnet', 'like', "%{$q}%");
                });
            }

            $total = $query->count();

            $cuotas = $query
                ->orderBy('fecha_vencimiento', 'desc')
                ->offset($offset)
                ->limit($limit)
                ->get()
                ->map(function ($cuota) {
                    $ep = $cuota->estudiantePrograma;
                    $prospecto = optional($ep)->prospecto;
                    $programa = optional($ep)->programa;

                    // Calcular total pagado de esta cuota
                    $totalPagado = KardexPago::where('cuota_id', $cuota->id)
                        ->where('estado_pago', 'aprobado')
                        ->sum('monto_pagado');

                    return [
                        'id' => $cuota->id,
                        'estudiante_programa_id' => $cuota->estudiante_programa_id,
                        'numero_cuota' => $cuota->numero_cuota,
                        'fecha_vencimiento' => optional($cuota->fecha_vencimiento)->format('Y-m-d'),
                        'monto' => (float) $cuota->monto,
                        'estado' => $cuota->estado,
                        'paid_at' => optional($cuota->paid_at)->format('Y-m-d H:i:s'),
                        'total_pagado' => (float) $totalPagado,
                        'saldo_pendiente' => (float) ($cuota->monto - $totalPagado),
                        'prospecto' => $prospecto ? [
                            'id' => $prospecto->id,
                            'nombre_completo' => $prospecto->nombre_completo,
                            'carnet' => $prospecto->carnet,
                            'correo_electronico' => $prospecto->correo_electronico,
                            'telefono' => $prospecto->telefono,
                        ] : null,
                        'programa' => $programa ? [
                            'id' => $programa->id,
                            'nombre' => $programa->nombre_del_programa,
                        ] : null,
                        'created_at' => optional($cuota->created_at)->format('Y-m-d H:i:s'),
                        'updated_at' => optional($cuota->updated_at)->format('Y-m-d H:i:s'),
                    ];
                });

            return response()->json([
                'timestamp' => Carbon::now()->toIso8601String(),
                'pagination' => [
                    'total' => $total,
                    'per_page' => $limit,
                    'current_page' => $page,
                    'last_page' => ceil($total / $limit),
                ],
                'cuotas' => $cuotas,
            ]);
        } catch (\Throwable $th) {
            return $this->errorResponse('Error al listar las cuotas', $th);
        }
    }

    /**
     * Ver detalle de una cuota específica con pagos y reconciliaciones.
     */
    public function cuotasShow($id)
    {
        try {
            $cuota = CuotaProgramaEstudiante::with([
                'estudiantePrograma:id,prospecto_id,programa_id',
                'estudiantePrograma.prospecto:id,nombre_completo,carnet,correo_electronico,telefono',
                'estudiantePrograma.programa:id,nombre_del_programa',
            ])->findOrFail($id);

            $ep = $cuota->estudiantePrograma;
            $prospecto = optional($ep)->prospecto;
            $programa = optional($ep)->programa;

            // Pagos aplicados a esta cuota
            $pagos = KardexPago::where('cuota_id', $id)
                ->orderBy('fecha_pago', 'desc')
                ->get()
                ->map(function ($pago) {
                    return [
                        'id' => $pago->id,
                        'fecha_pago' => optional($pago->fecha_pago)->format('Y-m-d'),
                        'monto_pagado' => (float) $pago->monto_pagado,
                        'metodo_pago' => $pago->metodo_pago,
                        'estado_pago' => $pago->estado_pago,
                        'numero_boleta' => $pago->numero_boleta,
                        'banco' => $pago->banco,
                        'observaciones' => $pago->observaciones,
                    ];
                });

            $totalPagado = $pagos->where('estado_pago', 'aprobado')->sum('monto_pagado');

            return response()->json([
                'timestamp' => Carbon::now()->toIso8601String(),
                'cuota' => [
                    'id' => $cuota->id,
                    'estudiante_programa_id' => $cuota->estudiante_programa_id,
                    'numero_cuota' => $cuota->numero_cuota,
                    'fecha_vencimiento' => optional($cuota->fecha_vencimiento)->format('Y-m-d'),
                    'monto' => (float) $cuota->monto,
                    'estado' => $cuota->estado,
                    'paid_at' => optional($cuota->paid_at)->format('Y-m-d H:i:s'),
                    'total_pagado' => (float) $totalPagado,
                    'saldo_pendiente' => (float) ($cuota->monto - $totalPagado),
                    'prospecto' => $prospecto ? [
                        'id' => $prospecto->id,
                        'nombre_completo' => $prospecto->nombre_completo,
                        'carnet' => $prospecto->carnet,
                        'correo_electronico' => $prospecto->correo_electronico,
                        'telefono' => $prospecto->telefono,
                    ] : null,
                    'programa' => $programa ? [
                        'id' => $programa->id,
                        'nombre' => $programa->nombre_del_programa,
                    ] : null,
                    'created_at' => optional($cuota->created_at)->format('Y-m-d H:i:s'),
                    'updated_at' => optional($cuota->updated_at)->format('Y-m-d H:i:s'),
                ],
                'pagos' => $pagos,
            ]);
        } catch (\Throwable $th) {
            return $this->errorResponse('Error al obtener el detalle de la cuota', $th);
        }
    }

    /**
     * Crear una nueva cuota.
     * Payload esperado:
     * {
     *   "estudiante_programa_id": 123,
     *   "numero_cuota": 1,
     *   "fecha_vencimiento": "2025-12-31",
     *   "monto": 500.00,
     *   "estado": "pendiente"
     * }
     */
    public function cuotasStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'estudiante_programa_id' => 'required|exists:estudiante_programas,id',
                'numero_cuota' => 'required|integer|min:0',
                'fecha_vencimiento' => 'required|date',
                'monto' => 'required|numeric|min:0',
                'estado' => 'nullable|string|in:pendiente,pagado,vencido,cancelado',
            ]);

            $cuota = CuotaProgramaEstudiante::create([
                'estudiante_programa_id' => $validated['estudiante_programa_id'],
                'numero_cuota' => $validated['numero_cuota'],
                'fecha_vencimiento' => $validated['fecha_vencimiento'],
                'monto' => $validated['monto'],
                'estado' => $validated['estado'] ?? 'pendiente',
                'created_by' => auth()->id(),
            ]);

            // Cargar relaciones
            $cuota->load([
                'estudiantePrograma.prospecto:id,nombre_completo,carnet',
                'estudiantePrograma.programa:id,nombre_del_programa',
            ]);

            return response()->json([
                'message' => 'Cuota creada exitosamente',
                'cuota' => [
                    'id' => $cuota->id,
                    'estudiante_programa_id' => $cuota->estudiante_programa_id,
                    'numero_cuota' => $cuota->numero_cuota,
                    'fecha_vencimiento' => optional($cuota->fecha_vencimiento)->format('Y-m-d'),
                    'monto' => (float) $cuota->monto,
                    'estado' => $cuota->estado,
                    'prospecto' => optional($cuota->estudiantePrograma->prospecto)->nombre_completo,
                    'programa' => optional($cuota->estudiantePrograma->programa)->nombre_del_programa,
                ],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            return $this->errorResponse('Error al crear la cuota', $th);
        }
    }

    /**
     * Actualizar una cuota existente.
     */
    public function cuotasUpdate(Request $request, $id)
    {
        try {
            $cuota = CuotaProgramaEstudiante::findOrFail($id);

            $validated = $request->validate([
                'numero_cuota' => 'sometimes|integer|min:0',
                'fecha_vencimiento' => 'sometimes|date',
                'monto' => 'sometimes|numeric|min:0',
                'estado' => 'sometimes|string|in:pendiente,pagado,vencido,cancelado',
                'paid_at' => 'nullable|date',
            ]);

            $cuota->fill($validated);
            $cuota->updated_by = auth()->id();
            $cuota->save();

            $cuota->load([
                'estudiantePrograma.prospecto:id,nombre_completo,carnet',
                'estudiantePrograma.programa:id,nombre_del_programa',
            ]);

            return response()->json([
                'message' => 'Cuota actualizada exitosamente',
                'cuota' => [
                    'id' => $cuota->id,
                    'estudiante_programa_id' => $cuota->estudiante_programa_id,
                    'numero_cuota' => $cuota->numero_cuota,
                    'fecha_vencimiento' => optional($cuota->fecha_vencimiento)->format('Y-m-d'),
                    'monto' => (float) $cuota->monto,
                    'estado' => $cuota->estado,
                    'paid_at' => optional($cuota->paid_at)->format('Y-m-d H:i:s'),
                    'prospecto' => optional($cuota->estudiantePrograma->prospecto)->nombre_completo,
                    'programa' => optional($cuota->estudiantePrograma->programa)->nombre_del_programa,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            return $this->errorResponse('Error al actualizar la cuota', $th);
        }
    }

    /**
     * Eliminar una cuota.
     * Solo se permite eliminar si no tiene pagos aplicados (kardex).
     */
    public function cuotasDestroy($id)
    {
        try {
            $cuota = CuotaProgramaEstudiante::findOrFail($id);

            // Verificar si tiene pagos aplicados
            $tienePagos = KardexPago::where('cuota_id', $id)->exists();

            if ($tienePagos) {
                return response()->json([
                    'message' => 'No se puede eliminar la cuota porque tiene pagos aplicados',
                ], 400);
            }

            $cuota->deleted_by = auth()->id();
            $cuota->save();
            $cuota->delete();

            return response()->json([
                'message' => 'Cuota eliminada exitosamente',
            ]);
        } catch (\Throwable $th) {
            return $this->errorResponse('Error al eliminar la cuota', $th);
        }
    }

    /**
     * Listar cuotas de un estudiante específico (por estudiante_programa_id o prospecto_id).
     */
    public function cuotasPorEstudiante(Request $request)
    {
        try {
            $estudianteProgramaId = $request->input('estudiante_programa_id');
            $prospectoId = $request->input('prospecto_id');

            if (!$estudianteProgramaId && !$prospectoId) {
                return response()->json([
                    'message' => 'Debe proporcionar estudiante_programa_id o prospecto_id',
                ], 400);
            }

            $query = CuotaProgramaEstudiante::query()
                ->with([
                    'estudiantePrograma:id,prospecto_id,programa_id',
                    'estudiantePrograma.prospecto:id,nombre_completo,carnet,correo_electronico',
                    'estudiantePrograma.programa:id,nombre_del_programa',
                ]);

            if ($estudianteProgramaId) {
                $query->where('estudiante_programa_id', $estudianteProgramaId);
            } elseif ($prospectoId) {
                $query->whereHas('estudiantePrograma', function ($q) use ($prospectoId) {
                    $q->where('prospecto_id', $prospectoId);
                });
            }

            $cuotas = $query
                ->orderBy('numero_cuota')
                ->get()
                ->map(function ($cuota) {
                    $totalPagado = KardexPago::where('cuota_id', $cuota->id)
                        ->where('estado_pago', 'aprobado')
                        ->sum('monto_pagado');

                    return [
                        'id' => $cuota->id,
                        'numero_cuota' => $cuota->numero_cuota,
                        'fecha_vencimiento' => optional($cuota->fecha_vencimiento)->format('Y-m-d'),
                        'monto' => (float) $cuota->monto,
                        'estado' => $cuota->estado,
                        'paid_at' => optional($cuota->paid_at)->format('Y-m-d H:i:s'),
                        'total_pagado' => (float) $totalPagado,
                        'saldo_pendiente' => (float) ($cuota->monto - $totalPagado),
                    ];
                });

            $ep = $cuotas->first()?->estudiantePrograma ?? EstudiantePrograma::with('prospecto', 'programa')
                ->where('id', $estudianteProgramaId)
                ->orWhereHas('prospecto', fn($q) => $q->where('id', $prospectoId))
                ->first();

            return response()->json([
                'timestamp' => Carbon::now()->toIso8601String(),
                'prospecto' => optional($ep->prospecto)->nombre_completo,
                'carnet' => optional($ep->prospecto)->carnet,
                'programa' => optional($ep->programa)->nombre_del_programa,
                'total_cuotas' => $cuotas->count(),
                'total_monto' => $cuotas->sum('monto'),
                'total_pagado' => $cuotas->sum('total_pagado'),
                'saldo_pendiente' => $cuotas->sum('saldo_pendiente'),
                'cuotas' => $cuotas,
            ]);
        } catch (\Throwable $th) {
            return $this->errorResponse('Error al obtener las cuotas del estudiante', $th);
        }
    }

    // ==================== CRUD DE KARDEX (MOVIMIENTOS DE PAGO) ====================

    /**
     * Listar movimientos de Kardex con filtros (formato DataTable).
     */
    public function kardexIndex(Request $request)
    {
        try {
            $limit = $this->resolveLimit($request, 100);
            $page = max(1, (int) $request->input('page', 1));
            $offset = ($page - 1) * $limit;

            $query = KardexPago::query()
                ->with([
                    'estudiantePrograma:id,prospecto_id,programa_id',
                    'estudiantePrograma.prospecto:id,nombre_completo,carnet,correo_electronico',
                    'estudiantePrograma.programa:id,nombre_del_programa',
                ]);

            // Filtrar por carnet
            if ($request->filled('carnet')) {
                $carnet = $request->input('carnet');
                $query->whereHas('estudiantePrograma.prospecto', function ($q) use ($carnet) {
                    $q->where('carnet', 'like', "%{$carnet}%");
                });
            }

            // Filtrar por prospecto_id
            if ($request->filled('prospecto_id')) {
                $prospectoId = $request->input('prospecto_id');
                $query->whereHas('estudiantePrograma', function ($q) use ($prospectoId) {
                    $q->where('prospecto_id', $prospectoId);
                });
            }

            // Filtrar por estudiante_programa_id
            if ($request->filled('estudiante_programa_id')) {
                $query->where('estudiante_programa_id', $request->input('estudiante_programa_id'));
            }

            // Filtrar por estado_pago
            if ($request->filled('estado_pago')) {
                $query->where('estado_pago', $request->input('estado_pago'));
            }

            // Filtrar por método de pago
            if ($request->filled('metodo_pago')) {
                $query->where('metodo_pago', $request->input('metodo_pago'));
            }

            // Filtrar por banco
            if ($request->filled('banco')) {
                $query->where('banco', 'like', '%' . $request->input('banco') . '%');
            }

            // Filtrar por rango de fecha de pago
            if ($request->filled('fecha_pago_desde')) {
                $query->whereDate('fecha_pago', '>=', $request->input('fecha_pago_desde'));
            }
            if ($request->filled('fecha_pago_hasta')) {
                $query->whereDate('fecha_pago', '<=', $request->input('fecha_pago_hasta'));
            }

            // Búsqueda general
            if ($request->filled('q')) {
                $q = $request->input('q');
                $query->where(function ($qb) use ($q) {
                    $qb->where('numero_boleta', 'like', "%{$q}%")
                       ->orWhere('observaciones', 'like', "%{$q}%")
                       ->orWhereHas('estudiantePrograma.prospecto', function ($sq) use ($q) {
                           $sq->where('nombre_completo', 'like', "%{$q}%")
                              ->orWhere('carnet', 'like', "%{$q}%");
                       });
                });
            }

            $total = $query->count();

            $kardex = $query
                ->orderBy('fecha_pago', 'desc')
                ->offset($offset)
                ->limit($limit)
                ->get()
                ->map(function ($pago) {
                    $ep = $pago->estudiantePrograma;
                    $prospecto = optional($ep)->prospecto;
                    $programa = optional($ep)->programa;

                    return [
                        'id' => $pago->id,
                        'estudiante_programa_id' => $pago->estudiante_programa_id,
                        'cuota_id' => $pago->cuota_id,
                        'fecha_pago' => optional($pago->fecha_pago)->format('Y-m-d'),
                        'fecha_recibo' => optional($pago->fecha_recibo)->format('Y-m-d'),
                        'monto_pagado' => (float) $pago->monto_pagado,
                        'metodo_pago' => $pago->metodo_pago,
                        'estado_pago' => $pago->estado_pago,
                        'numero_boleta' => $pago->numero_boleta,
                        'banco' => $pago->banco,
                        'observaciones' => $pago->observaciones,
                        'prospecto' => $prospecto ? [
                            'id' => $prospecto->id,
                            'nombre_completo' => $prospecto->nombre_completo,
                            'carnet' => $prospecto->carnet,
                            'correo_electronico' => $prospecto->correo_electronico,
                        ] : null,
                        'programa' => $programa ? [
                            'id' => $programa->id,
                            'nombre' => $programa->nombre_del_programa,
                        ] : null,
                        'created_at' => optional($pago->created_at)->format('Y-m-d H:i:s'),
                        'updated_at' => optional($pago->updated_at)->format('Y-m-d H:i:s'),
                    ];
                });

            return response()->json([
                'timestamp' => Carbon::now()->toIso8601String(),
                'pagination' => [
                    'total' => $total,
                    'per_page' => $limit,
                    'current_page' => $page,
                    'last_page' => ceil($total / $limit),
                ],
                'kardex' => $kardex,
            ]);
        } catch (\Throwable $th) {
            return $this->errorResponse('Error al listar los movimientos del kardex', $th);
        }
    }

    /**
     * Ver detalle de un movimiento de Kardex.
     */
    public function kardexShow($id)
    {
        try {
            $pago = KardexPago::with([
                'estudiantePrograma:id,prospecto_id,programa_id',
                'estudiantePrograma.prospecto:id,nombre_completo,carnet,correo_electronico,telefono',
                'estudiantePrograma.programa:id,nombre_del_programa',
            ])->findOrFail($id);

            $ep = $pago->estudiantePrograma;
            $prospecto = optional($ep)->prospecto;
            $programa = optional($ep)->programa;

            // Cuota asociada (si existe)
            $cuota = null;
            if ($pago->cuota_id) {
                $cuotaModel = CuotaProgramaEstudiante::find($pago->cuota_id);
                if ($cuotaModel) {
                    $cuota = [
                        'id' => $cuotaModel->id,
                        'numero_cuota' => $cuotaModel->numero_cuota,
                        'fecha_vencimiento' => optional($cuotaModel->fecha_vencimiento)->format('Y-m-d'),
                        'monto' => (float) $cuotaModel->monto,
                        'estado' => $cuotaModel->estado,
                    ];
                }
            }

            return response()->json([
                'timestamp' => Carbon::now()->toIso8601String(),
                'kardex' => [
                    'id' => $pago->id,
                    'estudiante_programa_id' => $pago->estudiante_programa_id,
                    'cuota_id' => $pago->cuota_id,
                    'fecha_pago' => optional($pago->fecha_pago)->format('Y-m-d'),
                    'fecha_recibo' => optional($pago->fecha_recibo)->format('Y-m-d'),
                    'monto_pagado' => (float) $pago->monto_pagado,
                    'metodo_pago' => $pago->metodo_pago,
                    'estado_pago' => $pago->estado_pago,
                    'numero_boleta' => $pago->numero_boleta,
                    'banco' => $pago->banco,
                    'observaciones' => $pago->observaciones,
                    'prospecto' => $prospecto ? [
                        'id' => $prospecto->id,
                        'nombre_completo' => $prospecto->nombre_completo,
                        'carnet' => $prospecto->carnet,
                        'correo_electronico' => $prospecto->correo_electronico,
                        'telefono' => $prospecto->telefono,
                    ] : null,
                    'programa' => $programa ? [
                        'id' => $programa->id,
                        'nombre' => $programa->nombre_del_programa,
                    ] : null,
                    'cuota' => $cuota,
                    'created_at' => optional($pago->created_at)->format('Y-m-d H:i:s'),
                    'updated_at' => optional($pago->updated_at)->format('Y-m-d H:i:s'),
                ],
            ]);
        } catch (\Throwable $th) {
            return $this->errorResponse('Error al obtener el detalle del movimiento', $th);
        }
    }

    /**
     * Crear un nuevo movimiento de Kardex.
     */
    public function kardexStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'estudiante_programa_id' => 'required|exists:estudiante_programas,id',
                'cuota_id' => 'nullable|exists:cuotas_programa_estudiante,id',
                'fecha_pago' => 'required|date',
                'fecha_recibo' => 'nullable|date',
                'monto_pagado' => 'required|numeric|min:0',
                'metodo_pago' => 'required|string',
                'estado_pago' => 'nullable|string|in:pendiente_revision,aprobado,rechazado',
                'numero_boleta' => 'nullable|string',
                'banco' => 'nullable|string',
                'observaciones' => 'nullable|string',
            ]);

            $kardex = KardexPago::create([
                'estudiante_programa_id' => $validated['estudiante_programa_id'],
                'cuota_id' => $validated['cuota_id'] ?? null,
                'fecha_pago' => $validated['fecha_pago'],
                'fecha_recibo' => $validated['fecha_recibo'] ?? null,
                'monto_pagado' => $validated['monto_pagado'],
                'metodo_pago' => $validated['metodo_pago'],
                'estado_pago' => $validated['estado_pago'] ?? 'pendiente_revision',
                'numero_boleta' => $validated['numero_boleta'] ?? null,
                'banco' => $validated['banco'] ?? null,
                'observaciones' => $validated['observaciones'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $kardex->load([
                'estudiantePrograma.prospecto:id,nombre_completo,carnet',
                'estudiantePrograma.programa:id,nombre_del_programa',
            ]);

            return response()->json([
                'message' => 'Movimiento de kardex creado exitosamente',
                'kardex' => [
                    'id' => $kardex->id,
                    'estudiante_programa_id' => $kardex->estudiante_programa_id,
                    'cuota_id' => $kardex->cuota_id,
                    'fecha_pago' => optional($kardex->fecha_pago)->format('Y-m-d'),
                    'monto_pagado' => (float) $kardex->monto_pagado,
                    'metodo_pago' => $kardex->metodo_pago,
                    'estado_pago' => $kardex->estado_pago,
                    'prospecto' => optional($kardex->estudiantePrograma->prospecto)->nombre_completo,
                    'programa' => optional($kardex->estudiantePrograma->programa)->nombre_del_programa,
                ],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            return $this->errorResponse('Error al crear el movimiento de kardex', $th);
        }
    }

    /**
     * Actualizar un movimiento de Kardex.
     */
    public function kardexUpdate(Request $request, $id)
    {
        try {
            $kardex = KardexPago::findOrFail($id);

            $validated = $request->validate([
                'fecha_pago' => 'sometimes|date',
                'fecha_recibo' => 'nullable|date',
                'monto_pagado' => 'sometimes|numeric|min:0',
                'metodo_pago' => 'sometimes|string',
                'estado_pago' => 'sometimes|string|in:pendiente_revision,aprobado,rechazado',
                'numero_boleta' => 'nullable|string',
                'banco' => 'nullable|string',
                'observaciones' => 'nullable|string',
            ]);

            $kardex->fill($validated);
            $kardex->updated_by = auth()->id();
            $kardex->save();

            $kardex->load([
                'estudiantePrograma.prospecto:id,nombre_completo,carnet',
                'estudiantePrograma.programa:id,nombre_del_programa',
            ]);

            return response()->json([
                'message' => 'Movimiento de kardex actualizado exitosamente',
                'kardex' => [
                    'id' => $kardex->id,
                    'estudiante_programa_id' => $kardex->estudiante_programa_id,
                    'cuota_id' => $kardex->cuota_id,
                    'fecha_pago' => optional($kardex->fecha_pago)->format('Y-m-d'),
                    'monto_pagado' => (float) $kardex->monto_pagado,
                    'metodo_pago' => $kardex->metodo_pago,
                    'estado_pago' => $kardex->estado_pago,
                    'prospecto' => optional($kardex->estudiantePrograma->prospecto)->nombre_completo,
                    'programa' => optional($kardex->estudiantePrograma->programa)->nombre_del_programa,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            return $this->errorResponse('Error al actualizar el movimiento de kardex', $th);
        }
    }

    /**
     * Eliminar un movimiento de Kardex.
     */
    public function kardexDestroy($id)
    {
        try {
            $kardex = KardexPago::findOrFail($id);

            $kardex->deleted_by = auth()->id();
            $kardex->save();
            $kardex->delete();

            return response()->json([
                'message' => 'Movimiento de kardex eliminado exitosamente',
            ]);
        } catch (\Throwable $th) {
            return $this->errorResponse('Error al eliminar el movimiento de kardex', $th);
        }
    }

    // ==================== CRUD DE RECONCILIACIONES BANCARIAS ====================

    /**
     * Listar reconciliaciones bancarias con filtros.
     */
    public function reconciliacionesIndex(Request $request)
    {
        try {
            $limit = $this->resolveLimit($request, 100);
            $page = max(1, (int) $request->input('page', 1));
            $offset = ($page - 1) * $limit;

            $query = ReconciliationRecord::query()
                ->with([
                    'prospecto:id,nombre_completo,carnet,correo_electronico',
                    'kardexPago:id,estudiante_programa_id,monto_pagado,fecha_pago,estado_pago',
                ]);

            // Filtrar por prospecto_id
            if ($request->filled('prospecto_id')) {
                $query->where('prospecto_id', $request->input('prospecto_id'));
            }

            // Filtrar por estado
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            // Filtrar por banco
            if ($request->filled('bank')) {
                $query->where('bank', 'like', '%' . $request->input('bank') . '%');
            }

            // Filtrar por referencia
            if ($request->filled('reference')) {
                $query->where('reference', 'like', '%' . $request->input('reference') . '%');
            }

            // Filtrar por rango de fecha
            if ($request->filled('fecha_desde')) {
                $query->whereDate('date', '>=', $request->input('fecha_desde'));
            }
            if ($request->filled('fecha_hasta')) {
                $query->whereDate('date', '<=', $request->input('fecha_hasta'));
            }

            // Búsqueda general
            if ($request->filled('q')) {
                $q = $request->input('q');
                $query->where(function ($qb) use ($q) {
                    $qb->where('reference', 'like', "%{$q}%")
                       ->orWhere('bank', 'like', "%{$q}%")
                       ->orWhereHas('prospecto', function ($sq) use ($q) {
                           $sq->where('nombre_completo', 'like', "%{$q}%")
                              ->orWhere('carnet', 'like', "%{$q}%");
                       });
                });
            }

            $total = $query->count();

            $reconciliaciones = $query
                ->orderBy('date', 'desc')
                ->offset($offset)
                ->limit($limit)
                ->get()
                ->map(function ($record) {
                    $prospecto = $record->prospecto;
                    $kardex = $record->kardexPago;

                    return [
                        'id' => $record->id,
                        'prospecto_id' => $record->prospecto_id,
                        'kardex_pago_id' => $record->kardex_pago_id,
                        'bank' => $record->bank,
                        'reference' => $record->reference,
                        'amount' => (float) $record->amount,
                        'date' => optional($record->date)->format('Y-m-d'),
                        'status' => $record->status,
                        'notes' => $record->notes,
                        'prospecto' => $prospecto ? [
                            'id' => $prospecto->id,
                            'nombre_completo' => $prospecto->nombre_completo,
                            'carnet' => $prospecto->carnet,
                            'correo_electronico' => $prospecto->correo_electronico,
                        ] : null,
                        'kardex' => $kardex ? [
                            'id' => $kardex->id,
                            'fecha_pago' => optional($kardex->fecha_pago)->format('Y-m-d'),
                            'monto_pagado' => (float) $kardex->monto_pagado,
                            'estado_pago' => $kardex->estado_pago,
                        ] : null,
                        'created_at' => optional($record->created_at)->format('Y-m-d H:i:s'),
                        'updated_at' => optional($record->updated_at)->format('Y-m-d H:i:s'),
                    ];
                });

            return response()->json([
                'timestamp' => Carbon::now()->toIso8601String(),
                'pagination' => [
                    'total' => $total,
                    'per_page' => $limit,
                    'current_page' => $page,
                    'last_page' => ceil($total / $limit),
                ],
                'reconciliaciones' => $reconciliaciones,
            ]);
        } catch (\Throwable $th) {
            return $this->errorResponse('Error al listar las reconciliaciones', $th);
        }
    }

    /**
     * Ver detalle de una reconciliación.
     */
    public function reconciliacionesShow($id)
    {
        try {
            $record = ReconciliationRecord::with([
                'prospecto:id,nombre_completo,carnet,correo_electronico,telefono',
                'kardexPago:id,estudiante_programa_id,monto_pagado,fecha_pago,estado_pago',
                'kardexPago.estudiantePrograma:id,prospecto_id,programa_id',
                'kardexPago.estudiantePrograma.programa:id,nombre_del_programa',
            ])->findOrFail($id);

            $prospecto = $record->prospecto;
            $kardex = $record->kardexPago;
            $programa = optional(optional($kardex)->estudiantePrograma)->programa;

            return response()->json([
                'timestamp' => Carbon::now()->toIso8601String(),
                'reconciliacion' => [
                    'id' => $record->id,
                    'prospecto_id' => $record->prospecto_id,
                    'kardex_pago_id' => $record->kardex_pago_id,
                    'bank' => $record->bank,
                    'reference' => $record->reference,
                    'amount' => (float) $record->amount,
                    'date' => optional($record->date)->format('Y-m-d'),
                    'status' => $record->status,
                    'notes' => $record->notes,
                    'prospecto' => $prospecto ? [
                        'id' => $prospecto->id,
                        'nombre_completo' => $prospecto->nombre_completo,
                        'carnet' => $prospecto->carnet,
                        'correo_electronico' => $prospecto->correo_electronico,
                        'telefono' => $prospecto->telefono,
                    ] : null,
                    'kardex' => $kardex ? [
                        'id' => $kardex->id,
                        'fecha_pago' => optional($kardex->fecha_pago)->format('Y-m-d'),
                        'monto_pagado' => (float) $kardex->monto_pagado,
                        'estado_pago' => $kardex->estado_pago,
                    ] : null,
                    'programa' => $programa ? [
                        'id' => $programa->id,
                        'nombre' => $programa->nombre_del_programa,
                    ] : null,
                    'created_at' => optional($record->created_at)->format('Y-m-d H:i:s'),
                    'updated_at' => optional($record->updated_at)->format('Y-m-d H:i:s'),
                ],
            ]);
        } catch (\Throwable $th) {
            return $this->errorResponse('Error al obtener el detalle de la reconciliación', $th);
        }
    }

    /**
     * Crear una nueva reconciliación (normalmente se importa automáticamente).
     */
    public function reconciliacionesStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'prospecto_id' => 'nullable|exists:prospectos,id',
                'kardex_pago_id' => 'nullable|exists:kardex_pagos,id',
                'bank' => 'required|string',
                'reference' => 'required|string',
                'amount' => 'required|numeric|min:0',
                'date' => 'required|date',
                'status' => 'nullable|string|in:imported,conciliado,rechazado,sin_coincidencia',
                'notes' => 'nullable|string',
            ]);

            $reconciliacion = ReconciliationRecord::create([
                'prospecto_id' => $validated['prospecto_id'] ?? null,
                'kardex_pago_id' => $validated['kardex_pago_id'] ?? null,
                'bank' => $validated['bank'],
                'reference' => $validated['reference'],
                'amount' => $validated['amount'],
                'date' => $validated['date'],
                'status' => $validated['status'] ?? 'imported',
                'notes' => $validated['notes'] ?? null,
            ]);

            return response()->json([
                'message' => 'Reconciliación creada exitosamente',
                'reconciliacion' => [
                    'id' => $reconciliacion->id,
                    'bank' => $reconciliacion->bank,
                    'reference' => $reconciliacion->reference,
                    'amount' => (float) $reconciliacion->amount,
                    'date' => optional($reconciliacion->date)->format('Y-m-d'),
                    'status' => $reconciliacion->status,
                ],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            return $this->errorResponse('Error al crear la reconciliación', $th);
        }
    }

    /**
     * Actualizar una reconciliación (cambiar estado, asociar kardex).
     */
    public function reconciliacionesUpdate(Request $request, $id)
    {
        try {
            $reconciliacion = ReconciliationRecord::findOrFail($id);

            $validated = $request->validate([
                'prospecto_id' => 'nullable|exists:prospectos,id',
                'kardex_pago_id' => 'nullable|exists:kardex_pagos,id',
                'status' => 'sometimes|string|in:imported,conciliado,rechazado,sin_coincidencia',
                'notes' => 'nullable|string',
            ]);

            $reconciliacion->fill($validated);
            $reconciliacion->save();

            return response()->json([
                'message' => 'Reconciliación actualizada exitosamente',
                'reconciliacion' => [
                    'id' => $reconciliacion->id,
                    'bank' => $reconciliacion->bank,
                    'reference' => $reconciliacion->reference,
                    'amount' => (float) $reconciliacion->amount,
                    'date' => optional($reconciliacion->date)->format('Y-m-d'),
                    'status' => $reconciliacion->status,
                    'prospecto_id' => $reconciliacion->prospecto_id,
                    'kardex_pago_id' => $reconciliacion->kardex_pago_id,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            return $this->errorResponse('Error al actualizar la reconciliación', $th);
        }
    }

    /**
     * Eliminar una reconciliación.
     */
    public function reconciliacionesDestroy($id)
    {
        try {
            $reconciliacion = ReconciliationRecord::findOrFail($id);
            $reconciliacion->delete();

            return response()->json([
                'message' => 'Reconciliación eliminada exitosamente',
            ]);
        } catch (\Throwable $th) {
            return $this->errorResponse('Error al eliminar la reconciliación', $th);
        }
    }
}
