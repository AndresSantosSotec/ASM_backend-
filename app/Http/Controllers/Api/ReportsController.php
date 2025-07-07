<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KardexPago;
use App\Models\CuotaProgramaEstudiante;
use App\Models\EstudiantePrograma;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\SummaryExport;
use App\Jobs\ExportReportJob;

class ReportsController extends Controller
{
    /**
     * Financial dashboard summary used by the frontend.
     *
     * Returns monthly incomes, overdue ratios and active student counts
     * comparing the current month against the previous one.
     */
    public function summary()
    {
        $now = now();
        $lastMonth = now()->copy()->subMonth();

        $currentIncome = KardexPago::whereYear('fecha_pago', $now->year)
            ->whereMonth('fecha_pago', $now->month)
            ->sum('monto_pagado');

        $previousIncome = KardexPago::whereYear('fecha_pago', $lastMonth->year)
            ->whereMonth('fecha_pago', $lastMonth->month)
            ->sum('monto_pagado');

        $totalPending = CuotaProgramaEstudiante::where('estado', '!=', 'pagado')
            ->sum('monto');

        $pendingPrev = CuotaProgramaEstudiante::where('estado', '!=', 'pagado')
            ->whereDate('fecha_vencimiento', '<', $now->copy()->firstOfMonth())
            ->sum('monto');

        $overdue = CuotaProgramaEstudiante::where('estado', '!=', 'pagado')
            ->whereDate('fecha_vencimiento', '<=', $now)
            ->sum('monto');

        $totalCuotas = CuotaProgramaEstudiante::sum('monto');
        $morosity = $totalCuotas > 0 ? ($overdue / $totalCuotas) * 100 : 0;

        $overduePrev = CuotaProgramaEstudiante::where('estado', '!=', 'pagado')
            ->whereDate('fecha_vencimiento', '<', $now->copy()->firstOfMonth())
            ->sum('monto');

        $totalPrevCuotas = CuotaProgramaEstudiante::whereDate('fecha_vencimiento', '<', $now->copy()->firstOfMonth())
            ->sum('monto');
        $morosityPrev = $totalPrevCuotas > 0 ? ($overduePrev / $totalPrevCuotas) * 100 : 0;

        $activeStudents = EstudiantePrograma::count();
        $activeStudentsPrev = EstudiantePrograma::whereDate('created_at', '<', $now->copy()->firstOfMonth())->count();

        return response()->json([
            'ingresosMensuales'           => (float) $currentIncome,
            'ingresosMesAnterior'        => (float) $previousIncome,
            'tasaMorosidad'              => round($morosity, 2),
            'tasaMorosidadAnterior'      => round($morosityPrev, 2),
            'recaudacionPendiente'       => (float) $totalPending,
            'recaudacionPendienteAnterior' => (float) $pendingPrev,
            'estudiantesActivos'         => $activeStudents,
            'estudiantesActivosAnterior' => $activeStudentsPrev,
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

        // Use the same data as the summary endpoint
        $now = now();
        $lastMonth = now()->copy()->subMonth();

        $data = [
            'ingresosMensuales'           => (float) KardexPago::whereYear('fecha_pago', $now->year)
                ->whereMonth('fecha_pago', $now->month)
                ->sum('monto_pagado'),
            'ingresosMesAnterior'        => (float) KardexPago::whereYear('fecha_pago', $lastMonth->year)
                ->whereMonth('fecha_pago', $lastMonth->month)
                ->sum('monto_pagado'),
            'tasaMorosidad'              => 0, // placeholder, computed below
            'tasaMorosidadAnterior'      => 0, // placeholder, computed below
            'recaudacionPendiente'       => (float) CuotaProgramaEstudiante::where('estado', '!=', 'pagado')->sum('monto'),
            'recaudacionPendienteAnterior' => (float) CuotaProgramaEstudiante::where('estado', '!=', 'pagado')
                ->whereDate('fecha_vencimiento', '<', $now->copy()->firstOfMonth())
                ->sum('monto'),
            'estudiantesActivos'         => EstudiantePrograma::count(),
            'estudiantesActivosAnterior' => EstudiantePrograma::whereDate('created_at', '<', $now->copy()->firstOfMonth())->count(),
        ];

        $overdue = CuotaProgramaEstudiante::where('estado', '!=', 'pagado')
            ->whereDate('fecha_vencimiento', '<=', $now)
            ->sum('monto');
        $totalCuotas = CuotaProgramaEstudiante::sum('monto');
        $data['tasaMorosidad'] = $totalCuotas > 0 ? round(($overdue / $totalCuotas) * 100, 2) : 0;

        $overduePrev = CuotaProgramaEstudiante::where('estado', '!=', 'pagado')
            ->whereDate('fecha_vencimiento', '<', $now->copy()->firstOfMonth())
            ->sum('monto');
        $totalPrevCuotas = CuotaProgramaEstudiante::whereDate('fecha_vencimiento', '<', $now->copy()->firstOfMonth())
            ->sum('monto');
        $data['tasaMorosidadAnterior'] = $totalPrevCuotas > 0 ? round(($overduePrev / $totalPrevCuotas) * 100, 2) : 0;

        if ($format === 'excel') {
            return Excel::download(new SummaryExport([$data]), 'reporte.xlsx');
        }

        $pdf = Pdf::loadView('pdf.report-summary', $data);
        return $pdf->download('reporte.pdf');
    }
}
