<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\KardexPago;
use App\Models\ReconciliationRecord;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\BankStatementImport;
use App\Exports\ReconciliationTemplateExport;
use App\Exports\ReconciliationRecordsExport;

class ReconciliationController extends Controller
{
    /** =======================
     * Normalizadores (coinciden con front y tu EstudiantePagosController)
     * ======================= */
    protected function normalizeReceiptNumber(string $n): string
    {
        $n = mb_strtoupper($n, 'UTF-8');
        return preg_replace('/[^A-Z0-9]/u', '', $n);
    }

    protected function normalizeBank(string $bank): string
    {
        $b = mb_strtoupper(trim($bank), 'UTF-8');

        $map = [
            'BANCO INDUSTRIAL' => ['BI', 'BANCO INDUSTRIAL', 'INDUSTRIAL'],
            'BANRURAL'         => ['BANRURAL', 'BAN RURAL', 'RURAL'],
            'BAM'              => ['BAM', 'BANCO AGROMERCANTIL'],
            'G&T CONTINENTAL'  => ['G&T', 'G Y T', 'GYT', 'G&T CONTINENTAL'],
            'PROMERICA'        => ['PROMERICA'],
        ];

        foreach ($map as $canon => $aliases) {
            if (in_array($b, $aliases, true)) {
                return $canon;
            }
        }
        return $b;
    }

    /** Fingerprint lógico para matcheo exacto (bank|reference|amount|date) */
    protected function makeFingerprint(string $bankNorm, string $receiptNorm, float $amount, string $dateYmd): string
    {
        // NOTA: no usamos hash para poder depurar fácil; si quieres, cambia a sha256()
        return $bankNorm.'|'.$receiptNorm.'|'.number_format($amount, 2, '.', '').'|'.$dateYmd;
    }

    /**
     * GET /api/conciliacion/pendientes-desde-kardex
     *
     * Devuelve pagos del Kardex que NO tienen su match exacto en reconciliation_records
     * Comparación exacta por:
     *  - bank (normalizado) == bank (normalizado desde reconciliation_records)
     *  - reference (normalizado)
     *  - amount (==)
     *  - date (==, formato Y-m-d)
     *
     * Query params opcionales:
     *  - from=YYYY-MM-DD
     *  - to=YYYY-MM-DD
     *  - banco=BI|BANRURAL|... (alias aceptados; se normaliza)
     */
    public function kardexNoConciliados(Request $request)
    {
        try {
            $from = $request->query('from'); // ej: 2025-01-01
            $to   = $request->query('to');   // ej: 2025-12-31
            $bank = $request->query('banco'); // filtro por banco (alias aceptados)

            $bankFilterNorm = $bank ? $this->normalizeBank($bank) : null;

            // 1) Traer pagos del kardex (rango opcional)
            $kardexQuery = KardexPago::query()
                ->select([
                    'id',
                    'estudiante_programa_id',
                    'cuota_id',
                    'fecha_pago',
                    'monto_pagado',
                    'numero_boleta',
                    'banco',
                    'numero_boleta_normalizada',
                    'banco_normalizado',
                ]);

            if ($from) {
                $kardexQuery->whereDate('fecha_pago', '>=', $from);
            }
            if ($to) {
                $kardexQuery->whereDate('fecha_pago', '<=', $to);
            }
            if ($bankFilterNorm) {
                // CORREGIDO: Usa orWhereRaw para normalización en tiempo real
                $kardexQuery->where(function ($q) use ($bankFilterNorm) {
                    $q->where('banco_normalizado', $bankFilterNorm)
                      ->orWhere('banco', $bankFilterNorm)
                      ->orWhereRaw('UPPER(TRIM(banco)) = ?', [$bankFilterNorm]);
                });
            }

            $kardex = $kardexQuery->orderBy('fecha_pago', 'desc')->get();

            // 2) Cargar reconciliation_records del mismo rango para comparar
            $recQuery = ReconciliationRecord::query()
                ->select(['id','bank','reference','amount','date','status']);

            if ($from) {
                $recQuery->whereDate('date', '>=', $from);
            }
            if ($to) {
                $recQuery->whereDate('date', '<=', $to);
            }

            // CORREGIDO: Simplificado el filtro por banco
            if ($bankFilterNorm) {
                $aliases = $this->getBankAliases($bankFilterNorm);
                $recQuery->where(function($q) use ($aliases) {
                    foreach($aliases as $alias) {
                        $q->orWhere('bank', 'LIKE', '%'.$alias.'%');
                    }
                });
            }

            $recs = $recQuery->get();

            // 3) Construir set de fingerprints desde reconciliation_records
            $recFingerprints = [];
            foreach ($recs as $r) {
                $bankNorm    = $this->normalizeBank((string) $r->bank);
                $receiptNorm = $this->normalizeReceiptNumber((string) $r->reference);
                $amount      = (float) $r->amount;
                $dateYmd     = Carbon::parse($r->date)->format('Y-m-d');
                $fp = $this->makeFingerprint($bankNorm, $receiptNorm, $amount, $dateYmd);
                $recFingerprints[$fp] = true;
            }

            // 4) Detectar los del Kardex que NO tienen match
            $pendientesUi = [];
            $i = 1;

            foreach ($kardex as $pago) {
                // usar normalizados si existen; si no, normalizar a partir de crudos
                $bankNorm = $pago->banco_normalizado ?: $this->normalizeBank((string) $pago->banco);
                $receiptNorm = $pago->numero_boleta_normalizada ?: $this->normalizeReceiptNumber((string) $pago->numero_boleta);
                $amount = (float) $pago->monto_pagado;
                $dateYmd = Carbon::parse($pago->fecha_pago)->format('Y-m-d');

                $fp = $this->makeFingerprint($bankNorm, $receiptNorm, $amount, $dateYmd);

                $hasMatch = isset($recFingerprints[$fp]);

                if (!$hasMatch) {
                    // MEJORADO: Cargar información del estudiante/prospecto
                    $estudiantePrograma = $pago->estudiantePrograma()->with(['prospecto', 'programa'])->first();

                    // Formato tipo PreviewResponse para tu UI "pendientes"
                    $pendientesUi[] = [
                        'index'  => $i++,
                        'input'  => [
                            'carnet'      => $estudiantePrograma->prospecto->carnet ?? '',
                            'alumno'      => $estudiantePrograma->prospecto->nombre ?? $estudiantePrograma->prospecto->primer_nombre.' '.$estudiantePrograma->prospecto->primer_apellido ?? '',
                            'carrera'     => $estudiantePrograma->programa->nombre_del_programa ?? '',
                            'banco'       => $pago->banco ?? $bankNorm,
                            'recibo'      => $pago->numero_boleta ?? $receiptNorm,
                            'monto'       => $amount,
                            'fechaPago'   => $dateYmd,
                            'autorizacion'=> null,
                        ],
                        'status' => 'sin_coincidencia',
                        'message'=> 'No existe registro equivalente en reconciliation_records',
                        'kardex_id' => $pago->id,
                        'cuota_id' => $pago->cuota_id,
                    ];
                }
            }

            // 5) Resumen
            $summary = [
                'conciliados'       => 0, // este endpoint solo saca no conciliados desde kardex
                'monto_conciliado'  => 0,
                'con_diferencia'    => 0,
                'rechazados'        => 0,
                'sin_coincidencia'  => count($pendientesUi),
            ];

            return response()->json([
                'ok'      => true,
                'results' => $pendientesUi,
                'summary' => $summary,
                'message' => 'Pendientes derivados de Kardex sin match en reconciliation_records',
            ]);

        } catch (\Exception $e) {
           // \Log::error('Error en kardexNoConciliados: ' . $e->getMessage());

            return response()->json([
                'ok'      => false,
                'results' => [],
                'summary' => [
                    'conciliados'       => 0,
                    'monto_conciliado'  => 0,
                    'con_diferencia'    => 0,
                    'rechazados'        => 0,
                    'sin_coincidencia'  => 0,
                ],
                'message' => 'Error al procesar la consulta: ' . $e->getMessage(),
            ], 500);
        }
    }

    /** POST /api/conciliacion/import
     * Importa CSV/XLSX a reconciliation_records (ignora prospecto_id).
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,csv,txt|max:10240', // 10MB
        ]);

        try {
            Excel::import(new BankStatementImport, $request->file('file'));

            $summary = session('reconciliation_import_summary', [
                'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0,
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Importación completada',
                'summary' => $summary,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Error al importar: '.$e->getMessage(),
            ], 500);
        }
    }

    /** GET /api/conciliacion/template
     * Descarga plantilla con encabezados correctos.
     */
    public function downloadTemplate()
    {
        return Excel::download(new ReconciliationTemplateExport, 'plantilla_conciliacion.xlsx');
    }

    /** GET /api/conciliacion/export
     * Exporta registros de reconciliation_records según filtros.
     * Query: from=YYYY-MM-DD&to=YYYY-MM-DD&bank=...&status=...
     */
    public function export(Request $request)
    {
        $from   = $request->query('from');
        $to     = $request->query('to');
        $bank   = $request->query('bank');
        $status = $request->query('status');

        $export = new ReconciliationRecordsExport($from, $to, $bank, $status);
        return Excel::download($export, 'reconciliation_records.xlsx');
    }

    /**
     * Helper para obtener aliases de bancos
     */
    private function getBankAliases(string $bankNorm): array
    {
        return match ($bankNorm) {
            'BANCO INDUSTRIAL' => ['BI','INDUSTRIAL', 'BANCO INDUSTRIAL'],
            'BANRURAL'         => ['BAN RURAL','RURAL', 'BANRURAL'],
            'BAM'              => ['BANCO AGROMERCANTIL', 'BAM'],
            'G&T CONTINENTAL'  => ['G&T','G Y T','GYT', 'G&T CONTINENTAL'],
            'PROMERICA'        => ['PROMERICA'],
            default            => [$bankNorm],
        };
    }
}
