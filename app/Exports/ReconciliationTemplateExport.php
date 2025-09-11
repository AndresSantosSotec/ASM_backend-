<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromArray;

class ReconciliationTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'Banco',
            'Referencia',       // No. de boleta / referencia
            'Monto',            // 1234.56
            'Fecha',            // yyyy-mm-dd o dd/mm/yyyy
            'Número de Autorización',
        ];
    }

    public function array(): array
    {
        // filas de ejemplo opcionales; puedes dejar vacío
        return [
            ['BANCO INDUSTRIAL', 'BI-123456', '750.00', '2025-03-10', 'AUTH-987654'],
            ['BANRURAL',         'BR-789012', '950.00', '10/03/2025', ''],
        ];
    }
}