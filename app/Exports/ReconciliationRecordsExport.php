<?php
// app/Exports/ReconciliationRecordsExport.php

namespace App\Exports;

use App\Models\ReconciliationRecord;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ReconciliationRecordsExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(
        protected ?string $from = null,
        protected ?string $to = null,
        protected ?string $bank = null,
        protected ?string $status = null,
    ) {}

    public function query()
    {
        $q = ReconciliationRecord::query()
            ->select(['bank','reference','amount','date','auth_number','status']); // sin prospecto_id

        if ($this->from) $q->whereDate('date', '>=', $this->from);
        if ($this->to)   $q->whereDate('date', '<=', $this->to);
        if ($this->bank) $q->where('bank', 'LIKE', '%'.$this->bank.'%');
        if ($this->status) $q->where('status', $this->status);

        return $q->orderBy('date', 'desc');
    }

    public function headings(): array
    {
        return ['Banco','Referencia','Monto','Fecha','Número de Autorización','Estatus'];
    }

    public function map($row): array
    {
        return [
            $row->bank,
            $row->reference,
            number_format((float)$row->amount, 2, '.', ''),
            optional($row->date)->format('Y-m-d'),
            $row->auth_number,
            $row->status,
        ];
    }
}

