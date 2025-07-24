<?php

namespace App\Imports;

use App\Models\Prospecto;
use App\Models\EstudiantePrograma;
use App\Models\Programa;
use App\Models\CuotaProgramaEstudiante;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

    /** 1) Reglas de validación «a nivel de fila» */
    public function rules(): array
    {
        return [
            '*.carnet'                               => 'required|string',
            '*.email'                                => 'nullable|email',
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
    protected function parseDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Exception $e) {
            // opcional: registrar $e->getMessage()
            return null;
        }
    }

    /** 4) Limpia montos «Q1,400.00» → 1400.00 */
    protected function limpiarMonto($v): float
    {
        return (float) str_replace(['Q', ',', ' '], '', trim($v));
    }

    /** 5) Procesar cada fila */
    public function onRow(Row $row)
    {
        $d = array_map('trim', $row->toArray());

        // Normalizar género
        if (!empty($d['m']) && $d['m'] === '1') {
            $genero = 'Masculino';
        } elseif (!empty($d['f']) && $d['f'] === '1') {
            $genero = 'Femenino';
        } else {
            $genero = null;
        }

        // Abreviatura solo letras (fallback para buscar programa)
        $claveProg = Str::upper(preg_replace('/[^A-Za-z]/', '', $d['codigo_carrera'] ?? ''));

        DB::transaction(function () use ($d, $genero, $claveProg) {
            // — Prospecto —
            $prospecto = Prospecto::updateOrCreate(
                ['carnet' => $d['carnet']],
                [
                    'nombre_completo'               => trim("{$d['nombre']} {$d['apellido']}"),
                    'telefono'                      => $d['telefono'] ?: null,
                    'correo_electronico'            => $d['email'] ?: null,
                    'genero'                        => $genero,
                    'empresa_donde_labora_actualmente' => $d['empresa_p_labora'] ?? null,
                    'puesto'                        => $d['puesto_trabajo'] ?? null,
                    'observaciones'                 => $d['observaciones'] ?? null,
                    'numero_identificacion'         => $d['dpi'] ?? null,
                    'fecha_nacimiento'              => $this->parseDate($d['cumpleanos']),
                    'modalidad'                     => $d['modalidad'] ?? null,
                    'fecha_inicio_especifica'       => $this->parseDate($d['fecha_de_inscripcion']),
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
            $prog = Programa::whereRaw('upper(abreviatura) = ?', [$claveProg])->first()
                ?? Programa::where('abreviatura', 'ilike', "%{$claveProg}%")->first();

            if (! $prog) {
                // Si no encontramos programa, saltamos esta fila
                return;
            }

            // — Inscripción académica (histórico) —
            $fechaInicio = $this->parseDate($d['fecha_de_inscripcion']) ?? now()->toDateString();
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
    }
}
