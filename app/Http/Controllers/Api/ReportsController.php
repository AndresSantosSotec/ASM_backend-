<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KardexPago;
use App\Models\CuotaProgramaEstudiante;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\SummaryExport;
use App\Jobs\ExportReportJob;

class ReportsController extends Controller
{
    /**
     * Return collected totals and overdue debt amounts.
     */
    public function summary()
    {
        $totalRecaudado = KardexPago::sum('monto_pagado');

        $deudaVencida = CuotaProgramaEstudiante::where('estado', '!=', 'pagado')
            ->whereDate('fecha_vencimiento', '<', now())
            ->sum('monto');

        return response()->json([
            'total_recaudado' => (float) $totalRecaudado,
            'deuda_vencida'   => (float) $deudaVencida,
        ]);
    }

    /**
     * Export the summary as PDF or Excel. If ?queue=1 is provided the export
     * will be queued and stored.
     */
    public function export(Request $request)
    {
        $format = $request->input('format', 'pdf');

        if ($request->boolean('queue')) {
            ExportReportJob::dispatch($format);
            return response()->json(['queued' => true]);
        }

        $data = [
            'total_recaudado' => KardexPago::sum('monto_pagado'),
            'deuda_vencida'   => CuotaProgramaEstudiante::where('estado', '!=', 'pagado')
                ->whereDate('fecha_vencimiento', '<', now())
                ->sum('monto'),
        ];

        if ($format === 'excel') {
            return Excel::download(new SummaryExport([$data]), 'reporte.xlsx');
        }

        $pdf = Pdf::loadView('pdf.report-summary', $data);
        return $pdf->download('reporte.pdf');
    }
}
