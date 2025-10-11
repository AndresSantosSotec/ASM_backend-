<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReportesMatriculaExport implements WithMultipleSheets
{
    protected $datos;
    protected $detalle;

    public function __construct($datos, $detalle = 'complete')
    {
        $this->datos = $datos;
        $this->detalle = $detalle;
    }

    public function sheets(): array
    {
        $sheets = [];

        // Hoja de resumen
        if ($this->detalle === 'complete' || $this->detalle === 'summary') {
            $sheets[] = new ResumenSheet($this->datos);
        }

        // Hoja de listado de alumnos
        if ($this->detalle === 'complete' || $this->detalle === 'data') {
            $sheets[] = new ListadoAlumnosSheet($this->datos);
        }

        // Hoja de distribución por programas
        if ($this->detalle === 'complete') {
            $sheets[] = new DistribucionProgramasSheet($this->datos);
        }

        return $sheets;
    }
}

class ResumenSheet implements FromCollection, WithHeadings, WithTitle
{
    protected $datos;

    public function __construct($datos)
    {
        $this->datos = $datos;
    }

    public function title(): string
    {
        return 'Resumen';
    }

    public function headings(): array
    {
        return [
            'Métrica',
            'Período Actual',
            'Período Anterior',
            'Variación %'
        ];
    }

    public function collection()
    {
        $comparativa = $this->datos->comparativa ?? null;
        
        if (!$comparativa) {
            return collect([]);
        }

        return collect([
            [
                'Métrica' => 'Total Matriculados',
                'Período Actual' => $comparativa->totales->actual ?? 0,
                'Período Anterior' => $comparativa->totales->anterior ?? 0,
                'Variación %' => ($comparativa->totales->variacion ?? 0) . '%'
            ],
            [
                'Métrica' => 'Alumnos Nuevos',
                'Período Actual' => $comparativa->nuevos->actual ?? 0,
                'Período Anterior' => $comparativa->nuevos->anterior ?? 0,
                'Variación %' => ($comparativa->nuevos->variacion ?? 0) . '%'
            ],
            [
                'Métrica' => 'Alumnos Recurrentes',
                'Período Actual' => $comparativa->recurrentes->actual ?? 0,
                'Período Anterior' => $comparativa->recurrentes->anterior ?? 0,
                'Variación %' => ($comparativa->recurrentes->variacion ?? 0) . '%'
            ]
        ]);
    }
}

class ListadoAlumnosSheet implements FromCollection, WithHeadings, WithTitle
{
    protected $datos;

    public function __construct($datos)
    {
        $this->datos = $datos;
    }

    public function title(): string
    {
        return 'Listado de Alumnos';
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nombre',
            'Fecha Matrícula',
            'Tipo',
            'Programa',
            'Estado'
        ];
    }

    public function collection()
    {
        $alumnos = $this->datos->listado->alumnos ?? [];
        
        return collect($alumnos)->map(function ($alumno) {
            return [
                'ID' => $alumno->id ?? '',
                'Nombre' => $alumno->nombre ?? '',
                'Fecha Matrícula' => $alumno->fechaMatricula ?? '',
                'Tipo' => $alumno->tipo ?? '',
                'Programa' => $alumno->programa ?? '',
                'Estado' => $alumno->estado ?? ''
            ];
        });
    }
}

class DistribucionProgramasSheet implements FromCollection, WithHeadings, WithTitle
{
    protected $datos;

    public function __construct($datos)
    {
        $this->datos = $datos;
    }

    public function title(): string
    {
        return 'Distribución por Programas';
    }

    public function headings(): array
    {
        return [
            'Programa',
            'Total'
        ];
    }

    public function collection()
    {
        $distribucion = $this->datos->periodoActual->distribucionProgramas ?? [];
        
        return collect($distribucion)->map(function ($item) {
            return [
                'Programa' => $item->programa ?? '',
                'Total' => $item->total ?? 0
            ];
        });
    }
}
