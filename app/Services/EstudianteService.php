<?php

namespace App\Services;

use App\Models\Prospecto;
use App\Models\EstudiantePrograma;
use App\Models\Programa;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class EstudianteService
{
    private const DEFAULT_PROGRAM_ABBR = 'TEMP';
    private const DEFAULT_PROGRAM_NAME = 'Programa Pendiente';
    private const DUMMY_BIRTH_DATE = '2000-01-01';
    private const DEFAULT_MODALIDAD = 'sincronica';

    private const PROGRAM_ALIASES = [
        'MMKD'  => 'MMK',
        'MMK'   => 'MMK',
        'MRRHH' => 'MHTM',
    ];

    /**
     * Crear o actualizar estudiante desde datos de Excel de pago histórico
     */
    public function syncEstudianteFromPaymentRow(array $row, int $uploaderId): ?object
    {
        $carnet = $this->normalizeCarnet($row['carnet'] ?? null);

        Log::info("🔧 Sincronizando estudiante desde pago histórico", [
            'carnet' => $carnet,
            'nombre' => $row['nombre_estudiante'] ?? 'N/A'
        ]);

        // 1. Crear o actualizar prospecto
        $prospecto = $this->findOrCreateProspecto($carnet, $row, $uploaderId);

        // 2. Obtener programa (crear TEMP si plan_estudios no existe)
        $programa = $this->obtenerPrograma($row['plan_estudios'] ?? null);

        // 3. Crear o actualizar estudiante_programa
        $estudiantePrograma = $this->findOrCreateEstudiantePrograma(
            $prospecto,
            $programa,
            $row,
            $uploaderId
        );

        // 4. Generar cuotas si no existen
        $this->generarCuotasSiNoExisten($estudiantePrograma, $row, $uploaderId);

        // 5. Retornar datos del programa para continuar procesamiento
        return DB::table('prospectos as p')
            ->select(
                'p.id as prospecto_id',
                'p.carnet',
                'p.nombre_completo',
                'ep.id as estudiante_programa_id',
                'ep.programa_id',
                'ep.created_at as fecha_inscripcion',
                'prog.nombre_del_programa as nombre_programa',
                'prog.abreviatura as programa_abreviatura',
                'prog.activo as programa_activo'
            )
            ->join('estudiante_programa as ep', 'p.id', '=', 'ep.prospecto_id')
            ->leftJoin('tb_programas as prog', 'ep.programa_id', '=', 'prog.id')
            ->where('p.id', $prospecto->id)
            ->where('ep.id', $estudiantePrograma->id)
            ->first();
    }

    /**
     * Actualizar programa TEMP a programa real
     */
    public function actualizarProgramaTempAReal(int $estudianteProgramaId, string $planEstudios, int $uploaderId): bool
    {
        $codigoNormalizado = $this->normalizeProgramaCodigo($planEstudios);

        // 🛑 SKIP: No actualizar si el plan de estudios es TEMP
        if (!$codigoNormalizado || strtoupper($codigoNormalizado) === self::DEFAULT_PROGRAM_ABBR) {
            Log::info("⏭️ Saltando actualización: plan_estudios inválido o es TEMP", [
                'plan_estudios' => $planEstudios,
                'codigo_normalizado' => $codigoNormalizado
            ]);
            return false;
        }

        $programaReal = Programa::whereRaw('UPPER(abreviatura) = ?', [$codigoNormalizado])->first();

        if (!$programaReal) {
            Log::warning("⚠️ No se encontró programa real para código", [
                'plan_estudios' => $planEstudios,
                'codigo_normalizado' => $codigoNormalizado
            ]);
            return false;
        }

        // 🛑 SKIP: No actualizar si el programa encontrado también es TEMP
        if (strtoupper($programaReal->abreviatura) === self::DEFAULT_PROGRAM_ABBR) {
            Log::info("⏭️ Saltando actualización: programa destino también es TEMP", [
                'plan_estudios' => $planEstudios,
                'programa_id' => $programaReal->id
            ]);
            return false;
        }

        DB::table('estudiante_programa')
            ->where('id', $estudianteProgramaId)
            ->update([
                'programa_id' => $programaReal->id,
                'updated_at' => now()
            ]);

        Log::info("🔄 Programa TEMP actualizado a real", [
            'estudiante_programa_id' => $estudianteProgramaId,
            'programa_id' => $programaReal->id,
            'programa_nombre' => $programaReal->nombre_del_programa
        ]);

        return true;
    }

    /**
     * Generar cuotas desde datos del Excel
     */
    public function generarCuotasSiNoExisten(EstudiantePrograma $estudiantePrograma, array $row, int $uploaderId): int
    {
        // Verificar si ya tiene cuotas
        $cuotasExistentes = DB::table('cuotas_programa_estudiante')
            ->where('estudiante_programa_id', $estudiantePrograma->id)
            ->count();

        if ($cuotasExistentes > 0) {
            Log::debug("📋 Estudiante ya tiene cuotas", [
                'estudiante_programa_id' => $estudiantePrograma->id,
                'cantidad' => $cuotasExistentes
            ]);
            return 0;
        }

        $mensualidad = $this->limpiarMonto($row['mensualidad_aprobada'] ?? $row['mensualidad'] ?? $row['monto'] ?? 0);

        if ($mensualidad <= 0) {
            Log::warning("⚠️ No se pueden generar cuotas: mensualidad = 0", [
                'estudiante_programa_id' => $estudiantePrograma->id
            ]);
            return 0;
        }

        // Calcular número de cuotas (por defecto 12 si no se especifica)
        $numCuotas = (int)($row['numero_de_cuotas'] ?? 12);
        if ($numCuotas <= 0) {
            $numCuotas = 12;
        }

        // Fecha de inicio basada en mes_inicio o fecha_pago
        $fechaInicio = $this->parseFechaInicio($row);

        Log::info("📊 Generando cuotas para programa", [
            'estudiante_programa_id' => $estudiantePrograma->id,
            'mensualidad' => $mensualidad,
            'num_cuotas' => $numCuotas,
            'fecha_inicio' => $fechaInicio->toDateString()
        ]);

        $cuotas = [];
        for ($i = 1; $i <= $numCuotas; $i++) {
            $fechaVenc = $fechaInicio->copy()->addMonths($i - 1);

            $cuotaBase = [
                'estudiante_programa_id' => $estudiantePrograma->id,
                'numero_cuota' => $i,
                'fecha_vencimiento' => $fechaVenc->toDateString(),
                'monto' => $mensualidad,
                'estado' => 'pendiente',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('cuotas_programa_estudiante', 'created_by')) {
                $cuotaBase['created_by'] = $uploaderId;
            }

            $cuotas[] = $cuotaBase;
        }

        DB::table('cuotas_programa_estudiante')->insert($cuotas);

        Log::info("✅ Cuotas generadas exitosamente", [
            'estudiante_programa_id' => $estudiantePrograma->id,
            'cantidad' => count($cuotas)
        ]);

        return count($cuotas);
    }

    // ========== MÉTODOS PRIVADOS ==========

    private function findOrCreateProspecto(string $carnet, array $row, int $uploaderId): Prospecto
    {
        $nombreEstudiante = trim($row['nombre_estudiante'] ?? 'SIN NOMBRE');
        $telefono = $row['telefono'] ?? '00000000';
        $correo = $row['email'] ?? $row['correo'] ?? $this->defaultEmail($carnet);
        $genero = $row['genero'] ?? 'Masculino';
        $pais = $row['pais'] ?? 'Guatemala';

        $prospecto = Prospecto::where('carnet', $carnet)->first();

        if ($prospecto) {
            Log::debug("📋 Prospecto existente encontrado", [
                'carnet' => $carnet,
                'prospecto_id' => $prospecto->id
            ]);
            return $prospecto;
        }

        // Crear nuevo prospecto con valores por defecto seguros
        $prospecto = Prospecto::create([
            'carnet' => $carnet,
            'nombre_completo' => $nombreEstudiante,
            'correo_electronico' => $correo,
            'telefono' => $telefono,
            'fecha' => now()->toDateString(),
            'activo' => true,
            'status' => 'Inscrito',
            'genero' => $genero,
            'pais_origen' => $pais,
            'created_by' => $uploaderId,
        ]);

        Log::info("✅ Prospecto creado desde pago histórico", [
            'carnet' => $carnet,
            'prospecto_id' => $prospecto->id,
            'nombre' => $nombreEstudiante,
            'genero' => $genero,
            'pais' => $pais
        ]);

        return $prospecto;
    }

    private function findOrCreateEstudiantePrograma(
        Prospecto $prospecto,
        Programa $programa,
        array $row,
        int $uploaderId
    ): EstudiantePrograma {
        $estudiantePrograma = EstudiantePrograma::where('prospecto_id', $prospecto->id)
            ->where('programa_id', $programa->id)
            ->first();

        if ($estudiantePrograma) {
            Log::debug("📋 Relación estudiante-programa existente", [
                'estudiante_programa_id' => $estudiantePrograma->id
            ]);
            return $estudiantePrograma;
        }

        // Crear nueva relación
        $fechaInicio = $this->parseFechaInicio($row);
        $numCuotas = (int)($row['numero_de_cuotas'] ?? 12);
        if ($numCuotas <= 0) {
            $numCuotas = 12;
        }
        $fechaFin = $fechaInicio->copy()->addMonths($numCuotas);
        $mensualidad = $this->limpiarMonto($row['mensualidad_aprobada'] ?? $row['mensualidad'] ?? $row['monto'] ?? 0);

        $estudiantePrograma = EstudiantePrograma::create([
            'prospecto_id' => $prospecto->id,
            'programa_id' => $programa->id,
            'inscripcion' => 0,
            'inversion_total' => $mensualidad * $numCuotas,
            'fecha_inicio' => $fechaInicio->toDateString(),
            'fecha_fin' => $fechaFin->toDateString(),
            'cuota_mensual' => $mensualidad,
            'duracion_meses' => $numCuotas,
            'created_by' => $uploaderId,
        ]);

        Log::info("✅ Relación estudiante-programa creada", [
            'estudiante_programa_id' => $estudiantePrograma->id,
            'prospecto_id' => $prospecto->id,
            'programa_id' => $programa->id
        ]);

        return $estudiantePrograma;
    }

    private function obtenerPrograma(?string $claveProg): Programa
    {
        $abrev = $this->normalizeProgramaCodigo($claveProg);

        if ($abrev) {
            $programa = Programa::whereRaw('UPPER(abreviatura) = ?', [$abrev])->first();

            if (!$programa) {
                $programa = Programa::whereRaw('UPPER(abreviatura) LIKE ?', [$abrev . '%'])->first();
            }

            if ($programa) {
                return $programa;
            }

            Log::warning("⚠️ Programa no encontrado, usando TEMP", [
                'codigo' => $abrev
            ]);
        }

        return Programa::firstOrCreate(
            ['abreviatura' => self::DEFAULT_PROGRAM_ABBR],
            ['nombre_del_programa' => self::DEFAULT_PROGRAM_NAME, 'activo' => true]
        );
    }

    private function parseFechaInicio(array $row): Carbon
    {
        if (!empty($row['mes_pago']) || !empty($row['mes']) || !empty($row['año']) || !empty($row['ano'])) {
            $mes = $this->normalizeMonth($row['mes_pago'] ?? $row['mes'] ?? null);
            $anio = $this->normalizeYear($row['año'] ?? $row['ano'] ?? null);

            if ($mes && $anio) {
                return Carbon::create($anio, $mes, 1)->startOfMonth();
            }
        }

        // Prioridad: mes_inicio > fecha_pago > fecha predeterminada (2020-04-01)
        if (!empty($row['mes_inicio'])) {
            try {
                return Carbon::parse($row['mes_inicio'])->startOfMonth();
            } catch (\Exception $e) {
                Log::warning("⚠️ Error parseando mes_inicio", ['valor' => $row['mes_inicio']]);
            }
        }

        if (!empty($row['fecha_pago'])) {
            try {
                // Usar la primera fecha de pago como fecha de inicio
                return Carbon::parse($row['fecha_pago'])->startOfMonth();
            } catch (\Exception $e) {
                Log::warning("⚠️ Error parseando fecha_pago", ['valor' => $row['fecha_pago']]);
            }
        }

        // Si no hay fecha de inicio ni fecha de pago, usar fecha predeterminada
        // en lugar de now() para mantener consistencia en migraciones históricas
        return Carbon::parse('2020-04-01')->startOfMonth();
    }

    private function normalizeMonth($mes): ?int
    {
        if (is_null($mes)) {
            return null;
        }

        if (is_numeric($mes)) {
            $numero = (int) $mes;
            if ($numero >= 1 && $numero <= 12) {
                return $numero;
            }
        }

        $mes = strtolower(trim((string) $mes));
        $mes = strtr($mes, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']);
        $mes = preg_replace('/[^a-z]/', '', $mes);

        $map = [
            'enero' => 1,
            'ene' => 1,
            'january' => 1,
            'jan' => 1,
            'febrero' => 2,
            'feb' => 2,
            'february' => 2,
            'marzo' => 3,
            'mar' => 3,
            'march' => 3,
            'abril' => 4,
            'abr' => 4,
            'april' => 4,
            'mayo' => 5,
            'may' => 5,
            'junio' => 6,
            'jun' => 6,
            'june' => 6,
            'julio' => 7,
            'jul' => 7,
            'july' => 7,
            'agosto' => 8,
            'ago' => 8,
            'august' => 8,
            'aug' => 8,
            'septiembre' => 9,
            'setiembre' => 9,
            'sep' => 9,
            'sept' => 9,
            'september' => 9,
            'octubre' => 10,
            'oct' => 10,
            'october' => 10,
            'noviembre' => 11,
            'nov' => 11,
            'november' => 11,
            'diciembre' => 12,
            'dic' => 12,
            'december' => 12,
            'dec' => 12,
        ];

        return $map[$mes] ?? null;
    }

    private function normalizeYear($anio): ?int
    {
        if (is_null($anio)) {
            return null;
        }

        if (is_numeric($anio)) {
            $numero = (int) $anio;
            if ($numero < 100) {
                $numero += $numero >= 50 ? 1900 : 2000;
            }

            if ($numero >= 1900 && $numero <= 2100) {
                return $numero;
            }
        }

        $anio = preg_replace('/[^0-9]/', '', (string) $anio);
        if ($anio === '') {
            return null;
        }

        $numero = (int) $anio;
        if ($numero < 100) {
            $numero += $numero >= 50 ? 1900 : 2000;
        }

        return ($numero >= 1900 && $numero <= 2100) ? $numero : null;
    }

    private function normalizeCarnet(?string $carnet): string
    {
        if (empty($carnet)) {
            return 'TEMP-' . Str::random(6);
        }
        return Str::upper(preg_replace('/\s+/', '', $carnet));
    }

    private function normalizeProgramaCodigo(?string $code): ?string
    {
        if (empty($code)) {
            return null;
        }
        $base = Str::upper(preg_replace('/[^A-Za-z]/', '', $code));
        return self::PROGRAM_ALIASES[$base] ?? $base;
    }

    private function limpiarMonto($v): float
    {
        if (is_null($v)) {
            return 0.0;
        }
        if (is_numeric($v)) {
            return (float) $v;
        }
        $clean = str_replace(['Q', '$', ',', ' '], '', trim($v));
        return (float) ($clean !== '' ? $clean : 0);
    }

    private function defaultEmail(string $carnet): string
    {
        return 'sin-correo-' . strtolower($carnet) . '@example.com';
    }
}
