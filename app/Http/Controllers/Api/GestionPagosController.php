<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
     * Lista paginada de EP (estudiante_programa) con cuotas vencidas.
     *
     * Query params:
     * - q: string (nombre/carnet)
     * - bucket: b1|b2|b3|b4|all (default all)
     * - programa_id: int
     * - per_page: int (default 25)
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

        Log::info("[GP][$rid] latePayments params processed", compact('q','bucket','programaId','perPage'));

        try {
            // Cuotas vencidas y pendientes agrupadas por EP
            $c = (new CuotaProgramaEstudiante())->getTable();   // cuotas_programa_estudiante
            $ep = (new EstudiantePrograma())->getTable();       // estudiante_programa
            $p = (new Prospecto())->getTable();                 // prospectos
            $pr = (new Programa())->getTable();                 // programas (tb_programas)

            Log::info("[GP][$rid] Table names", [
                'cuotas' => $c,
                'estudiante_programa' => $ep,
                'prospectos' => $p,
                'programas' => $pr
            ]);

            $query = DB::table("$c as c")
                ->join("$ep as ep", 'ep.id', '=', 'c.estudiante_programa_id')
                ->join("$p as s", 's.id', '=', 'ep.prospecto_id')
                ->join("$pr as pr", 'pr.id', '=', 'ep.programa_id')
                ->where('c.estado', 'pendiente')
                ->where('c.fecha_vencimiento', '<', $now)
                ->when($programaId, fn($q2) => $q2->where('ep.programa_id', $programaId))
                ->when($q !== '', function($q2) use ($q) {
                    $q2->where(function($w) use ($q) {
                        $w->where('s.nombre_completo', 'like', "%{$q}%")
                          ->orWhere('s.carnet', 'like', "%{$q}%")
                          ->orWhere('ep.id', $q);
                    });
                });

            // IMPORTANTE: El bucket filtering se hará después de la paginación
            // porque necesitamos calcular días de atraso por fila

            // Filtrar por bucket si no es 'all'
            if ($bucket !== 'all') {
                $query->where(function($q) use ($bucket, $now) {
                    switch ($bucket) {
                        case 'b1':
                            // 0-5 días de atraso
                            $q->whereRaw("c.fecha_vencimiento >= ?", [$now->copy()->subDays(5)]);
                            break;
                        case 'b2':
                            // 6-10 días de atraso
                            $q->whereRaw("c.fecha_vencimiento >= ?", [$now->copy()->subDays(10)])
                              ->whereRaw("c.fecha_vencimiento < ?", [$now->copy()->subDays(5)]);
                            break;
                        case 'b3':
                            // 11-30 días de atraso
                            $q->whereRaw("c.fecha_vencimiento >= ?", [$now->copy()->subDays(30)])
                              ->whereRaw("c.fecha_vencimiento < ?", [$now->copy()->subDays(10)]);
                            break;
                        case 'b4':
                            // Más de 30 días de atraso
                            $q->whereRaw("c.fecha_vencimiento < ?", [$now->copy()->subDays(30)]);
                            break;
                    }
                });
            }

            $query->groupBy('ep.id','s.id','s.nombre_completo','s.carnet','pr.nombre')
                ->selectRaw('
                    ep.id as ep_id,
                    s.id as student_id,
                    s.nombre_completo as student_name,
                    s.carnet as carnet,
                    pr.nombre as programa,
                    SUM(c.monto) as total_debt,
                    MIN(c.fecha_vencimiento) as oldest_due,
                    COUNT(*) as cuotas_pendientes
                ')
                ->orderBy('oldest_due', 'asc');

            Log::info("[GP][$rid] Query built successfully");

            // Paginamos
            $paginated = $query->paginate($perPage);

            Log::info("[GP][$rid] Query executed successfully", [
                'total_results' => $paginated->total(),
                'current_page' => $paginated->currentPage()
            ]);

            // Traemos regla de mora una vez
            $paymentRule = PaymentRule::first();

            // Transformamos a shape que pide el front
            $items = collect($paginated->items())->map(function($row) use ($now, $paymentRule, $rid) {
                $daysLate = Carbon::parse($row->oldest_due)->diffInDays($now);
                $lateMonths = (int) ceil($daysLate / 30);

                // Bucket
                $bucket = 'B1';
                if ($daysLate <= 5)       $bucket = 'B1';
                elseif ($daysLate <= 10)  $bucket = 'B2';
                elseif ($daysLate <= 30)  $bucket = 'B3';
                else                      $bucket = 'B4';

                // Servicios bloqueados (para pintar estado)
                $services = $this->obtenerServiciosBloqueados($daysLate);
                $status = count($services) > 0 ? 'bloqueado' : 'activo';

                return [
                    'id'            => (int) $row->ep_id,      // ← usamos EP como ID de fila
                    'studentId'     => (int) $row->student_id,
                    'name'          => (string) $row->student_name,
                    'program'       => (string) $row->programa,
                    'totalDebt'     => (float) $row->total_debt,   // (sin mora; si quieres con mora, ver nota abajo)
                    'lateMonths'    => $lateMonths,
                    'daysLate'      => $daysLate,
                    'bucket'        => $bucket,
                    'status'        => $status,
                    'lastContact'   => null, // Ya no usamos collection_logs
                    'promiseDate'   => null, // Ya no usamos collection_logs
                ];
            })->values();

            Log::info("[GP][$rid] Response prepared successfully", [
                'items_count' => $items->count()
            ]);

            // Respuesta con meta de paginación
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
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return response()->json([
                'error' => 'Error interno del servidor',
                'message' => 'Error al obtener los pagos atrasados',
                'request_id' => $rid,
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * GET /collections/students/{epId}/snapshot
     * Detalle del EP (para el modal): cuotas pendientes, pagos recientes, etc.
     * SIN COLLECTION_LOGS
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
            $ep = EstudiantePrograma::with(['prospecto:id,nombre_completo,carnet', 'programa:id,nombre'])
                ->findOrFail($epId);

            $pending = CuotaProgramaEstudiante::query()
                ->where('estudiante_programa_id', $epId)
                ->where('estado', 'pendiente')
                ->orderBy('fecha_vencimiento')
                ->get(['id','concepto','monto','fecha_vencimiento']);

            $payments = KardexPago::query()
                ->where('estudiante_programa_id', $epId)
                ->where('estado_pago', 'aprobado')
                ->orderByDesc('fecha_pago')
                ->limit(10)->get(['id','monto_pagado','fecha_pago','metodo_pago']);

            // Ya no incluimos contact_history porque no usamos collection_logs

            Log::info("[GP][$rid] studentSnapshot data retrieved successfully", [
                'pending_installments' => $pending->count(),
                'recent_payments' => $payments->count()
            ]);

            return response()->json([
                'student' => [
                    'epId'    => $ep->id,
                    'id'      => $ep->prospecto->id,
                    'name'    => $ep->prospecto->nombre_completo,
                    'carnet'  => $ep->prospecto->carnet,
                    'program' => $ep->programa->nombre ?? 'N/A',
                ],
                'pending_installments' => $pending->map(function($c) use ($now) {
                    $days = Carbon::parse($c->fecha_vencimiento)->diffInDays($now);
                    return [
                        'id' => (int) $c->id,
                        'concepto' => (string) $c->concepto,
                        'monto' => (float) $c->monto,
                        'fecha_vencimiento' => (string) $c->fecha_vencimiento,
                        'days_late' => $days,
                    ];
                }),
                'recent_payments' => $payments,
                'contact_history' => [], // Vacío porque no usamos collection_logs
                'request_id' => $rid,
            ]);

        } catch (\Exception $e) {
            Log::error("[GP][$rid] Error in studentSnapshot", [
                'error' => $e->getMessage(),
                'epId' => $epId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Error interno del servidor',
                'message' => 'Error al obtener el snapshot del estudiante',
                'request_id' => $rid,
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
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
}
