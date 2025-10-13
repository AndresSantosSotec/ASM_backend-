<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class EstudiantesMatriculadosExport implements WithMultipleSheets
{
    protected $datos;

    public function __construct($datos)
    {
        $this->datos = $datos;
    }

    public function sheets(): array
    {
        return [
            new EstadisticasSheet($this->datos),
            new EstudiantesSheet($this->datos),
            new DistribucionSheet($this->datos)
        ];
    }
}

class EstadisticasSheet implements FromCollection, WithHeadings, WithTitle
{
    protected $datos;

    public function __construct($datos)
    {
        $this->datos = $datos;
    }

    public function title(): string
    {
        return 'Estadísticas';
    }

    public function headings(): array
    {
        return [
            'Métrica',
            'Valor'
        ];
    }

    public function collection()
    {
        $estadisticas = $this->datos->estadisticas ?? null;
        
        if (!$estadisticas) {
            return collect([]);
        }

        return collect([
            [
                'Métrica' => 'Total Estudiantes',
                'Valor' => $estadisticas->totalEstudiantes ?? 0
            ],
            [
                'Métrica' => 'Nuevos',
                'Valor' => $estadisticas->nuevos ?? 0
            ],
            [
                'Métrica' => 'Recurrentes',
                'Valor' => $estadisticas->recurrentes ?? 0
            ]
        ]);
    }
}

class EstudiantesSheet implements FromCollection, WithHeadings, WithTitle
{
    protected $datos;

    public function __construct($datos)
    {
        $this->datos = $datos;
    }

    public function title(): string
    {
        return 'Estudiantes';
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nombre',
            'Carnet',
            'Email',
            'Teléfono',
            'Fecha Matrícula',
            'Tipo',
            'Programa',
            'Estado'
        ];
    }

    public function collection()
    {
        $estudiantes = $this->datos->estudiantes ?? [];
        
        return collect($estudiantes)->map(function ($estudiante) {
            return [
                'ID' => $estudiante->id ?? '',
                'Nombre' => $estudiante->nombre ?? '',
                'Carnet' => $estudiante->carnet ?? 'N/A',
                'Email' => $estudiante->email ?? 'N/A',
                'Teléfono' => $estudiante->telefono ?? 'N/A',
                'Fecha Matrícula' => $estudiante->fechaMatricula ?? '',
                'Tipo' => $estudiante->tipo ?? '',
                'Programa' => $estudiante->programa ?? '',
                'Estado' => $estudiante->estado ?? ''
            ];
        });
    }
}

class DistribucionSheet implements FromCollection, WithHeadings, WithTitle
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
            'Total',
            'Porcentaje'
        ];
    }

    public function collection()
    {
        $distribucion = $this->datos->estadisticas->distribucionProgramas ?? [];
        
        return collect($distribucion)->map(function ($item) {
            return [
                'Programa' => $item->programa ?? '',
                'Total' => $item->total ?? 0,
                'Porcentaje' => ($item->porcentaje ?? 0) . '%'
            ];
        });
    }
}
