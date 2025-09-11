<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\EstudiantePrograma;
use App\Models\CuotaProgramaEstudiante;
use App\Models\PaymentRule;
use App\Models\PaymentRuleBlockingRule;
use App\Models\KardexPago;
use App\Models\Programa;
use App\Models\Prospecto;

class GestionPagosController extends Controller
{
    /**
     * GET /collections/late-payments
     */
    public function latePayments(Request $request)
    {
        $rid = (string) Str::uuid();
        $now = Carbon::now();

        Log::info("[GP][$rid] latePayments method called", [
            'request_params' => $request->all(),
            'timestamp' => $now->toISOString()
        ]);

        $q          = trim((string) $request->get('q', ''));
        $bucket     = strtolower((string) $request->get('bucket', 'all'));
        $programaId = $request->integer('programa_id');
        $perPage    = max(1, min(100, (int) ($request->integer('per_page') ?? 25)));

        Log::info("[GP][$rid] latePayments params processed", compact('q', 'bucket', 'programaId', 'perPage'));

        try {
            // Tablas
            $c  = (new CuotaProgramaEstudiante())->getTable(); // cuotas_programa_estudiante
            $ep = (new EstudiantePrograma())->getTable();      // estudiante_programa
            $p  = (new Prospecto())->getTable();               // prospectos
            $pr = (new Programa())->getTable();                // tb_programas

            Log::info("[GP][$rid] Table names", [
                'cuotas' => $c,
                'estudiante_programa' => $ep,
                'prospectos' => $p,
                'programas' => $pr
            ]);

            $query = DB::table("$c as c")
                ->join("$ep as ep", 'ep.id', '=', 'c.estudiante_programa_id')
                ->join("$p  as s",  's.id',  '=', 'ep.prospecto_id')
                ->join("$pr as pr", 'pr.id', '=', 'ep.programa_id')
                ->where('c.estado', 'pendiente')
                ->where('c.fecha_vencimiento', '<', $now)
                ->when($programaId, fn($q2) => $q2->where('ep.programa_id', $programaId))
                ->when($q !== '', function ($q2) use ($q) {
                    $q2->where(function ($w) use ($q) {
                        $w->where('s.nombre_completo', 'like', "%{$q}%")
                            ->orWhere('s.carnet', 'like', "%{$q}%")
                            ->orWhere('ep.id', $q);
                    });
                });

            // Filtrado por bucket
            if ($bucket !== 'all') {
                $query->where(function ($q2) use ($bucket, $now) {
                    switch ($bucket) {
                        case 'b1':
                            $q2->whereRaw("c.fecha_vencimiento >= ?", [$now->copy()->subDays(5)]);
                            break;
                        case 'b2':
                            $q2->whereRaw("c.fecha_vencimiento >= ?", [$now->copy()->subDays(10)])
                                ->whereRaw("c.fecha_vencimiento < ?",  [$now->copy()->subDays(5)]);
                            break;
                        case 'b3':
                            $q2->whereRaw("c.fecha_vencimiento >= ?", [$now->copy()->subDays(30)])
                                ->whereRaw("c.fecha_vencimiento < ?",  [$now->copy()->subDays(10)]);
                            break;
                        case 'b4':
                            $q2->whereRaw("c.fecha_vencimiento < ?",  [$now->copy()->subDays(30)]);
                            break;
                    }
                });
            }

            // ⚠️ usar nombre_del_programa en select y groupBy
            $query->groupBy('ep.id', 's.id', 's.nombre_completo', 's.carnet', 'pr.nombre_del_programa')
                ->selectRaw('
                    ep.id as ep_id,
                    s.id as student_id,
                    s.nombre_completo as student_name,
                    s.carnet as carnet,
                    pr.nombre_del_programa as programa,
                    SUM(c.monto) as total_debt,
                    MIN(c.fecha_vencimiento) as oldest_due,
                    COUNT(*) as cuotas_pendientes
                ')
                ->orderBy('oldest_due', 'asc');

            Log::info("[GP][$rid] Query built successfully");

            $paginated = $query->paginate($perPage);

            Log::info("[GP][$rid] Query executed successfully", [
                'total_results' => $paginated->total(),
                'current_page'  => $paginated->currentPage()
            ]);

            $paymentRule = PaymentRule::first();

            $items = collect($paginated->items())->map(function ($row) use ($now, $paymentRule) {
                $daysLate   = Carbon::parse($row->oldest_due)->diffInDays($now);
                $lateMonths = (int) ceil($daysLate / 30);

                $bucket = 'B1';
                if ($daysLate <= 5)  $bucket = 'B1';
                elseif ($daysLate <= 10) $bucket = 'B2';
                elseif ($daysLate <= 30) $bucket = 'B3';
                else                     $bucket = 'B4';

                $services = $this->obtenerServiciosBloqueados($daysLate);
                $status   = count($services) > 0 ? 'bloqueado' : 'activo';

                return [
                    'id'          => (int) $row->ep_id,
                    'studentId'   => (int) $row->student_id,
                    'name'        => (string) $row->student_name,
                    'program'     => (string) $row->programa,
                    'totalDebt'   => (float) $row->total_debt,
                    'lateMonths'  => $lateMonths,
                    'daysLate'    => $daysLate,
                    'bucket'      => $bucket,
                    'status'      => $status,
                    'lastContact' => null,
                    'promiseDate' => null,
                    // extras útiles para UI
                    'carnet'      => (string) $row->carnet,
                    'oldestDue'   => Carbon::parse($row->oldest_due)->toDateString(),
                    'pendingCount' => (int) $row->cuotas_pendientes,
                ];
            })->values();

            Log::info("[GP][$rid] Response prepared successfully", [
                'items_count' => $items->count()
            ]);

            return response()->json([
                'data' => $items,
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'last_page'    => $paginated->lastPage(),
                ],
                'summary' => [
                    'total_students' => (int) $paginated->total(),
                ],
                'request_id' => $rid,
            ]);
        } catch (\Exception $e) {
            Log::error("[GP][$rid] Error in latePayments", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'line'  => $e->getLine(),
                'file'  => $e->getFile()
            ]);

            return response()->json([
                'error'      => 'Error interno del servidor',
                'message'    => 'Error al obtener los pagos atrasados',
                'request_id' => $rid,
                'debug'      => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * GET /collections/students/{epId}/snapshot
     */
    public function studentSnapshot(int $epId, Request $request)
    {
        $rid = (string) Str::uuid();
        $now = Carbon::now();

        Log::info("[GP][$rid] studentSnapshot method called", [
            'epId' => $epId,
            'timestamp' => $now->toISOString()
        ]);

        try {
            $ep = EstudiantePrograma::with([
                'prospecto:id,nombre_completo,carnet',
                'programa:id,nombre_del_programa'
            ])->findOrFail($epId);

            // ⚠️ CORREGIR: usar los nombres de columnas correctos
            $pending = CuotaProgramaEstudiante::query()
                ->where('estudiante_programa_id', $epId)
                ->where('estado', 'pendiente')
                ->orderBy('fecha_vencimiento')
                ->get(['id', 'monto', 'fecha_vencimiento']); // ⚠️ QUITAR 'concepto'

            $payments = KardexPago::query()
                ->where('estudiante_programa_id', $epId)
                ->where('estado_pago', 'aprobado')
                ->orderByDesc('fecha_pago')
                ->limit(10)
                ->get(['id', 'monto_pagado', 'fecha_pago', 'metodo_pago']);

            return response()->json([
                'ep' => [
                    'id' => $ep->id,
                    'prospecto' => [
                        'id' => $ep->prospecto->id,
                        'nombre_completo' => $ep->prospecto->nombre_completo,
                        'carnet' => $ep->prospecto->carnet,
                    ],
                    'programa' => [
                        'id' => $ep->programa->id,
                        'nombre_del_programa' => $ep->programa->nombre_del_programa ?? 'N/A',
                    ],
                ],
                'pending_installments' => $pending->map(function ($c) use ($now) {
                    $days = Carbon::parse($c->fecha_vencimiento)->diffInDays($now);
                    return [
                        'id'                => (int) $c->id,
                        'monto'             => (float) $c->monto,
                        'fecha_vencimiento' => (string) $c->fecha_vencimiento,
                        'days_late'         => $days,
                    ];
                }),
                'recent_payments' => $payments,
                'request_id'      => $rid,
            ]);
        } catch (\Exception $e) {
            Log::error("[GP][$rid] Error in studentSnapshot", [
                'error' => $e->getMessage(),
                'epId'  => $epId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error'      => 'Error interno del servidor',
                'message'    => 'Error al obtener el snapshot del estudiante',
                'request_id' => $rid,
                'debug'      => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * GET /collections/payment-plans
     * Overview de planes sugeridos (no persistidos)
     */
    public function paymentPlansOverview(Request $request)
    {
        $now = Carbon::now();
        $perPage = max(1, min(100, (int) ($request->integer('per_page') ?? 25)));
        $q       = trim((string) $request->get('q', ''));

        $rule = PaymentRule::first();

        $base = EstudiantePrograma::query()
            ->with([
                'prospecto:id,nombre_completo,carnet',
                'programa:id,nombre_del_programa,duracion_meses',
                'cuotas' => function ($q2) {
                    $q2->where('estado', 'pendiente')->orderBy('fecha_vencimiento');
                }
            ])
            ->whereHas('cuotas', fn($w) => $w->where('estado', 'pendiente'));

        if ($q !== '') {
            $base->whereHas('prospecto', function ($w) use ($q) {
                $w->where('nombre_completo', 'like', "%{$q}%")
                    ->orWhere('carnet', 'like', "%{$q}%");
            })->orWhere('id', $q);
        }

        $paginated = $base->paginate($perPage);

        $items = collect($paginated->items())->map(function (EstudiantePrograma $ep) use ($now, $rule) {
            $cuotas = $ep->cuotas;
            $totalDebt = (float) $cuotas->sum('monto');
            $lateFee   = 0.0;

            $oldest = null;
            $nextDue = null;

            foreach ($cuotas as $c) {
                $lateFee += $this->calcLateForCuota($c, $rule, $now);
                $v = Carbon::parse($c->fecha_vencimiento);
                if (!$oldest || $v->lt($oldest)) $oldest = $v;
                if (!$nextDue || $v->lt($nextDue)) $nextDue = $v;
            }

            $totalWithLate = round($totalDebt + $lateFee, 2);

            $duracion   = (int) ($ep->duracion_meses ?? 0);
            $elapsed    = $this->monthsElapsed($ep->fecha_inicio, $now);
            $remaining  = max(1, $duracion > 0 ? ($duracion - $elapsed) : 6); // fallback 6
            $dueDay     = (int) ($rule->due_day ?? 30);
            $startDate  = $this->nextDueDateFromRule($now, $dueDay);

            $installment  = round($totalWithLate / $remaining, 2);

            $inst = [];
            for ($i = 1; $i <= $remaining; $i++) {
                $inst[] = [
                    'number'  => $i,
                    'amount'  => $installment,
                    'dueDate' => $startDate->copy()->addMonthsNoOverflow($i - 1)->toDateString(),
                    'status'  => 'pendiente',
                ];
            }

            return [
                'id'            => $ep->id,
                'studentId'     => $ep->prospecto->id,
                'studentName'   => $ep->prospecto->nombre_completo,
                'carnet'        => $ep->prospecto->carnet,
                'program'       => $ep->programa->nombre_del_programa ?? 'N/A',
                'programId'     => $ep->programa->id ?? null,

                'originalDebt'  => round($totalDebt, 2),
                'currentDebt'   => round($totalWithLate, 2),
                'lateFeeTotal'  => round($lateFee, 2),

                'oldestDue'     => $oldest ? $oldest->toDateString() : null,
                'nextDue'       => $nextDue ? $nextDue->toDateString() : null,
                'pendingCount'  => $cuotas->count(),

                'durationMonths' => $duracion,
                'monthsElapsed' => $elapsed,
                'monthsRemaining' => $remaining,

                'suggestedMonths'      => $remaining,
                'suggestedInstallment' => $installment,
                'dueDay'               => $dueDay,
                'startDate'            => $startDate->toDateString(),

                'installments'  => $inst,
                'status'        => 'activo',
                'notes'         => 'Plan sugerido automáticamente (preview)',
                'createdBy'     => 'system',
                'endDate'       => $startDate->copy()->addMonthsNoOverflow($remaining - 1)->toDateString(),
            ];
        })->values();

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'last_page'    => $paginated->lastPage(),
            ]
        ]);
    }

    /**
     * POST /collections/payment-plans/preview
     * Calculadora / previsualización de un plan (no persiste)
     */
    public function previewPaymentPlan(Request $request)
    {
        $now = Carbon::now();
        $v = Validator::make($request->all(), [
            'ep_id'             => 'required|integer|exists:estudiante_programa,id',
            'months'            => 'nullable|integer|min:1|max:48',
            'start_date'        => 'nullable|date',
            'include_late_fees' => 'nullable|boolean',
        ]);
        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], 422);
        }

        $ep = EstudiantePrograma::with([
            'prospecto',
            'programa',
            'cuotas' => fn($q) => $q->where('estado', 'pendiente')->orderBy('fecha_vencimiento')
        ])->findOrFail($request->ep_id);

        $rule   = PaymentRule::first();
        $dueDay = (int) ($rule->due_day ?? 30);

        $startDate = $request->filled('start_date')
            ? Carbon::parse($request->start_date)
            : $this->nextDueDateFromRule($now, $dueDay);

        $includeLate = $request->boolean('include_late_fees', true);

        $totalDebt = (float) $ep->cuotas->sum('monto');
        $lateFee   = 0.0;
        if ($includeLate) {
            foreach ($ep->cuotas as $c) {
                $lateFee += $this->calcLateForCuota($c, $rule, $now);
            }
        }
        $total = round($totalDebt + $lateFee, 2);

        $suggestedMonths = max(1, ($ep->duracion_meses ?? 0) - $this->monthsElapsed($ep->fecha_inicio, $now));
        if ($suggestedMonths <= 0) $suggestedMonths = 6;

        $months = (int) ($request->months ?? $suggestedMonths);
        $amount = round($total / $months, 2);

        $installments = [];
        for ($i = 1; $i <= $months; $i++) {
            $installments[] = [
                'number'  => $i,
                'amount'  => $amount,
                'dueDate' => $startDate->copy()->addMonthsNoOverflow($i - 1)->toDateString(),
                'status'  => 'pendiente',
            ];
        }

        return response()->json([
            'plan' => [
                'id'                => null,
                'epId'              => $ep->id,
                'student'           => $ep->prospecto->nombre_completo,
                'program'           => $ep->programa->nombre_del_programa ?? 'N/A',
                'originalDebt'      => round($totalDebt, 2),
                'lateFeeTotal'      => round($lateFee, 2),
                'total'             => $total,
                'months'            => $months,
                'startDate'         => $startDate->toDateString(),
                'dueDay'            => $dueDay,
                'installmentAmount' => $amount,
                'installments'      => $installments,
                'notes'             => 'Previsualización (no persistido)',
            ]
        ]);
    }

    /**
     * POST /collections/payment-plans
     * Crea un plan "ligero" (stub 201 con payload). No reprograma cuotas aún.
     */
    public function createPaymentPlan(Request $request)
    {
        // Por ahora delega al preview y devuelve 201 (sin tocar cuotas).
        $preview = $this->previewPaymentPlan($request);
        if ($preview->getStatusCode() !== 200) return $preview;

        $data = $preview->getData(true);
        $data['plan']['id'] = 'preview-' . Str::uuid()->toString();

        return response()->json($data['plan'], 201);
    }

    // ---------- helpers ----------

    private function obtenerServiciosBloqueados(int $diasVencidos): array
    {
        try {
            $rules = PaymentRuleBlockingRule::query()
                ->where('days_after_due', '<=', $diasVencidos)
                ->orderByDesc('days_after_due')
                ->get(['affected_services']);

            $servicios = [];
            foreach ($rules as $r) {
                $arr = is_array($r->affected_services) ? $r->affected_services : [];
                $servicios = array_merge($servicios, $arr);
            }
            return array_values(array_unique($servicios));
        } catch (\Exception $e) {
            Log::warning("Error getting blocked services", ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function calcLateForCuota(CuotaProgramaEstudiante $cuota, ?PaymentRule $rule, Carbon $now): float
    {
        if (!$rule) return 0.0;
        $venc = Carbon::parse($cuota->fecha_vencimiento);
        if ($venc->gte($now)) return 0.0;

        $monthsOverdue = $venc->diffInMonths($now);
        if ($monthsOverdue === 0 && $venc->diffInDays($now) > 0) {
            $monthsOverdue = 1;
        }

        $late = (float)($rule->late_fee_amount ?? 0);
        if ($late <= 0) return 0.0;

        // porcentaje si <=1; fijo si >1
        return $late <= 1
            ? (float)$cuota->monto * $late * $monthsOverdue
            : $late * $monthsOverdue;
    }

    private function nextDueDateFromRule(Carbon $now, ?int $dueDay): Carbon
    {
        $day = $dueDay ?: 30;
        $base = $now->copy()->day($day);
        if ($now->day > $day) $base->addMonthNoOverflow();
        return $base;
    }

    private function monthsElapsed($startDate, Carbon $now): int
    {
        if (!$startDate) return 0;
        $start = $startDate instanceof Carbon ? $startDate : Carbon::parse($startDate);
        return max(0, $start->diffInMonths($now));
    }
}
