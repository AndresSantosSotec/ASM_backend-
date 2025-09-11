<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Prospecto;
use App\Models\CuotaProgramaEstudiante;
use App\Models\KardexPago;
use App\Models\PaymentRule;
use Carbon\Carbon;

class AdminEstudiantePagosController extends Controller
{
    /** Lista de estudiantes con totales y balance (paginado + filtros) */
    public function index(Request $req)
    {
        $q          = trim((string) $req->get('q', ''));
        $programId  = $req->get('program_id');
        $status     = $req->get('status'); // 'al_dia'|'bloqueado'|null
        $perPage    = (int) ($req->get('per_page', 25));
        $withProg   = (bool) $req->get('with_programs', true);

        $base = Prospecto::query()
            ->withCount([
                'cuotas as cuotas_pagadas_count'   => fn($q) => $q->where('estado','pagado'),
                'cuotas as cuotas_pendientes_count'=> fn($q) => $q->where('estado','!=','pagado'),
            ])
            ->withSum('cuotas as monto_total_cuotas', 'monto')
            ->withSum('kardexPagos as monto_total_pagado', 'monto_pagado');

        if ($withProg) {
            $base->with(['programas.programa:id,nombre_del_programa']);
        }

        if ($q !== '') {
            $base->where(function($qq) use ($q) {
                $qq->where('nombre_completo','like',"%{$q}%")
                   ->orWhere('carnet','like',"%{$q}%")
                   ->orWhere('correo_electronico','like',"%{$q}%");
            });
        }

        if ($programId) {
            $base->whereHas('programas', fn($p) => $p->where('programa_id', $programId));
        }

        if ($status === 'al_dia') {
            $base->whereDoesntHave('cuotas', fn($c) => $c
                ->where('estado','!=','pagado')
                ->whereDate('fecha_vencimiento','<', now()));
        } elseif ($status === 'bloqueado') {
            $base->whereHas('cuotas', fn($c) => $c
                ->where('estado','!=','pagado')
                ->whereDate('fecha_vencimiento','<', now()));
        }

        $page = $base->orderBy('nombre_completo')->paginate($perPage);

        $data = $page->getCollection()->map(function($p) {
            $balance = (float) ($p->monto_total_cuotas - $p->monto_total_pagado);
            $bloqueado = $p->cuotas()
                ->where('estado','!=','pagado')
                ->whereDate('fecha_vencimiento','<', now())
                ->exists();

            return [
                'id'        => $p->id,
                'carnet'    => $p->carnet,
                'nombre'    => $p->nombre_completo,
                'programas' => $p->relationLoaded('programas')
                    ? $p->programas->map(fn($ep) => [
                        'id' => $ep->id,
                        'programa' => $ep->programa?->nombre_del_programa,
                    ])->values()
                    : [],
                'cuotas_pagadas'     => (int) $p->cuotas_pagadas_count,
                'cuotas_pendientes'  => (int) $p->cuotas_pendientes_count,
                'monto_total'        => (float) $p->monto_total_cuotas,
                'monto_pagado'       => (float) $p->monto_total_pagado,
                'balance'            => round($balance,2),
                'bloqueado'          => (bool) $bloqueado,
                'ultimo_pago'        => $p->kardexPagos()->max('fecha_pago'),
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
            ]
        ]);
    }

    /** Estado de cuenta detallado por estudiante */
    public function estadoCuenta($id)
    {
        $prospecto = Prospecto::with(['programas.programa'])->findOrFail($id);

        $rule = PaymentRule::first();
        $now  = Carbon::now();

        $cuotasPend = CuotaProgramaEstudiante::whereHas('estudiantePrograma', fn($q) =>
                $q->where('prospecto_id', $prospecto->id))
            ->where('estado','!=','pagado')
            ->orderBy('fecha_vencimiento')
            ->get();

        $pending = $cuotasPend->map(function($c) use ($rule,$now) {
            $venc = Carbon::parse($c->fecha_vencimiento);
            $monthsLate = 0;
            if ($venc->lt($now)) {
                $monthsLate = $venc->diffInMonths($now);
                if ($monthsLate === 0 && $venc->diffInDays($now) > 0) $monthsLate = 1;
            }
            $lateFeePerMonth = $rule?->late_fee_amount ?? 0;
            $late = 0.0;
            if ($monthsLate > 0 && $lateFeePerMonth > 0) {
                $late = $lateFeePerMonth <= 1
                    ? (float)$c->monto * $lateFeePerMonth * $monthsLate
                    : $lateFeePerMonth * $monthsLate;
            }

            return [
                'id'        => $c->id,
                'concept'   => "Cuota {$c->numero_cuota}",
                'amount'    => (float)$c->monto,
                'lateFee'   => round($late,2),
                'dueDate'   => $c->fecha_vencimiento?->toDateString(),
                'status'    => $venc->lt($now) ? 'vencido' : 'pendiente',
                'daysLate'  => $venc->lt($now) ? $venc->diffInDays($now) : null,
            ];
        })->values();

        $historial = KardexPago::whereHas('estudiantePrograma', fn($q) =>
                $q->where('prospecto_id', $prospecto->id))
            ->orderBy('fecha_pago','desc')
            ->get(['id','monto_pagado','fecha_pago','metodo_pago','numero_boleta'])
            ->map(fn($p) => [
                'id'          => $p->id,
                'concept'     => 'Pago de cuota',
                'amount'      => (float)$p->monto_pagado,
                'paymentDate' => $p->fecha_pago?->toDateTimeString(),
                'method'      => $p->metodo_pago,
                'reference'   => $p->numero_boleta,
            ])->values();

        $balance = [
            'isBlocked'     => $prospecto->isBlockedWithExceptions(),
            'warningLevel'  => $pending->where('status','vencido')->count() >= 2 ? 2 :
                               ($pending->where('status','vencido')->count() === 1 ? 1 : 0),
            'nextDueDate'   => $pending->where('status','pendiente')->min('dueDate'),
            'daysUntilDue'  => null,
            'latePayments'  => $pending->where('status','vencido')->count(),
        ];
        if ($balance['nextDueDate']) {
            $balance['daysUntilDue'] = Carbon::parse($balance['nextDueDate'])->diffInDays($now, false) * -1;
        }

        return response()->json([
            'student' => [
                'id'   => $prospecto->id,
                'name' => $prospecto->nombre_completo,
                'carnet' => $prospecto->carnet,
            ],
            'balance' => $balance,
            'pendingPayments' => $pending,
            'paymentHistory'  => $historial,
        ]);
    }

    /** Historial por separado (opcional, si quieres cargarlo lazy en el modal) */
    public function historial($id)
    {
        $prospecto = Prospecto::findOrFail($id);
        $historial = KardexPago::whereHas('estudiantePrograma', fn($q) =>
                $q->where('prospecto_id', $prospecto->id))
            ->orderBy('fecha_pago','desc')
            ->paginate(25);

        return response()->json($historial);
    }
}
