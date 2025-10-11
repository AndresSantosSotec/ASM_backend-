<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class SimpleDashboardExport implements FromArray, WithHeadings, WithTitle
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

    public function array(): array
    {
        $result = [];

        // Estadísticas generales
        $estadisticas = $this->data['estadisticas'] ?? [];
        $result[] = ['Métrica', 'Valor'];
        $result[] = ['Total Estudiantes', $estadisticas['totalEstudiantes'] ?? 0];
        $result[] = ['Total Programas', $estadisticas['totalProgramas'] ?? 0];
        $result[] = ['Total Cursos', $estadisticas['totalCursos'] ?? 0];
        $result[] = ['', '']; // Fila vacía

        // Matrículas
        $matriculas = $this->data['matriculas'] ?? [];
        $result[] = ['Matrículas del Mes', ''];
        $result[] = ['Mes Actual', $matriculas['total'] ?? 0];
        $result[] = ['Mes Anterior', $matriculas['mesAnterior'] ?? 0];
        $result[] = ['% Cambio', $matriculas['porcentajeCambio'] ?? 0];
        $result[] = ['', '']; // Fila vacía

        // Distribución por programas
        $programas = $this->data['distribucionProgramas'] ?? [];
        $result[] = ['Programa', 'Estudiantes'];
        foreach ($programas as $programa) {
            $result[] = [
                $programa['programa'] ?? 'N/A',
                $programa['totalEstudiantes'] ?? 0
            ];
        }

        return $result;
    }

    public function headings(): array
    {
        return []; // Sin encabezados porque manejamos todo en array()
    }

    public function title(): string
    {
        return 'Dashboard Administrativo';
    }
}
