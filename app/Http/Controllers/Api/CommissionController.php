<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\CommissionConfig;
use App\Models\AdvisorCommissionRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommissionController extends Controller
{
    public function index(Request $request)
    {
        $q = Commission::with(['user','config']);

        if ($request->filled('user_id')) {
            $q->where('user_id', $request->user_id);
        }
        if ($request->filled('period_start')) {
            $q->where('period_start', '>=', $request->period_start);
        }
        if ($request->filled('period_end')) {
            $q->where('period_end', '<=', $request->period_end);
        }

        return response()->json($q->paginate(15));
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'user_id'      => 'required|integer|exists:users,id',
            'period_start' => 'required|date',
            'period_end'   => 'required|date|after_or_equal:period_start',
            'config_id'    => 'nullable|integer|exists:commission_configs,id',
        ]);

        // 1) Elige configuración (la pasada o la más reciente)
        $config = $v['config_id']
            ? CommissionConfig::find($v['config_id'])
            : CommissionConfig::latest()->first();

        // 2) Tasa base o personalizada
        $advisorRate = AdvisorCommissionRate::where('user_id', $v['user_id'])->first();
        $rate = ($config->respect_personalized && $advisorRate)
            ? $advisorRate->rate
            : $config->base_rate;

        // 3) Conversiones: Prospectos inscritos en el periodo
        $conversions = DB::table('estudiante_programa AS ep')
            ->join('prospectos AS p', 'p.id', 'ep.prospecto_id')
            ->where('p.created_by', $v['user_id'])
            ->where('p.status', 'Inscrito')
            ->whereBetween('ep.created_at', [$v['period_start'], $v['period_end']])
            ->count();

        // 4) Total de pagos (kardex_pagos) en el periodo
        $totalIncome = DB::table('kardex_pagos AS kp')
            ->join('estudiante_programa AS ep', 'ep.id', 'kp.estudiante_programa_id')
            ->join('prospectos AS p', 'p.id', 'ep.prospecto_id')
            ->where('p.created_by', $v['user_id'])
            ->whereBetween('kp.fecha_pago', [$v['period_start'], $v['period_end']])
            ->sum('kp.monto_pagado');

        // 5) Si supera umbral, añade bonus
        if ($conversions >= $config->bonus_threshold) {
            $rate += $config->bonus_rate;
        }

        // 6) Cálculo de comisión
        $commissionAmount = round($totalIncome * $rate / 100, 2);
        $difference = round($commissionAmount - ($totalIncome * $config->base_rate / 100), 2);

        $commission = Commission::create([
            'user_id'           => $v['user_id'],
            'period_start'      => $v['period_start'],
            'period_end'        => $v['period_end'],
            'total_income'      => $totalIncome,
            'conversions'       => $conversions,
            'rate_applied'      => $rate,
            'commission_amount' => $commissionAmount,
            'difference'        => $difference,
            'config_id'         => $config->id,
        ]);

        return response()->json($commission, 201);
    }

    public function show($id)
    {
        $commission = Commission::with(['user','config'])->findOrFail($id);
        return response()->json($commission);
    }

    public function update(Request $request, $id)
    {
        $commission = Commission::findOrFail($id);

        $v = $request->validate([
            'period_start' => 'sometimes|date',
            'period_end'   => 'sometimes|date|after_or_equal:period_start',
            // puedes permitir recalcular manualmente ...
        ]);

        $commission->update($v);
        return response()->json($commission);
    }

    public function destroy($id)
    {
        Commission::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
