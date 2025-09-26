<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EstudiantePrograma;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FixCuotasEstudiantes extends Command
{
    protected $signature = 'fix:cuotas';
    protected $description = 'Genera cuotas faltantes para cada estudiante sin borrar ni afectar pagos existentes.';

    public function handle()
    {
        $this->info("🔍 Iniciando revisión de cuotas...");

        // Traemos todos los programas con cuotas ya cargadas
        $programas = EstudiantePrograma::with('cuotas')->get();
        $totalInsertadas = 0;

        foreach ($programas as $ep) {
            $numCuotas = $ep->duracion_meses;
            $fechaInicio = $ep->fecha_inicio;
            $cuotaMensual = $ep->cuota_mensual;

            if ($numCuotas <= 0 || $cuotaMensual <= 0) {
                $this->warn("⏭️ Saltando EP {$ep->id}: datos inválidos (duración: $numCuotas, mensualidad: $cuotaMensual)");
                continue;
            }

            // Revisar cuotas existentes
            $cuotasExistentes = $ep->cuotas->pluck('numero_cuota')->toArray();
            $faltantes = [];

            for ($i = 1; $i <= $numCuotas; $i++) {
                if (!in_array($i, $cuotasExistentes)) {
                    $faltantes[] = [
                        'estudiante_programa_id' => $ep->id,
                        'numero_cuota' => $i,
                        'fecha_vencimiento' => Carbon::parse($fechaInicio)->addMonths($i - 1)->toDateString(),
                        'monto' => $cuotaMensual,
                        'estado' => 'pendiente',
                        'created_at' => now(),
                        'updated_at' => now(),
                        'created_by' => 1, // cámbialo si usas auth
                    ];
                }
            }

            if (count($faltantes)) {
                DB::table('cuotas_programa_estudiante')->insert($faltantes);
                $this->info("✅ EP {$ep->id}: generadas ".count($faltantes)." cuotas faltantes");
                $totalInsertadas += count($faltantes);
            } else {
                $this->line("✔️ EP {$ep->id}: cuotas completas, no se hizo nada");
            }
        }

        $this->info("🎉 Proceso finalizado. Total cuotas nuevas insertadas: $totalInsertadas");
        return 0;
    }
}
