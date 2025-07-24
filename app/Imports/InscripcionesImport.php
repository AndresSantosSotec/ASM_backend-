<?php

namespace App\Imports;

use App\Models\Prospecto;
use App\Models\EstudiantePrograma;
use App\Models\Programa;
use App\Models\CuotaProgramaEstudiante;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\{
    OnEachRow,
    WithHeadingRow,
    WithChunkReading,
    SkipsOnError,
    SkipsOnFailure,
    WithValidation,
    SkipsEmptyRows
};
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Row;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Importa inscripciones desde un archivo Excel.
 *
 * Incluye validaciones y manejo de errores por fila para
 * garantizar que la carga sea lo más robusta posible.
 */

class InscripcionesImport implements
    OnEachRow,
    WithHeadingRow,
    WithChunkReading,
    ShouldQueue,
    SkipsOnError,
    SkipsOnFailure,
    WithValidation,
    SkipsEmptyRows
{
    use SkipsErrors, SkipsFailures;

    /** Programa usado cuando el código de carrera no existe. */
    private const DEFAULT_PROGRAM_ABBR = 'TEMP';
    private const DEFAULT_PROGRAM_NAME = 'Programa Pendiente';

    /** Fecha utilizada cuando no hay fecha válida. */
    private const DUMMY_BIRTH_DATE = '2000-01-01';
    /** Modalidad por defecto cuando el archivo no la provee o es desconocida. */
    private const DEFAULT_MODALIDAD = 'sincronica';


    /**
     * Errores ocurridos al procesar filas.
     *
     * @var array<int, array>
     */
    protected array $rowErrors = [];

    /**
     * Devuelve las filas que no pudieron procesarse.
     */
    public function getRowErrors(): array
    {
        return $this->rowErrors;
    }

    /**
     * Devuelve un programa existente por código o uno temporal si no se encuentra.
     */
    protected function obtenerPrograma(string $claveProg): Programa
    {
        if ($claveProg !== '') {
            $programa = Programa::whereRaw('upper(abreviatura) = ?', [$claveProg])->first()
                ?? Programa::where('abreviatura', 'ilike', "%{$claveProg}%")->first();
            if ($programa) {
                return $programa;
            }
            Log::warning('Programa no encontrado para código: ' . $claveProg . '. Se utilizará temporal.');
        } else {
            Log::warning('Código de programa vacío, se utilizará temporal.');
        }

        return Programa::firstOrCreate(
            ['abreviatura' => self::DEFAULT_PROGRAM_ABBR],
            ['nombre_del_programa' => self::DEFAULT_PROGRAM_NAME]
        );
    }

    /** 1) Reglas de validación «a nivel de fila» */
    public function rules(): array
    {
        return [
            '*.carnet'       => 'required|string',
            '*.nombre'       => 'required|string',
            '*.apellido'     => 'required|string',
            '*.telefono'     => 'nullable|string',
            '*.email'        => 'nullable|email',
            '*.numero_de_cuotas'                     => 'nullable|integer',
            '*.valor_q_matricula_inscripcion'       => 'nullable',
            '*.mensualidad'                          => 'nullable',
            '*.valor_q_total_de_la_carrera'          => 'nullable',
            '*.fecha_de_inscripcion'                 => 'nullable|date',
            '*.fecha_nacimiento'                     => 'nullable|date',
            // puedes agregar más reglas según necesites...
        ];
    }

    /** 2) Tamaño de chunk para procesar en trozos y no saturar memoria */
    public function chunkSize(): int
    {
        return 500;
    }

    /** 3) Helper para parsear fechas sin petar si vienen en otro formato */
    protected function parseDate($value, ?string $default = null): ?string
    {
        if (empty($value)) {
            return $default;
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Exception $e) {
            Log::warning('Fecha no válida: ' . $value . ' - ' . $e->getMessage());

            return $default;

        }
    }

    /** 4) Limpia montos «Q1,400.00» o "$1,400.00" → 1400.00 */
    protected function limpiarMonto($v): float
    {
        if (! is_string($v)) {
            return (float) $v;
        }

        $clean = str_replace(['Q', '$', ',', ' '], '', trim($v));

        return (float) $clean;
    }


    /** Normaliza el carné quitando espacios y pasando a mayúsculas. */
    protected function normalizeCarnet(string $carnet): string
    {
        return Str::upper(preg_replace('/\s+/', '', $carnet));
    }


    /** Limpia un número de teléfono o usa un valor por defecto. */
    protected function sanitizeTelefono(?string $telefono): string
    {
        if (! $telefono) {
            return $this->defaultTelefono();
        }

        $digits = preg_replace('/\D+/', '', $telefono);

        return $digits !== '' ? $digits : $this->defaultTelefono();
    }

    /** Normaliza la modalidad y aplica un valor por defecto. */
    protected function normalizeModalidad(?string $modalidad): string
    {
        if (empty($modalidad)) {
            return self::DEFAULT_MODALIDAD;
        }

        $m = strtolower(trim($modalidad));

        if (str_contains($m, 'elearn')) {
            return 'asincronica';
        }
        if (str_contains($m, 'sincro')) {
            return 'sincronica';
        }

        return $m;
    }


    /** Genera un correo temporal si no se proporciona uno. */
    protected function defaultEmail(string $carnet): string
    {
        return 'sin-email-' . Str::slug($carnet ?: Str::random(6)) . '@example.com';
    }

    /** Genera un teléfono temporal si no se proporciona uno. */
    protected function defaultTelefono(): string
    {
        return '00000000';
    }

    /**
     * Registra un error ocurrido al procesar una fila y lo almacena.
     */
    protected function addRowError(Row $row, \Throwable $e): void
    {
        $this->rowErrors[] = [
            'row'    => $row->getIndex(),
            'error'  => $e->getMessage(),
            'values' => $row->toArray(),
        ];
        Log::error('Error processing row ' . $row->getIndex() . ': ' . $e->getMessage());
    }

    /** 5) Procesar cada fila */
    public function onRow(Row $row)
    {
        $d = array_map('trim', $row->toArray());


        if (!empty($d['carnet'])) {
            $d['carnet'] = $this->normalizeCarnet($d['carnet']);
        }

        $telefono = $this->sanitizeTelefono($d['telefono'] ?? null);
        $correo   = $d['email'] ? strtolower($d['email']) : $this->defaultEmail($d['carnet'] ?? '');

        $fechaNacimiento = $this->parseDate($d['cumpleanos'], self::DUMMY_BIRTH_DATE);
        $fechaInscripcion = $this->parseDate($d['fecha_de_inscripcion'], now()->toDateString());


        // Normalizar género
        if (!empty($d['m']) && $d['m'] === '1') {
            $genero = 'Masculino';
        } elseif (!empty($d['f']) && $d['f'] === '1') {
            $genero = 'Femenino';
        } else {
            $genero = 'No especificado';
        }

        // Abreviatura solo letras (fallback para buscar programa)
        $claveProg = Str::upper(preg_replace('/[^A-Za-z]/', '', $d['codigo_carrera'] ?? ''));

        try {

            DB::transaction(function () use ($d, $genero, $claveProg, $row, $telefono, $correo, $fechaNacimiento, $fechaInscripcion) {

            // — Prospecto —
            $prospecto = Prospecto::updateOrCreate(
                ['carnet' => $d['carnet']],
                [
                    'nombre_completo'               => trim("{$d['nombre']} {$d['apellido']}"),
                    'telefono'                      => $telefono,
                    'correo_electronico'            => $correo,
                    'genero'                        => $genero,
                    'empresa_donde_labora_actualmente' => $d['empresa_p_labora'] ?? null,
                    'puesto'                        => $d['puesto_trabajo'] ?? null,
                    'observaciones'                 => $d['observaciones'] ?? null,
                    'numero_identificacion'         => $d['dpi'] ?? null,

                    'fecha_nacimiento'              => $fechaNacimiento,
                    'modalidad'                     => $this->normalizeModalidad($d['modalidad'] ?? null),
                    'fecha_inicio_especifica'       => $fechaInscripcion,
                    'fecha'                         => $fechaInscripcion,
                    'dia_estudio'                   => $d['dia'] ?? null,
                    'direccion_residencia'          => $d['direccion'] ?? null,
                    'pais_residencia'               => $d['pais'] ?? null,
                    'medio_conocimiento_institucion'=> $d['medio_por_cual_ingreso'] ?? null,
                    'monto_inscripcion'             => $this->limpiarMonto($d['valor_q_matricula_inscripcion'] ?? '0'),
                    'status'                        => 'Inscrito',
                    'activo'                        => true,
                    'created_by'                    => auth()->id(),
                ]
            );

            // — Programa —
            $prog = $this->obtenerPrograma($claveProg);

            // — Inscripción académica (histórico) —
            $fechaInicio = $fechaInscripcion;
            $numCuotas   = (int) ($d['numero_de_cuotas'] ?? 0);

            $ep = EstudiantePrograma::firstOrCreate(
                [
                    'prospecto_id' => $prospecto->id,
                    'programa_id'  => $prog->id,
                    'fecha_inicio' => $fechaInicio,
                ],
                [
                    'convenio_id'     => $d['convenio_id'] ?? null,
                    'inscripcion'     => $this->limpiarMonto($d['valor_q_matricula_inscripcion'] ?? '0'),
                    'cuota_mensual'   => $this->limpiarMonto($d['mensualidad'] ?? '0'),
                    'inversion_total' => $this->limpiarMonto($d['valor_q_total_de_la_carrera'] ?? '0'),
                    'duracion_meses'  => $numCuotas,
                    'created_by'      => auth()->id(),
                ]
            );

            // — Cuotas pendientes —
            for ($i = 1; $i <= $numCuotas; $i++) {
                $fechaVenc = Carbon::parse($fechaInicio)->addMonths($i - 1)->toDateString();
                CuotaProgramaEstudiante::firstOrCreate(
                    [
                        'estudiante_programa_id' => $ep->id,
                        'numero_cuota'           => $i,
                    ],
                    [
                        'fecha_vencimiento' => $fechaVenc,
                        'monto'             => $ep->cuota_mensual,
                        'estado'            => 'pendiente',
                        'created_by'        => auth()->id(),
                    ]
                );
            }
        });
        } catch (\Throwable $e) {
            $this->addRowError($row, $e);
        }
    }
}
