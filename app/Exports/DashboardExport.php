<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class DashboardExport implements WithMultipleSheets
{
    protected $data;

    public function __construct($data)
    {
        // Asegurar que los datos estén en formato array
        if (is_object($data)) {
            $this->data = json_decode(json_encode($data), true);
        } else {
            $this->data = $data ?? [];
        }
    }

    public function sheets(): array
    {
        $sheets = [];

        // Hoja 1: Resumen General
        $sheets[] = new DashboardResumenSheet($this->data);

        // Hoja 2: Distribución por Programas
        $sheets[] = new DashboardProgramasSheet($this->data);

        // Hoja 3: Evolución de Matrícula
        $sheets[] = new DashboardEvolucionSheet($this->data);

        return $sheets;
    }
}

class DashboardResumenSheet implements FromCollection, WithHeadings, WithTitle
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        $data = $this->data;

        // Convertir a array si viene como objeto/JSON
        if (is_object($data)) {
            $data = json_decode(json_encode($data), true);
        }

        return collect([
            ['Métricas Generales', '', ''],
            ['Total Estudiantes', $data['estadisticas']['totalEstudiantes'] ?? 0, ''],
            ['Total Programas', $data['estadisticas']['totalProgramas'] ?? 0, ''],
            ['Total Cursos', $data['estadisticas']['totalCursos'] ?? 0, ''],
            ['', '', ''],
            ['Matrículas del Mes', '', ''],
            ['Mes Actual', $data['matriculas']['total'] ?? 0, ''],
            ['Mes Anterior', $data['matriculas']['mesAnterior'] ?? 0, ''],
            ['% Cambio', $data['matriculas']['porcentajeCambio'] ?? 0, '%'],
            ['', '', ''],
            ['Alumnos Nuevos', '', ''],
            ['Nuevos este Mes', $data['alumnosNuevos']['total'] ?? 0, ''],
            ['Nuevos Mes Anterior', $data['alumnosNuevos']['mesAnterior'] ?? 0, ''],
            ['% Cambio', $data['alumnosNuevos']['porcentajeCambio'] ?? 0, '%'],
        ]);
    }

    public function headings(): array
    {
        return [
            'Concepto',
            'Valor',
            'Unidad'
        ];
    }

    public function title(): string
    {
        return 'Resumen General';
    }
}

class DashboardProgramasSheet implements FromCollection, WithHeadings, WithTitle
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        // Convertir a array si viene como objeto/JSON
        $data = $this->data;
        if (is_object($data)) {
            $data = json_decode(json_encode($data), true);
        }

        $programas = $data['distribucionProgramas'] ?? [];

        return collect($programas)->map(function($programa) {
            // Asegurar que $programa sea array
            if (is_object($programa)) {
                $programa = json_decode(json_encode($programa), true);
            }

            return [
                'programa' => $programa['programa'] ?? '',
                'abreviatura' => $programa['abreviatura'] ?? '',
                'total_estudiantes' => $programa['totalEstudiantes'] ?? 0,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Programa',
            'Abreviatura',
            'Total Estudiantes'
        ];
    }

    public function title(): string
    {
        return 'Distribución por Programas';
    }
}

class DashboardEvolucionSheet implements FromCollection, WithHeadings, WithTitle
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        // Convertir a array si viene como objeto/JSON
        $data = $this->data;
        if (is_object($data)) {
            $data = json_decode(json_encode($data), true);
        }

        $evolucion = $data['evolucionMatricula'] ?? [];

        return collect($evolucion)->map(function($mes) {
            // Asegurar que $mes sea array
            if (is_object($mes)) {
                $mes = json_decode(json_encode($mes), true);
            }

            return [
                'mes' => $mes['mes'] ?? '',
                'total' => $mes['total'] ?? 0,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Mes',
            'Total Matrículas'
        ];
    }

    public function title(): string
    {
        return 'Evolución de Matrícula';
    }
}
