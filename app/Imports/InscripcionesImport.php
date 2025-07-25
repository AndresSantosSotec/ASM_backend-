<?php

namespace App\Imports;

use App\Models\CuotaProgramaEstudiante;
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

class InscripcionesImport implements OnEachRow,
    SkipsEmptyRows,
    SkipsOnError,
    SkipsOnFailure,
    WithChunkReading,
    WithHeadingRow,
    WithValidation
{
    use SkipsErrors,
        SkipsFailures { onFailure as traitOnFailure; }

    private const DEFAULT_PROGRAM_ABBR = 'TEMP';
    private const DEFAULT_PROGRAM_NAME = 'Programa Pendiente';
    private const DUMMY_BIRTH_DATE = '2000-01-01';
    private const DEFAULT_MODALIDAD = 'sincronica';

    /**
     * Mapas de alias para códigos de programa.
     * Permite reconocer variaciones como "MRRHH" => "MHTM".
     */
    private const PROGRAM_ALIASES = [
        'MMKD'  => 'MMK',
        'MMK'   => 'MMK',
        'MRRHH' => 'MHTM',
    ];

    protected array $rowErrors = [];

    // Nuevas propiedades agregadas
    private ?string $importId = null;
    private int $rowCount = 0;
    private bool $skipErrorsMode = false;

    // Métodos faltantes agregados
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

    protected function obtenerPrograma(?string $claveProg): Programa
    {
        $abrev = $this->normalizeProgramaCodigo($claveProg);

        if ($abrev) {
            $programa = Programa::whereRaw('upper(abreviatura) = ?', [$abrev])->first();

            if (!$programa) {
                // Búsqueda más flexible por prefijo por si hay variaciones
                $programa = Programa::whereRaw('upper(abreviatura) LIKE ?', [$abrev . '%'])->first();
            }

            if ($programa) {
                return $programa;
            }

            Log::warning("Programa no encontrado para código: {$abrev}. Se utilizará temporal.");
        } else {
            Log::warning('Código de programa vacío, se utilizará temporal.');
        }

        return Programa::firstOrCreate(
            ['abreviatura' => self::DEFAULT_PROGRAM_ABBR],
            ['nombre_del_programa' => self::DEFAULT_PROGRAM_NAME]
        );
    }

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

    public function prepareForValidation($data, $index)
    {
        // Normalizar nombres de columnas alternativos
        if (!isset($data['carnet']) && isset($data['carne'])) {
            $data['carnet'] = $data['carne'];
        }
        if (!isset($data['valor_q_matricula_inscripcion']) && isset($data['valor_q_matricula_insripcion'])) {
            $data['valor_q_matricula_inscripcion'] = $data['valor_q_matricula_insripcion'];
        }

        // Asegurar valores numéricos
        $numericFields = [
            'numero_de_cuotas',
            'valor_q_matricula_inscripcion',
            'mensualidad',
            'certificacion',
            'valor_q_total_de_la_carrera'
        ];

        foreach ($numericFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = $this->limpiarMonto($data[$field]);
            } else {
                $data[$field] = 0;
            }
        }


        // Asegurar que teléfono sea una cadena para la validación
        if (isset($data['telefono']) && !is_string($data['telefono'])) {
            $data['telefono'] = strval($data['telefono']);
        }


        // Proveer apellido por defecto si falta
        if (empty($data['apellido'])) {
            $data['apellido'] = 'Desconocido';
        }

        // Normalizar correo electrónico, usar uno temporal si es inválido
        $carnetBase = $data['carnet'] ?? $data['carne'] ?? null;
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $data['email'] = $this->defaultEmail($this->normalizeCarnet($carnetBase));
        }

        // Convertir fechas inválidas en valores por defecto
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

    protected function parseDate($value, ?string $default = null): ?string
    {
        if (empty($value)) {
            return $default;
        }

        try {
            // Manejar fechas Excel (números seriales)
            if (is_numeric($value)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject($value))->toDateString();
            }

            // Manejar formato "24/6/1989"
            if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $value)) {
                return Carbon::createFromFormat('d/m/Y', $value)->toDateString();
            }

            return Carbon::parse($value)->toDateString();
        } catch (\Exception $e) {
            Log::warning("Fecha no válida: {$value} - {$e->getMessage()}");
            return $default;
        }
    }

    protected function limpiarMonto($v): float
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

    protected function normalizeCarnet(?string $carnet): string
    {
        if (empty($carnet)) {
            return 'TEMP-' . Str::random(6);
        }
        return Str::upper(preg_replace('/\s+/', '', $carnet));
    }

    protected function sanitizeTelefono(?string $telefono): string
    {
        if (!$telefono) {
            return $this->defaultTelefono();
        }
        $digits = preg_replace('/\D+/', '', $telefono);
        return $digits !== '' ? $digits : $this->defaultTelefono();
    }

    protected function sanitizeDiaEstudio(?string $dia): ?string
    {
        if ($dia === null) {
            return null;
        }
        return Str::limit(trim($dia), 20, '');
    }

    protected function normalizeProgramaCodigo(?string $code): ?string
    {
        if (empty($code)) {
            return null;
        }

        $base = Str::upper(preg_replace('/[^A-Za-z]/', '', $code));

        // Aplicar alias si existe
        if (isset(self::PROGRAM_ALIASES[$base])) {
            return self::PROGRAM_ALIASES[$base];
        }

        return $base;
    }

    protected function normalizeModalidad(?string $modalidad): string
    {
        if (empty($modalidad)) {
            return self::DEFAULT_MODALIDAD;
        }
        $m = strtolower(trim($modalidad));

        if (str_contains($m, 'elearn') || str_contains($m, 'online') || str_contains($m, 'virtual')) {
            return 'asincronica';
        }
        if (str_contains($m, 'sincro') || str_contains($m, 'presencial')) {
            return 'sincronica';
        }
        if (str_contains($m, 'diplomado')) {
            return 'diplomado';
        }
        return $m;
    }

    protected function defaultEmail(string $carnet): string
    {
        return 'sin-email-' . Str::slug($carnet) . '@example.com';
    }

    protected function defaultTelefono(): string
    {
        return '00000000';
    }

    protected function addRowError(Row $row, \Throwable $e): void
    {
        $this->rowErrors[] = [
            'row' => $row->getIndex(),
            'error' => $e->getMessage(),
            'values' => $row->toArray(),
        ];

        // Log con ID de importación si está disponible
        $logPrefix = $this->importId ? "[Importación {$this->importId}]" : '';
        Log::error("❌ {$logPrefix} Error processing row {$row->getIndex()}: {$e->getMessage()}");
    }

    public function onRow(Row $row)
    {
        // Incrementar contador de filas
        $this->rowCount++;

        // Log con ID de importación si está disponible
        $logPrefix = $this->importId ? "[Importación {$this->importId}]" : '';
        Log::info("🔍 {$logPrefix} Procesando fila #{$row->getIndex()}", $row->toArray());

        $d = array_map(function ($value) {
            return is_string($value) ? trim($value) : $value;
        }, $row->toArray());

        try {
            DB::transaction(function () use ($d, $row) {
                // Normalizar datos básicos
                $carnet = $this->normalizeCarnet($d['carnet'] ?? $d['carne'] ?? null);
                $telefono = $this->sanitizeTelefono($d['telefono'] ?? null);
                $correo = $d['email'] ? strtolower(trim($d['email'])) : $this->defaultEmail($carnet);
                $fechaNacimiento = $this->parseDate($d['cumpleanos'] ?? null, self::DUMMY_BIRTH_DATE);
                $fechaInscripcion = $this->parseDate($d['fecha_de_inscripcion'] ?? null, now()->toDateString());
                $numCuotas = (int)($d['numero_de_cuotas'] ?? 0);
                $fechaFin = $numCuotas > 0
                    ? Carbon::parse($fechaInscripcion)->addMonths($numCuotas)->toDateString()
                    : Carbon::parse($fechaInscripcion)->addMonths(1)->toDateString();

                // Determinar género
                $genero = 'No especificado';
                if (isset($d['m']) && ($d['m'] === '1' || strtolower($d['m']) === 'x')) {
                    $genero = 'Masculino';
                } elseif (isset($d['f']) && ($d['f'] === '1' || strtolower($d['f']) === 'x')) {
                    $genero = 'Femenino';
                }

                // — Prospecto —
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
                        'monto_inscripcion' => $this->limpiarMonto($d['valor_q_matricula_inscripcion'] ?? $d['valor_q_matricula_insripcion'] ?? '0'),
                        'status' => 'Inscrito',
                        'activo' => true,
                        'created_by' => auth()->id(),
                    ]
                );

                // — Programa —
                $claveProg = $d['codigo_carrera'] ?? null;
                $prog = $this->obtenerPrograma($claveProg);

                // — EstudiantePrograma —
                $epData = [
                    'prospecto_id' => $prospecto->id,
                    'programa_id' => $prog->id,
                    'fecha_inicio' => $fechaInscripcion,
                    'fecha_fin' => $fechaFin,
                    'convenio_id' => $d['convenio_id'] ?? null,
                    'inscripcion' => $this->limpiarMonto($d['valor_q_matricula_inscripcion'] ?? $d['valor_q_matricula_insripcion'] ?? '0'),
                    'cuota_mensual' => $this->limpiarMonto($d['mensualidad'] ?? '0'),
                    'certificacion' => $this->limpiarMonto($d['certificacion'] ?? '0'),
                    'inversion_total' => $this->limpiarMonto($d['valor_q_total_de_la_carrera'] ?? '0'),
                    'duracion_meses' => $numCuotas,
                    'created_by' => auth()->id(),
                ];

                $ep = EstudiantePrograma::updateOrCreate(
                    ['prospecto_id' => $prospecto->id, 'programa_id' => $prog->id],
                    $epData
                );

                // Eliminar cuotas existentes (usando DELETE directo sin softDeletes)
                DB::table('cuotas_programa_estudiante')
                    ->where('estudiante_programa_id', $ep->id)
                    ->delete();

                // — Cuotas pendientes —
                if ($numCuotas > 0 && $ep->cuota_mensual > 0) {
                    $cuotas = [];
                    for ($i = 1; $i <= $numCuotas; $i++) {
                        $fechaVenc = Carbon::parse($fechaInscripcion)
                            ->addMonths($i - 1)
                            ->toDateString();

                        $cuotas[] = [
                            'estudiante_programa_id' => $ep->id,
                            'numero_cuota' => $i,
                            'fecha_vencimiento' => $fechaVenc,
                            'monto' => $ep->cuota_mensual,
                            'estado' => 'pendiente',
                            'created_at' => now(),
                            'updated_at' => now(),
                            'created_by' => auth()->id(),
                        ];
                    }

                    // Insertar todas las cuotas en una sola operación
                    DB::table('cuotas_programa_estudiante')->insert($cuotas);
                }
            });
        } catch (\Throwable $e) {
            // Si estamos en modo skip errors, solo agregar el error y continuar
            if ($this->skipErrorsMode) {
                $this->addRowError($row, $e);
                return; // Continuar con la siguiente fila
            }

            // Si no estamos en modo skip, agregar error y relanzar excepción
            $this->addRowError($row, $e);
            throw $e;
        }
    }

    /**
     * Manejar errores de validación
     */
    public function onError(\Throwable $e)
    {
        $logPrefix = $this->importId ? "[Importación {$this->importId}]" : '';
        Log::error("❌ {$logPrefix} Error en validación: {$e->getMessage()}");

        if (!$this->skipErrorsMode) {
            throw $e;
        }
    }

    /**
     * Manejar fallos de validación
     *
     * @param Failure[] $failures
     */
    public function onFailure(Failure ...$failures)
    {
        $logPrefix = $this->importId ? "[Importación {$this->importId}]" : '';

        // Incrementar contador por cada fila fallida y registrar el error
        foreach ($failures as $failure) {
            $this->rowCount++;
            $msg = implode('; ', $failure->errors());
            Log::warning("⚠️ {$logPrefix} Validación fallida en fila {$failure->row()}: {$msg}");
        }

        // Conservar el comportamiento original del trait
        $this->traitOnFailure(...$failures);
    }

}
