<?php

namespace App\Imports;

use App\Models\EstudiantePrograma;
use App\Models\Programa;
use App\Models\Prospecto;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\Failure;
use Maatwebsite\Excel\Row;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class InscripcionesImport implements
    OnEachRow,
    SkipsEmptyRows,
    SkipsOnError,
    SkipsOnFailure,
    WithChunkReading,
    WithHeadingRow,
    WithValidation
{
    use SkipsErrors, SkipsFailures {
        onFailure as traitOnFailure;
    }

    private const DEFAULT_PROGRAM_ABBR = 'TEMP';
    private const DEFAULT_PROGRAM_NAME = 'Programa Pendiente';
    private const DUMMY_BIRTH_DATE = '2000-01-01';
    private const DEFAULT_MODALIDAD = 'sincronica';

    protected array $rowErrors = [];
    private ?string $importId = null;
    private int $rowCount = 0;
    private bool $skipErrorsMode = false;

    public function setImportId(string $importId): self
    {
        $this->importId = $importId;
        return $this;
    }

    public function skipErrors(): self
    {
        $this->skipErrorsMode = true;
        return $this;
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }

    public function getRowErrors(): array
    {
        return $this->rowErrors;
    }

    // 🔹 Obtiene el programa correcto o crea uno temporal
    protected function obtenerPrograma(?string $claveProg): Programa
    {
        $abrev = $this->normalizeProgramaCodigo($claveProg);

        if ($abrev) {
            $programa = Programa::whereRaw('upper(abreviatura) = ?', [$abrev])->first()
                ?? Programa::whereRaw('upper(abreviatura) LIKE ?', [$abrev . '%'])->first();

            if ($programa) {
                return $programa;
            }

            Log::warning("⚠️ Programa no encontrado para código: {$abrev}. Se usará TEMP.");
        } else {
            Log::warning("⚠️ Código de programa vacío, se usará TEMP.");
        }

        return Programa::firstOrCreate(
            ['abreviatura' => self::DEFAULT_PROGRAM_ABBR],
            ['nombre_del_programa' => self::DEFAULT_PROGRAM_NAME]
        );
    }

    // 🔹 Reglas de validación base
    public function rules(): array
    {
        return [
            '*.carnet' => 'required|string',
            '*.nombre' => 'required|string',
            '*.apellido' => 'required|string',
            '*.telefono' => 'nullable|string',
            '*.email' => 'nullable|email',
            '*.codigo_carrera' => 'nullable|string',
            '*.modalidad' => 'nullable|string',
            '*.dia' => 'nullable|string',
            '*.numero_de_cuotas' => 'nullable|integer|min:0',
            '*.valor_q_matricula_inscripcion' => 'nullable|numeric|min:0',
            '*.mensualidad' => 'nullable|numeric|min:0',
            '*.certificacion' => 'nullable|numeric|min:0',
            '*.valor_q_total_de_la_carrera' => 'nullable|numeric|min:0',
            '*.fecha_de_inscripcion' => 'nullable|date',
            '*.cumpleanos' => 'nullable|date',
        ];
    }

    // 🔹 Limpieza previa y preparación de datos
    public function prepareForValidation($data, $index)
    {
        // Correcciones comunes
        if (!isset($data['carnet']) && isset($data['carne'])) {
            $data['carnet'] = $data['carne'];
        }
        if (!isset($data['valor_q_matricula_inscripcion']) && isset($data['valor_q_matricula_insripcion'])) {
            $data['valor_q_matricula_inscripcion'] = $data['valor_q_matricula_insripcion'];
        }
        if (!isset($data['mensualidad']) && isset($data['mesualidad'])) {
            $data['mensualidad'] = $data['mesualidad'];
        }

        // Limpieza de montos
        $numericFields = [
            'numero_de_cuotas',
            'valor_q_matricula_inscripcion',
            'mensualidad',
            'certificacion',
            'valor_q_total_de_la_carrera'
        ];

        foreach ($numericFields as $field) {
            $data[$field] = isset($data[$field]) ? $this->limpiarMonto($data[$field]) : 0;
        }

        if (isset($data['telefono']) && !is_string($data['telefono'])) {
            $data['telefono'] = strval($data['telefono']);
        }

        if (empty($data['apellido'])) {
            $data['apellido'] = 'Desconocido';
        }

        // 🧹 Limpieza avanzada de correos electrónicos
        if (isset($data['email'])) {
            $data['email'] = trim(str_replace([" ", "\n", "\r", "\t"], '', $data['email']));
        }

        $carnetBase = $data['carnet'] ?? $data['carne'] ?? null;
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $data['email'] = $this->defaultEmail($this->normalizeCarnet($carnetBase));
        }

        // Fechas
        $data['fecha_de_inscripcion'] = $this->parseDate(
            $data['fecha_de_inscripcion'] ?? null,
            Carbon::now()->toDateString()
        );
        $data['cumpleanos'] = $this->parseDate(
            $data['cumpleanos'] ?? null,
            self::DUMMY_BIRTH_DATE
        );

        return $data;
    }

    public function chunkSize(): int
    {
        return 500;
    }

    // 🔹 Conversión flexible de fechas
    protected function parseDate($value, ?string $default = null): ?string
    {
        if (empty($value)) {
            return $default;
        }

        try {
            // 🔹 Caso 1: formato numérico (Excel date code)
            if (is_numeric($value)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject($value))->toDateString();
            }

            // 🔹 Limpieza general de caracteres erróneos
            $clean = trim(str_replace(['\\', '//', '--', '.', '  '], ['/', '/', '/', '/', ' '], $value));

            // 🔹 Caso 2: si contiene más de dos separadores o errores comunes, lo intentamos reparar
            if (preg_match('/^\d{1,2}\/{1,2}\d{1,2}\/\d{2,4}$/', $clean)) {
                // Normalizar fechas tipo 4//04/1972 → 4/04/1972
                $clean = preg_replace('/\/+/', '/', $clean);
                return Carbon::createFromFormat('d/m/Y', $clean)->toDateString();
            }

            // 🔹 Caso 3: si parece tener un mes fuera de rango (10/101/1979 → 10/10/1979)
            if (preg_match('/^(\d{1,2})\/(\d{2,})\/(\d{4})$/', $clean, $m)) {
                $dia = $m[1];
                $mes = substr($m[2], 0, 2); // toma solo los primeros dos dígitos
                $anio = $m[3];
                if ((int)$mes >= 1 && (int)$mes <= 12) {
                    return Carbon::createFromFormat('d/m/Y', "$dia/$mes/$anio")->toDateString();
                }
            }

            // 🔹 Último intento de parseo flexible
            return Carbon::parse($clean)->toDateString();
        } catch (\Exception $e) {
            Log::warning("⚠️ Fecha inválida (intentando reparar): {$value} → {$e->getMessage()}");
            return $default;
        }
    }


    protected function limpiarMonto($v): float
    {
        if (is_null($v)) return 0.0;
        if (is_numeric($v)) return (float)$v;
        $clean = str_replace(['Q', '$', ',', ' '], '', trim($v));
        return (float)($clean !== '' ? $clean : 0);
    }

    protected function normalizeCarnet(?string $carnet): string
    {
        return empty($carnet) ? 'TEMP-' . Str::random(6) : Str::upper(preg_replace('/\s+/', '', $carnet));
    }

    protected function sanitizeTelefono(?string $telefono): string
    {
        $digits = preg_replace('/\D+/', '', $telefono ?? '');
        return $digits !== '' ? $digits : '00000000';
    }

    protected function sanitizeDiaEstudio(?string $dia): ?string
    {
        return $dia ? Str::limit(trim($dia), 20, '') : null;
    }

    // 🔹 Normaliza códigos de programas y aplica alias extendido
    protected function normalizeProgramaCodigo(?string $code): ?string
    {
        if (empty($code)) return null;

        $clean = Str::upper(trim($code));
        $base = preg_replace('/[^A-Z0-9]/', '', $clean);
        $base = preg_replace('/\d+$/', '', $base);

        $aliases = [
            'MMKD'  => 'MMK',
            'MMK'   => 'MMK',
            'MRRHH' => 'MHTM',
            'BBAI'  => 'BBA',
            'BBACM' => 'BBA CM',
            'BBABF' => 'BBA BF',
            'MFM'   => 'MFIN',
            'MDM'   => 'MDM',
            'MDGP'  => 'MDGP',
            'MHHRR' => 'MHTM',
            'MGP'   => 'MGP',
            'DBA'   => 'DBA',
        ];

        return $aliases[$base] ?? $base;
    }

    protected function normalizeModalidad(?string $modalidad): string
    {
        if (empty($modalidad)) return self::DEFAULT_MODALIDAD;
        $m = strtolower(trim($modalidad));

        return match (true) {
            str_contains($m, 'elearn'),
            str_contains($m, 'online'),
            str_contains($m, 'virtual') => 'asincronica',
            str_contains($m, 'sincro'),
            str_contains($m, 'presencial') => 'sincronica',
            str_contains($m, 'diplomado') => 'diplomado',
            default => $m
        };
    }

    protected function defaultEmail(string $carnet): string
    {
        return 'sin-email-' . Str::slug($carnet) . '@example.com';
    }

    protected function addRowError(Row $row, \Throwable $e): void
    {
        $this->rowErrors[] = [
            'row' => $row->getIndex(),
            'error' => $e->getMessage(),
            'values' => $row->toArray(),
        ];
        $prefix = $this->importId ? "[Importación {$this->importId}]" : '';
        Log::error("❌ {$prefix} Error procesando fila {$row->getIndex()}: {$e->getMessage()}");
    }

    // 🔹 Procesa cada fila
    public function onRow(Row $row)
    {
        $this->rowCount++;
        $prefix = $this->importId ? "[Importación {$this->importId}]" : '';
        Log::info("🔍 {$prefix} Procesando fila #{$row->getIndex()}", $row->toArray());

        $d = array_map(fn($v) => is_string($v) ? trim($v) : $v, $row->toArray());

        try {
            DB::transaction(function () use ($d, $row) {
                $carnet = $this->normalizeCarnet($d['carnet'] ?? $d['carne'] ?? null);
                $telefono = $this->sanitizeTelefono($d['telefono'] ?? null);
                $correo = $d['email'] ? strtolower(trim($d['email'])) : $this->defaultEmail($carnet);
                $fechaNacimiento = $this->parseDate($d['cumpleanos'] ?? null, self::DUMMY_BIRTH_DATE);
                $fechaInscripcion = $this->parseDate($d['fecha_de_inscripcion'] ?? null, now()->toDateString());
                $numCuotas = (int)($d['numero_de_cuotas'] ?? 0);
                $fechaFin = $numCuotas > 0
                    ? Carbon::parse($fechaInscripcion)->addMonths($numCuotas)->toDateString()
                    : Carbon::parse($fechaInscripcion)->addMonths(1)->toDateString();

                $genero = 'No especificado';
                if (isset($d['m']) && ($d['m'] === '1' || strtolower($d['m']) === 'x')) $genero = 'Masculino';
                elseif (isset($d['f']) && ($d['f'] === '1' || strtolower($d['f']) === 'x')) $genero = 'Femenino';

                $prospecto = Prospecto::updateOrCreate(
                    ['carnet' => $carnet],
                    [
                        'nombre_completo' => trim(($d['nombre'] ?? '') . ' ' . ($d['apellido'] ?? '')),
                        'telefono' => $telefono,
                        'correo_electronico' => $correo,
                        'genero' => $genero,
                        'empresa_donde_labora_actualmente' => $d['empresa_plabora'] ?? $d['empresa_p_labora'] ?? null,
                        'puesto' => $d['puesto_trabajo'] ?? null,
                        'observaciones' => $d['observaciones'] ?? null,
                        'numero_identificacion' => $d['dpi'] ?? null,
                        'fecha_nacimiento' => $fechaNacimiento,
                        'modalidad' => $this->normalizeModalidad($d['modalidad'] ?? null),
                        'fecha_inicio_especifica' => $fechaInscripcion,
                        'fecha' => $fechaInscripcion,
                        'dia_estudio' => $this->sanitizeDiaEstudio($d['dia'] ?? null),
                        'direccion_residencia' => $d['direccion'] ?? null,
                        'pais_residencia' => $d['pais'] ?? null,
                        'medio_conocimiento_institucion' => $d['medio_por_cual_ingreso'] ?? null,
                        'monto_inscripcion' => $this->limpiarMonto($d['valor_q_matricula_inscripcion'] ?? '0'),
                        'status' => 'Inscrito',
                        'activo' => true,
                        'created_by' => auth()->id(),
                    ]
                );

                $prog = $this->obtenerPrograma($d['codigo_carrera'] ?? null);

                EstudiantePrograma::updateOrCreate(
                    ['prospecto_id' => $prospecto->id, 'programa_id' => $prog->id],
                    [
                        'fecha_inicio' => $fechaInscripcion,
                        'fecha_fin' => $fechaFin,
                        'convenio_id' => $d['convenio_id'] ?? null,
                        'inscripcion' => $this->limpiarMonto($d['valor_q_matricula_inscripcion'] ?? '0'),
                        'cuota_mensual' => $this->limpiarMonto($d['mensualidad'] ?? '0'),
                        'certificacion' => $this->limpiarMonto($d['certificacion'] ?? '0'),
                        'inversion_total' => $this->limpiarMonto($d['valor_q_total_de_la_carrera'] ?? '0'),
                        'duracion_meses' => $numCuotas,
                        'created_by' => auth()->id(),
                    ]
                );

                // 🟢 Ya no se generan cuotas automáticamente
                Log::info("ℹ️ [Importación {$this->importId}] Cuotas no generadas automáticamente para estudiante {$prospecto->id}");
            });
        } catch (\Throwable $e) {
            $this->addRowError($row, $e);
            if (!$this->skipErrorsMode) throw $e;
        }
    }

    public function onError(\Throwable $e)
    {
        $prefix = $this->importId ? "[Importación {$this->importId}]" : '';
        Log::error("❌ {$prefix} Error en validación: {$e->getMessage()}");
        if (!$this->skipErrorsMode) throw $e;
    }

    public function onFailure(Failure ...$failures)
    {
        $prefix = $this->importId ? "[Importación {$this->importId}]" : '';
        foreach ($failures as $failure) {
            $this->rowCount++;
            $msg = implode('; ', $failure->errors());
            Log::warning("⚠️ {$prefix} Validación fallida en fila {$failure->row()}: {$msg}");
        }
        $this->traitOnFailure(...$failures);
    }
}
