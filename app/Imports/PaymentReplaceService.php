<?php

namespace App\Services;

use App\Models\KardexPago;
use App\Models\CuotaProgramaEstudiante;
use App\Models\ReconciliationRecord;
use App\Models\PrecioPrograma;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PaymentReplaceService
{
    /**
     * Purga TODO lo anterior (kardex, conciliaciones, cuotas) para los programas del carnet
     * y reconstruye las cuotas conforme a la duración del programa.
     *
     * @param callable $resolverProgramas  fn(string $carnet, $row|null): Collection<obj ep>
     * @param string   $carnetNormalizado
     * @param Collection $pagosEstudiante  Filas Excel del carnet (para inferencias)
     * @param int      $uploaderId
     */
    public function purgeAndRebuildForCarnet(
        callable $resolverProgramas,
        string $carnetNormalizado,
        Collection $pagosEstudiante,
        int $uploaderId
    ): void {
        // 1) Resolver programas (puede crear si no existen)
        $programas = $resolverProgramas($carnetNormalizado, $pagosEstudiante->first());

        if ($programas->isEmpty()) {
            Log::warning("🔎 [Replace] No se encontraron/crearon programas para carnet {$carnetNormalizado}. Se omite purge/rebuild.");
            return;
        }

        // 2) Inferencias a partir del Excel (por si faltan datos en estudiante_programa):
        $fechaInicioInferida = $this->inferFechaInicio($pagosEstudiante);
        $mensualidadInferida = $this->inferMensualidad($pagosEstudiante);
        $inscripcionInferida = $this->inferInscripcion($pagosEstudiante); // 👈 NUEVO

        // 3) Por cada EP hacer purge + rebuild
        foreach ($programas as $ep) {
            DB::transaction(function () use ($ep, $uploaderId, $carnetNormalizado, $fechaInicioInferida, $mensualidadInferida, $inscripcionInferida) {
                Log::info("🧹 [Replace] PURGE EP {$ep->estudiante_programa_id} ({$ep->nombre_programa})");

                // 3.1 Borrar conciliaciones de kardex
                $kardexIds = DB::table('kardex_pagos')
                    ->where('estudiante_programa_id', $ep->estudiante_programa_id)
                    ->pluck('id');

                if ($kardexIds->isNotEmpty()) {
                    ReconciliationRecord::whereIn('kardex_pago_id', $kardexIds)->delete();
                    Log::info("   • conciliaciones eliminadas", ['count' => $kardexIds->count()]);
                }

                // 3.2 Borrar kardex
                $deletedK = KardexPago::where('estudiante_programa_id', $ep->estudiante_programa_id)->delete();
                Log::info("   • kardex eliminados", ['count' => $deletedK]);

                // 3.3 Borrar cuotas
                $deletedC = CuotaProgramaEstudiante::where('estudiante_programa_id', $ep->estudiante_programa_id)->delete();
                Log::info("   • cuotas eliminadas", ['count' => $deletedC]);

                // 3.4 Reconstruir cuotas según duración del programa
                $this->rebuildCuotasFromProgram($ep->estudiante_programa_id, $fechaInicioInferida, $mensualidadInferida, $inscripcionInferida);

                Log::info("✅ [Replace] EP {$ep->estudiante_programa_id} listo para nueva importación", [
                    'carnet' => $carnetNormalizado,
                    'programa' => $ep->nombre_programa,
                ]);
            });
        }
    }

    /**
     * Reconstruye cuotas exactamente con la duración del programa.
     * Usa estudiante_programa; si faltan datos, complementa con precio_programa o inferencias.
     */
    private function rebuildCuotasFromProgram(int $epId, ?Carbon $fechaInicioInferida, ?float $mensualidadInferida, ?float $inscripcionInferida = null): void
    {
        $ep = DB::table('estudiante_programa')->where('id', $epId)->first();
        if (!$ep) {
            Log::warning("⚠️ [Replace] EP no encontrado para reconstrucción", ['ep_id' => $epId]);
            return;
        }

        // Base de datos del EP
        $duracionMeses  = (int)($ep->duracion_meses ?? 0);
        $cuotaMensual   = (float)($ep->cuota_mensual ?? 0);
        $fechaInicio    = $ep->fecha_inicio ? Carbon::parse($ep->fecha_inicio) : null;

        // Complementar con precio_programa si falta
        $pp = null;
        if ($duracionMeses <= 0 || $cuotaMensual <= 0) {
            $pp = PrecioPrograma::where('programa_id', $ep->programa_id)->first();
            if ($pp) {
                $duracionMeses = $duracionMeses > 0 ? $duracionMeses : (int)($ep->duracion_meses ?? $pp->meses ?? 12);
                $cuotaMensual  = $cuotaMensual  > 0 ? $cuotaMensual  : (float)($pp->cuota_mensual ?? 0);
            }
        }

        // Si sigue faltando, usar inferencias de Excel
        if ($duracionMeses <= 0)  { $duracionMeses = 12; } // fallback razonable
        if ($cuotaMensual  <= 0 && $mensualidadInferida) { $cuotaMensual = $mensualidadInferida; }
        if (!$fechaInicio && $fechaInicioInferida) { $fechaInicio = $fechaInicioInferida; }
        if (!$fechaInicio) { $fechaInicio = now(); }

        // === 👇 INSCRIPCIÓN (CUOTA 0) =========================================
        $inscripcion = null;

        // 1) Si el EP tiene campo inscripcion (si existe en tu esquema) úsalo
        if (property_exists($ep, 'inscripcion') && $ep->inscripcion > 0) {
            $inscripcion = (float)$ep->inscripcion;
        }

        // 2) Si no, usa PrecioPrograma
        if ($inscripcion === null) {
            if (!$pp) {
                $pp = PrecioPrograma::where('programa_id', $ep->programa_id)->first();
            }
            if ($pp && $pp->inscripcion > 0) {
                $inscripcion = (float)$pp->inscripcion;
            }
        }

        // 3) Si no, usa inferencia del Excel (si vino)
        if ($inscripcion === null && $inscripcionInferida && $inscripcionInferida > 0) {
            $inscripcion = $inscripcionInferida;
        }
        // ======================================================================

        Log::info("🔧 [Replace] Rebuild cuotas", [
            'ep_id' => $epId,
            'duracion_meses' => $duracionMeses,
            'cuota_mensual' => $cuotaMensual,
            'fecha_inicio' => $fechaInicio->toDateString(),
            'inscripcion' => $inscripcion,
        ]);

        // Construcción de la malla de cuotas
        $rows = [];

        // 👇 Si hay inscripción > 0, crear CUOTA 0
        if ($inscripcion !== null && $inscripcion > 0) {
            $rows[] = [
                'estudiante_programa_id' => $epId,
                'numero_cuota'           => 0,
                'fecha_vencimiento'      => $fechaInicio->toDateString(),
                'monto'                  => $inscripcion,
                'estado'                 => 'pendiente',
                'created_at'             => now(),
                'updated_at'             => now(),
            ];
        }

        // Cuotas 1..N como siempre
        for ($i = 1; $i <= $duracionMeses; $i++) {
            $rows[] = [
                'estudiante_programa_id' => $epId,
                'numero_cuota'           => $i,
                'fecha_vencimiento'      => $fechaInicio->copy()->addMonths($i - 1)->toDateString(),
                'monto'                  => $cuotaMensual,
                'estado'                 => 'pendiente',
                'created_at'             => now(),
                'updated_at'             => now(),
            ];
        }

        DB::table('cuotas_programa_estudiante')->insert($rows);

        Log::info("✅ [Replace] Malla reconstruida (incluye cuota 0 si aplica)", [
            'ep_id' => $epId,
            'cuota_mensual' => $cuotaMensual,
            'inscripcion' => $inscripcion
        ]);
    }

    /** Toma la fecha mínima entre las fechas del Excel para usarla como potencial fecha_inicio. */
    private function inferFechaInicio(Collection $pagos): ?Carbon
    {
        $fechas = $pagos->map(function ($r) {
            $v = $r['fecha_pago'] ?? null;
            if (!$v) return null;
            try { return $v instanceof Carbon ? $v : Carbon::parse($v); } catch (\Throwable $e) { return null; }
        })->filter();

        return $fechas->min() ?: null;
    }

    /** Usa la mensualidad_aprobada más frecuente (moda) como inferencia. */
    private function inferMensualidad(Collection $pagos): ?float
    {
        $vals = $pagos->map(function ($r) {
            $m = $r['mensualidad_aprobada'] ?? null;
            if ($m === null || $m === '') return null;
            $m = is_string($m) ? floatval(preg_replace('/[Q$,\s]/', '', $m)) : (float)$m;
            return $m > 0 ? $m : null;
        })->filter();

        if ($vals->isEmpty()) return null;

        // moda simple
        $freq = [];
        foreach ($vals as $v) { $freq[(string)$v] = ($freq[(string)$v] ?? 0) + 1; }
        arsort($freq);
        $moda = (float)array_key_first($freq);
        return $moda ?: null;
    }

    /** Usa concepto 'inscrip' o cercanía de monto para inferir inscripción (moda) */
    private function inferInscripcion(Collection $pagos): ?float
    {
        // 1) Prioriza pagos cuyo concepto sugiera inscripción
        $candidatosConcepto = $pagos->filter(function ($r) {
            $c = strtolower((string)($r['concepto'] ?? ''));
            return $c !== '' && str_contains($c, 'inscrip'); // inscripcion / inscripción / etc.
        })->map(function ($r) {
            $m = $r['monto'] ?? null;
            if ($m === null || $m === '') return null;
            $m = is_string($m) ? floatval(preg_replace('/[Q$,\s]/', '', $m)) : (float)$m;
            return $m > 0 ? $m : null;
        })->filter();

        if ($candidatosConcepto->isNotEmpty()) {
            // moda simple
            $freq = [];
            foreach ($candidatosConcepto as $v) { $freq[(string)$v] = ($freq[(string)$v] ?? 0) + 1; }
            arsort($freq);
            return (float)array_key_first($freq);
        }

        // 2) Si no hay concepto, intenta detectar un "monto chico único" que no sea mensualidad (heurística suave)
        // (opcional; lo dejamos conservador)
        return null;
    }
}
