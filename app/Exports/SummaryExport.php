<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Collection;

class SummaryExport implements FromCollection, WithHeadings
{
    protected array $rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function collection()
    {
        return new Collection($this->rows);
    }

    public function headings(): array
    {
        return ['total_recaudado', 'deuda_vencida'];
    }
}
