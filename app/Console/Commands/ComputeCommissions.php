<?php
// app/Console/Commands/ComputeCommissions.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Prospecto;
use App\Models\EstudiantePrograma;
use App\Models\KardexPago;
use App\Models\CommissionConfig;
use App\Models\AdvisorCommissionRate;
use App\Models\Commission;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use App\Notifications\CommissionNotification;
use App\Notifications\CommissionTrendNotification;
class ComputeCommissions extends Command
{
    protected $signature = 'commissions:compute {--period=monthly}';
    protected $description = 'Calcula las comisiones de todos los asesores según configuración y prospectos/pagos.';

    public function handle()
    {
        $period = $this->option('period'); // monthly|quarterly
        $config = CommissionConfig::first();

        // 1) Definir rangos
        if ($period === 'quarterly') {
            $start = Carbon::now()->firstOfQuarter()->startOfDay();
            $end   = Carbon::now()->endOfQuarter()->endOfDay();
        } else {
            $start = Carbon::now()->startOfMonth()->startOfDay();
            $end   = Carbon::now()->endOfMonth()->endOfDay();
        }

        // 2) Para cada asesor (rol = 7)
        $asesores = User::where('rol',7)->get();

        foreach ($asesores as $u) {
            // a) Conversions = Prospectos asignados con status = 'Inscrito' en este periodo
            $conv = Prospecto::where('created_by',$u->id)
                        ->where('status','Inscrito')
                        ->whereBetween('created_at',[$start,$end])
                        ->count();

            // b) Income = suma de pagos en kardex_pagos de esos prospectos
            $income = DB::table('kardex_pagos as k')
                ->join('estudiante_programa as ep','k.estudiante_programa_id','=','ep.id')
                ->join('prospectos as p','ep.prospecto_id','=','p.id')
                ->where('p.created_by',$u->id)
                ->whereBetween('k.fecha_pago',[$start,$end])
                ->sum('k.monto_pagado');

            // c) Determinar rate
            $rate = $config->base_rate;
            // si supera umbral
            if ($conv >= $config->bonus_threshold) {
                $rate += $config->bonus_rate;
            }
            // ver si usamos rate personalizado
            if ($config->respect_custom_rates) {
                $custom = AdvisorCommissionRate::where('user_id',$u->id)
                             ->latest('effective_from')
                             ->first();
                if ($custom) {
                    $rate = $custom->rate;
                }
            }

            // d) Calcular comisión
            $commAmt = round($income * $rate / 100, 2);

            // e) Trend vs periodo anterior
            $prevStart = ($period==='quarterly') 
                ? $start->copy()->subQuarter() 
                : $start->copy()->subMonth();
            $prevEnd   = ($period==='quarterly') 
                ? $end->copy()->subQuarter() 
                : $end->copy()->subMonth();

            $prev = Commission::where('user_id',$u->id)
                   ->where('period_start',$prevStart->toDateString())
                   ->where('period_end',$prevEnd->toDateString())
                   ->first();
            $trend = $prev && $prev->commission_amount > 0
                   ? round((($commAmt - $prev->commission_amount) / $prev->commission_amount) * 100,2)
                   : null;

            // f) Guarda o actualiza
            Commission::updateOrCreate(
                [
                    'user_id'      => $u->id,
                    'period_start' => $start->toDateString(),
                    'period_end'   => $end->toDateString(),
                ],
                [
                    'conversions'       => $conv,
                    'income'            => $income,
                    'rate_used'         => $rate,
                    'commission_amount' => $commAmt,
                    'trend_percent'     => $trend,
                ]
            );

            $this->info("Asesor {$u->id} → conv={$conv}, income={$income}, rate={$rate}%, comm={$commAmt}");
        }

        $this->info("✅ Cómputo completado ({$period} {$start->toDateString()} – {$end->toDateString()}).");
    }
}
