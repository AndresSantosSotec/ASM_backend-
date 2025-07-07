<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use App\Exports\SummaryExport;
use App\Models\KardexPago;
use App\Models\CuotaProgramaEstudiante;

class ExportReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $format;

    public function __construct(string $format = 'pdf')
    {
        $this->format = $format;
    }

    public function handle(): void
    {
        $data = [
            'total_recaudado' => KardexPago::sum('monto_pagado'),
            'deuda_vencida'   => CuotaProgramaEstudiante::where('estado', '!=', 'pagado')
                ->whereDate('fecha_vencimiento', '<', now())
                ->sum('monto'),
        ];

        if ($this->format === 'excel') {
            Excel::store(new SummaryExport([$data]), 'reportes/resumen.xlsx');
        } else {
            $pdf = Pdf::loadView('pdf.report-summary', $data);
            Storage::put('reportes/resumen.pdf', $pdf->output());
        }
    }
}
