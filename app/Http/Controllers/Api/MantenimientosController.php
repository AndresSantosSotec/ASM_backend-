<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\ModuleviewPermisos;
use App\Models\Achievement;
use App\Models\Actividades;
use App\Models\AdicionalEstudiante;
use App\Models\AdvisorCommissionRate;
use App\Models\ApprovalFlow;
use App\Models\ApprovalStage;
use App\Models\AuditLog;
use App\Models\Citas;
use App\Models\CollectionLog;
use App\Models\ColumnConfiguration;
use App\Models\Commission;
use App\Models\CommissionConfig;
use App\Models\ContactoEnviado;
use App\Models\Convenio;
use App\Models\Course;
use App\Models\CuotaProgramaEstudiante;
use App\Models\Departamento;
use App\Models\DuplicateRecord;
use App\Models\EstudiantePrograma;
use App\Models\GpaHist;
use App\Models\Inscripcion;
use App\Models\InscripcionPeriodo;
use App\Models\Interacciones;
use App\Models\Invoice;
use App\Models\KardexPago;
use App\Models\Modules;
use App\Models\ModulesViews;
use App\Models\Moduleview;
use App\Models\Municipio;
use App\Models\Nom;
use App\Models\Pais;
use App\Models\Payment;
use App\Models\PaymentExceptionCategory;
use App\Models\PaymentGateway;
use App\Models\PaymentPlan;
use App\Models\PaymentPlanInstallment;
use App\Models\PaymentRule;
use App\Models\PaymentRuleBlockingRule;
use App\Models\PaymentRuleNotification;
use App\Models\PeriodoInscripcion;
use App\Models\PeriodoPrograma;
use App\Models\Permisos;
use App\Models\Permission;
use App\Models\PrecioConvenioPrograma;
use App\Models\PrecioPrograma;
use App\Models\Programa;
use App\Models\Prospecto;
use App\Models\ProspectosDocumento;
use App\Models\ReconciliationRecord;
use App\Models\Role;
use App\Models\RolePermisos;
use App\Models\RolePermissionConfig;
use App\Models\Session;
use App\Models\TareasGen;
use App\Models\User;
use App\Models\UserPermisos;
use App\Models\UserRole;


class MantenimientosController extends Controller
{
    //Dasborad de datos generales del mantenimiento

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

            return response()->json([
                'timestamp' => $now->toIso8601String(),
                'filters' => $filters,
                'kardex' => $kardexSummary,
                'reconciliaciones' => $reconciliationSummary,
                'cuotas' => $cuotasSummary,
            ]);
        } catch (\Throwable $th) {
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

            $kardex = $this->buildKardexBaseQuery($filters)
                ->with([
                    'estudiantePrograma:id,prospecto_id,programa_id',
                    'estudiantePrograma.prospecto:id,nombre_completo,carnet,correo_electronico,activo',
                    'estudiantePrograma.programa:id,nombre',
                    'cuota:id,estudiante_programa_id,numero_cuota,fecha_vencimiento,monto,estado,paid_at',
                    'reconciliationRecords:id,kardex_pago_id,bank,reference,amount,date,status',
                ])
                ->orderByDesc('fecha_pago')
                ->limit($limit)
                ->get()
                ->map(function (KardexPago $pago) {
                    $estudiantePrograma = $pago->estudiantePrograma;
                    $prospecto = optional($estudiantePrograma)->prospecto;
                    $programa = optional($estudiantePrograma)->programa;
                    $cuota = $pago->cuota;

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
                        'reconciliaciones' => $pago->reconciliationRecords->map(function (ReconciliationRecord $record) {
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
                    'kardexPago.estudiantePrograma.programa:id,nombre',
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
                    'estudiantePrograma.programa:id,nombre',
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

            return response()->json([
                'timestamp' => Carbon::now()->toIso8601String(),
                'filters' => array_merge($filters, ['limit' => $limit]),
                'kardex' => $kardex,
                'reconciliaciones' => $reconciliaciones,
                'cuotas' => $cuotas,
            ]);
        } catch (\Throwable $th) {
            return $this->errorResponse('Error al obtener los datos de Kardex', $th);
        }
    }

    /**
     * Dashboard para cuotas por estudiante.
     */
    public function cuotasDashboard(Request $request)
    {
        try {
            $filters = $this->extractCommonFilters($request);
            $limit = $this->resolveLimit($request);
            $now = Carbon::now();

            $cuotasBase = $this->buildCuotasBaseQuery($filters);

            $estudiantesActivos = EstudiantePrograma::query()
                ->whereHas('prospecto', function ($q) {
                    $q->where('activo', true);
                })
                ->when($filters['programa_id'] ?? null, function ($q, $programaId) {
                    $q->where('programa_id', $programaId);
                })
                ->when($filters['prospecto_id'] ?? null, function ($q, $prospectoId) {
                    $q->where('prospecto_id', $prospectoId);
                })
                ->count();

            $saldoPendiente = (float) (clone $cuotasBase)
                ->where(function ($q) {
                    $q->whereNull('estado')->orWhere('estado', '!=', 'pagado');
                })
                ->sum('monto');

            $cuotasEnMora = (clone $cuotasBase)
                ->where(function ($q) {
                    $q->whereNull('estado')->orWhere('estado', '!=', 'pagado');
                })
                ->whereDate('fecha_vencimiento', '<', $now->toDateString())
                ->count();

            $planesReestructurados = PaymentPlan::query()
                ->when($filters['prospecto_id'] ?? null, function ($q, $prospectoId) {
                    $q->where('prospecto_id', $prospectoId);
                })
                ->whereIn('status', ['restructurado', 'reestructurado', 'restructured'])
                ->count();

            $cuotas = $this->buildCuotasBaseQuery($filters)
                ->with([
                    'estudiantePrograma:id,prospecto_id,programa_id',
                    'estudiantePrograma.prospecto:id,nombre_completo,carnet,correo_electronico,telefono,activo',
                    'estudiantePrograma.programa:id,nombre',
                ])
                ->orderBy('fecha_vencimiento')
                ->get();

            $estudiantes = $cuotas
                ->groupBy('estudiante_programa_id')
                ->values()
                ->map(function ($cuotasEstudiante) use ($limit) {
                    /** @var \Illuminate\Support\Collection $cuotasEstudiante */
                    $cuotasEstudiante = $cuotasEstudiante instanceof \Illuminate\Support\Collection
                        ? $cuotasEstudiante
                        : collect($cuotasEstudiante);

                    $primeraCuota = $cuotasEstudiante->first();
                    $estudiantePrograma = $primeraCuota ? $primeraCuota->estudiantePrograma : null;
                    $prospecto = optional($estudiantePrograma)->prospecto;
                    $programa = optional($estudiantePrograma)->programa;

                    $cuotasOrdenadas = $cuotasEstudiante->sortBy('fecha_vencimiento');
                    $pendientes = $cuotasOrdenadas->filter(function (CuotaProgramaEstudiante $cuota) {
                        return $cuota->estado !== 'pagado';
                    });

                    $proximaCuota = $pendientes->first();

                    return [
                        'estudiante_programa_id' => $estudiantePrograma ? $estudiantePrograma->id : null,
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
                        'cuotas_pagadas' => $cuotasOrdenadas->count() - $pendientes->count(),
                        'proxima_cuota' => $proximaCuota ? [
                            'id' => $proximaCuota->id,
                            'numero_cuota' => $proximaCuota->numero_cuota,
                            'fecha_vencimiento' => optional($proximaCuota->fecha_vencimiento)->format('Y-m-d'),
                            'monto' => (float) $proximaCuota->monto,
                            'estado' => $proximaCuota->estado,
                        ] : null,
                        'cuotas' => $cuotasOrdenadas->take($limit)->map(function (CuotaProgramaEstudiante $cuota) {
                            return [
                                'id' => $cuota->id,
                                'numero_cuota' => $cuota->numero_cuota,
                                'fecha_vencimiento' => optional($cuota->fecha_vencimiento)->format('Y-m-d'),
                                'monto' => (float) $cuota->monto,
                                'estado' => $cuota->estado,
                                'paid_at' => optional($cuota->paid_at)->format('Y-m-d H:i:s'),
                            ];
                        })->values(),
                    ];
                })
                ->take($limit);

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
     * Listado auxiliar de estudiantes activos para formularios de mantenimiento.
     */
    public function estudiantesActivos(Request $request)
    {
        try {
            $limit = $this->resolveLimit($request, 200);
            $search = trim((string) $request->input('search'));
            $programaId = $request->input('programa_id');

            $query = Prospecto::query()
                ->select('id', 'nombre_completo', 'carnet', 'correo_electronico', 'telefono')
                ->where('activo', true)
                ->with(['programas' => function ($q) {
                    $q->select('id', 'prospecto_id', 'programa_id')
                        ->with(['programa' => function ($sq) {
                            $sq->select('id', 'nombre');
                        }]);
                }]);

            if ($programaId) {
                $query->whereHas('programas', function ($q) use ($programaId) {
                    $q->where('programa_id', $programaId);
                });
            }

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $like = '%' . str_replace('%', '\\%', $search) . '%';
                    $q->where('nombre_completo', 'like', $like)
                        ->orWhere('carnet', 'like', $like)
                        ->orWhere('correo_electronico', 'like', $like)
                        ->orWhere('telefono', 'like', $like);
                });
            }

            $prospectos = $query
                ->orderBy('nombre_completo')
                ->limit($limit)
                ->get()
                ->map(function (Prospecto $prospecto) {
                    return [
                        'id' => $prospecto->id,
                        'nombre' => $prospecto->nombre_completo,
                        'carnet' => $prospecto->carnet,
                        'correo' => $prospecto->correo_electronico,
                        'telefono' => $prospecto->telefono,
                        'programas' => $prospecto->programas->map(function (EstudiantePrograma $programa) {
                            $detallePrograma = $programa->programa;
                            return [
                                'estudiante_programa_id' => $programa->id,
                                'programa_id' => optional($detallePrograma)->id,
                                'programa_nombre' => optional($detallePrograma)->nombre,
                            ];
                        })->values(),
                    ];
                });

            return response()->json([
                'timestamp' => Carbon::now()->toIso8601String(),
                'filters' => [
                    'search' => $search,
                    'programa_id' => $programaId,
                    'limit' => $limit,
                ],
                'data' => $prospectos,
            ]);
        } catch (\Throwable $th) {
            return $this->errorResponse('Error al obtener estudiantes activos', $th);
        }
    }

    /**
     * Construye el query base de Kardex aplicando filtros.
     */
    private function buildKardexBaseQuery(array $filters)
    {
        $query = KardexPago::query()
            ->whereHas('estudiantePrograma.prospecto', function ($q) use ($filters) {
                $q->where('activo', true);

                if (!empty($filters['search'])) {
                    $like = '%' . str_replace('%', '\\%', $filters['search']) . '%';
                    $q->where(function ($sq) use ($like) {
                        $sq->where('nombre_completo', 'like', $like)
                            ->orWhere('carnet', 'like', $like)
                            ->orWhere('correo_electronico', 'like', $like);
                    });
                }
            });

        if (!empty($filters['prospecto_id'])) {
            $query->whereHas('estudiantePrograma', function ($q) use ($filters) {
                $q->where('prospecto_id', $filters['prospecto_id']);
            });
        }

        if (!empty($filters['programa_id'])) {
            $query->whereHas('estudiantePrograma', function ($q) use ($filters) {
                $q->where('programa_id', $filters['programa_id']);
            });
        }

        if (!empty($filters['estado_pago'])) {
            $query->where('estado_pago', $filters['estado_pago']);
        } elseif (!empty($filters['estado'])) {
            $query->where('estado_pago', $filters['estado']);
        }

        if ($filters['fecha_inicio'] ?? null) {
            $query->whereDate('fecha_pago', '>=', $filters['fecha_inicio']->format('Y-m-d'));
        }

        if ($filters['fecha_fin'] ?? null) {
            $query->whereDate('fecha_pago', '<=', $filters['fecha_fin']->format('Y-m-d'));
        }

        if (!empty($filters['search'])) {
            $like = '%' . str_replace('%', '\\%', $filters['search']) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('numero_boleta', 'like', $like)
                    ->orWhere('banco', 'like', $like)
                    ->orWhere('observaciones', 'like', $like);
            });
        }

        return $query;
    }

    /**
     * Construye el query base de conciliaciones aplicando filtros.
     */
    private function buildReconciliationBaseQuery(array $filters)
    {
        $query = ReconciliationRecord::query()
            ->whereHas('prospecto', function ($q) use ($filters) {
                $q->where('activo', true);

                if (!empty($filters['search'])) {
                    $like = '%' . str_replace('%', '\\%', $filters['search']) . '%';
                    $q->where(function ($sq) use ($like) {
                        $sq->where('nombre_completo', 'like', $like)
                            ->orWhere('carnet', 'like', $like)
                            ->orWhere('correo_electronico', 'like', $like);
                    });
                }
            });

        if (!empty($filters['prospecto_id'])) {
            $query->where('prospecto_id', $filters['prospecto_id']);
        }

        if (!empty($filters['programa_id'])) {
            $query->whereHas('prospecto.programas', function ($q) use ($filters) {
                $q->where('programa_id', $filters['programa_id']);
            });
        }

        if ($filters['fecha_inicio'] ?? null) {
            $query->whereDate('date', '>=', $filters['fecha_inicio']->format('Y-m-d'));
        }

        if ($filters['fecha_fin'] ?? null) {
            $query->whereDate('date', '<=', $filters['fecha_fin']->format('Y-m-d'));
        }

        if (!empty($filters['estado_reconciliacion'])) {
            $estado = $filters['estado_reconciliacion'];

            if ($estado === 'pendiente') {
                $query->where(function ($q) {
                    $q->whereNull('status')
                        ->orWhereIn('status', ['imported', 'sin_coincidencia']);
                });
            } else {
                $query->where('status', $estado);
            }
        }

        if (!empty($filters['search'])) {
            $like = '%' . str_replace('%', '\\%', $filters['search']) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('reference', 'like', $like)
                    ->orWhere('bank', 'like', $like);
            });
        }

        return $query;
    }

    /**
     * Construye el query base de cuotas aplicando filtros.
     */
    private function buildCuotasBaseQuery(array $filters)
    {
        $query = CuotaProgramaEstudiante::query()
            ->whereHas('estudiantePrograma.prospecto', function ($q) use ($filters) {
                $q->where('activo', true);

                if (!empty($filters['search'])) {
                    $like = '%' . str_replace('%', '\\%', $filters['search']) . '%';
                    $q->where(function ($sq) use ($like) {
                        $sq->where('nombre_completo', 'like', $like)
                            ->orWhere('carnet', 'like', $like)
                            ->orWhere('correo_electronico', 'like', $like);
                    });
                }
            });

        if (!empty($filters['prospecto_id'])) {
            $query->whereHas('estudiantePrograma', function ($q) use ($filters) {
                $q->where('prospecto_id', $filters['prospecto_id']);
            });
        }

        if (!empty($filters['programa_id'])) {
            $query->whereHas('estudiantePrograma', function ($q) use ($filters) {
                $q->where('programa_id', $filters['programa_id']);
            });
        }

        if ($filters['fecha_inicio'] ?? null) {
            $query->whereDate('fecha_vencimiento', '>=', $filters['fecha_inicio']->format('Y-m-d'));
        }

        if ($filters['fecha_fin'] ?? null) {
            $query->whereDate('fecha_vencimiento', '<=', $filters['fecha_fin']->format('Y-m-d'));
        }

        if (!empty($filters['estado_cuota'])) {
            $query->where('estado', $filters['estado_cuota']);
        }

        if (!empty($filters['search'])) {
            $like = '%' . str_replace('%', '\\%', $filters['search']) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('numero_cuota', 'like', $like)
                    ->orWhere('estado', 'like', $like);
            });
        }

        return $query;
    }

    /**
     * Extrae y normaliza filtros comunes desde la petición.
     */
    private function extractCommonFilters(Request $request): array
    {
        return [
            'prospecto_id' => $request->input('prospecto_id'),
            'programa_id' => $request->input('programa_id'),
            'estado' => $request->input('estado'),
            'estado_pago' => $request->input('estado_pago'),
            'estado_cuota' => $request->input('estado_cuota'),
            'estado_reconciliacion' => $request->input('estado_reconciliacion'),
            'search' => trim((string) $request->input('search')) ?: null,
            'fecha_inicio' => $this->parseDate($request->input('fecha_inicio')),
            'fecha_fin' => $this->parseDate($request->input('fecha_fin')),
        ];
    }

    /**
     * Convierte cadenas en fechas (solo fecha) o retorna null si no es válida.
     */
    private function parseDate($value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $th) {
            return null;
        }
    }

    /**
     * Límite de registros seguro para respuestas.
     */
    private function resolveLimit(Request $request, int $default = 100, string $key = 'limit'): int
    {
        $limit = (int) $request->input($key, $default);

        if ($limit < 1) {
            $limit = 1;
        }

        if ($limit > 500) {
            $limit = 500;
        }

        return $limit;
    }

    /**
     * Respuesta de error con detalle en modo debug.
     */
    private function errorResponse(string $message, \Throwable $th)
    {
        return response()->json([
            'message' => $message,
            'error' => config('app.debug') ? $th->getMessage() : 'Error interno del servidor',
            'trace' => config('app.debug') ? $th->getTraceAsString() : null,
        ], 500);
    }
}
