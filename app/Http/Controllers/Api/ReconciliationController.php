<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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
     * Normalizadores
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

    /** Fingerprint lógico (bank|reference|amount|date) */
    protected function makeFingerprint(string $bankNorm, string $receiptNorm, float $amount, string $dateYmd): string
    {
        return $bankNorm . '|' . $receiptNorm . '|' . number_format($amount, 2, '.', '') . '|' . $dateYmd;
    }

    /**
     * GET /api/conciliacion/pendientes-desde-kardex
     */
    public function kardexNoConciliados(Request $request)
    {
        try {
            $from = $request->query('from');
            $to   = $request->query('to');
            $bank = $request->query('banco');

            $bankFilterNorm = $bank ? $this->normalizeBank($bank) : null;

            // 1) Kardex
            $kardexQuery = KardexPago::query()
                ->select([
                    'id',
                    'estudiante_programa_id',
                    'cuota_id',
                    'fecha_pago',
                    'fecha_recibo',
                    'monto_pagado',
                    'numero_boleta',
                    'banco',
                    'numero_boleta_normalizada',
                    'banco_normalizado',
                ]);

            if ($from) $kardexQuery->whereDate('fecha_pago', '>=', $from);
            if ($to)   $kardexQuery->whereDate('fecha_pago', '<=', $to);
            if ($bankFilterNorm) {
                $kardexQuery->where(function ($q) use ($bankFilterNorm) {
                    $q->where('banco_normalizado', $bankFilterNorm)
                      ->orWhere('banco', $bankFilterNorm)
                      ->orWhereRaw('UPPER(TRIM(banco)) = ?', [$bankFilterNorm]);
                });
            }

            $kardex = $kardexQuery->orderBy('fecha_pago', 'desc')->get();

            // 2) reconciliation_records
            $recQuery = ReconciliationRecord::query()
                ->select(['id', 'bank', 'reference', 'amount', 'date', 'status']);
            if ($from) $recQuery->whereDate('date', '>=', $from);
            if ($to)   $recQuery->whereDate('date', '<=', $to);
            if ($bankFilterNorm) {
                $aliases = $this->getBankAliases($bankFilterNorm);
                $recQuery->where(function ($q) use ($aliases) {
                    foreach ($aliases as $alias) {
                        $q->orWhere('bank', 'LIKE', '%' . $alias . '%');
                    }
                });
            }
            $recs = $recQuery->get();

            $recFingerprints = [];
            foreach ($recs as $r) {
                $bn = $this->normalizeBank((string) $r->bank);
                $rn = $this->normalizeReceiptNumber((string) $r->reference);
                $amt = (float) $r->amount;
                $ymd = Carbon::parse($r->date)->format('Y-m-d');
                $recFingerprints[$this->makeFingerprint($bn, $rn, $amt, $ymd)] = true;
            }

            // 3) Pendientes
            $pendientesUi = [];
            $i = 1;

            foreach ($kardex as $p) {
                $bn = $p->banco_normalizado ?: $this->normalizeBank((string) $p->banco);
                $rn = $p->numero_boleta_normalizada ?: $this->normalizeReceiptNumber((string) $p->numero_boleta);
                $amt = (float) $p->monto_pagado;
                $ymd = $p->fecha_recibo
                    ? Carbon::parse($p->fecha_recibo)->format('Y-m-d')
                    : Carbon::parse($p->fecha_pago)->format('Y-m-d');

                $fp = $this->makeFingerprint($bn, $rn, $amt, $ymd);

                if (!isset($recFingerprints[$fp])) {
                    $est = $p->estudiantePrograma()->with(['prospecto', 'programa'])->first();
                    $pendientesUi[] = [
                        'index'  => $i++,
                        'input'  => [
                            'carnet'  => $est->prospecto->carnet ?? '',
                            'alumno'  => $est->prospecto->nombre ?? (($est->prospecto->primer_nombre ?? '') . ' ' . ($est->prospecto->primer_apellido ?? '')),
                            'carrera' => $est->programa->nombre_del_programa ?? '',
                            'banco'   => $p->banco ?? $bn,
                            'recibo'  => $p->numero_boleta ?? $rn,
                            'monto'   => $amt,
                            'fechaPago' => $ymd,
                            'autorizacion' => null,
                        ],
                        'status' => 'sin_coincidencia',
                        'message' => 'No existe registro equivalente en reconciliation_records',
                        'kardex_id' => $p->id,
                        'cuota_id'  => $p->cuota_id,
                    ];
                }
            }

            return response()->json([
                'ok' => true,
                'results' => $pendientesUi,
                'summary' => [
                    'conciliados' => 0,
                    'monto_conciliado' => 0,
                    'con_diferencia' => 0,
                    'rechazados' => 0,
                    'sin_coincidencia' => count($pendientesUi),
                ],
                'message' => 'Pendientes derivados de Kardex sin match en reconciliation_records',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'results' => [],
                'summary' => [
                    'conciliados' => 0,
                    'monto_conciliado' => 0,
                    'con_diferencia' => 0,
                    'rechazados' => 0,
                    'sin_coincidencia' => 0,
                ],
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /** POST /api/conciliacion/import */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,csv,txt|max:10240',
        ]);

        $uploaderId = auth()->id()
            ?? optional($request->user())->id
            ?? $request->integer('uploaded_by')
            ?? $request->integer('X-User-Id');

        if (!$uploaderId) {
            return response()->json([
                'ok' => false,
                'message' => 'Falta el usuario que sube el archivo (uploaded_by).',
            ], 422);
        }

        try {
            Excel::import(new BankStatementImport(uploaderId: $uploaderId), $request->file('file'));

            $recs = ReconciliationRecord::where('uploaded_by', $uploaderId)
                ->whereNull('status')
                ->get();

            $conciliados = 0;
            $montoConciliado = 0;
            $conciliadosList = [];

            foreach ($recs as $r) {
                if (!$r->bank || !$r->reference || !$r->amount || !$r->date) {
                    $r->update(['status' => 'rechazado']);
                    continue;
                }

                $bn = $this->normalizeBank((string)$r->bank);
                $rn = $this->normalizeReceiptNumber((string)$r->reference);
                $amt = (float)$r->amount;
                $ymd = Carbon::parse($r->date)->format('Y-m-d');

                $kardex = KardexPago::where('banco_normalizado', $bn)
                    ->where('numero_boleta_normalizada', $rn)
                    ->whereDate(DB::raw("COALESCE(fecha_recibo, fecha_pago)"), $ymd)
                    ->where('monto_pagado', $amt)
                    ->where('estado_pago', 'pendiente_revision')
                    ->first();

                if ($kardex) {
                    DB::transaction(function () use ($kardex, $r, $amt, &$conciliados, &$montoConciliado, &$conciliadosList) {
                        $kardex->update([
                            'estado_pago'   => 'aprobado',
                            'observaciones' => 'Conciliado automáticamente con estado de cuenta'
                        ]);

                        $kardex->cuota()->update([
                            'estado'  => 'pagado',
                            'paid_at' => Carbon::now(),
                        ]);

                        $r->update(['status' => 'conciliado']);

                        $conciliados++;
                        $montoConciliado += $amt;
                        $conciliadosList[] = [
                            'kardex_id' => $kardex->id,
                            'cuota_id'  => $kardex->cuota_id,
                            'monto'     => $amt,
                        ];
                    });
                }
            }

            return response()->json([
                'ok' => true,
                'message' => 'Importación y conciliación completadas',
                'summary' => [
                    'conciliados' => $conciliados,
                    'monto_conciliado' => $montoConciliado,
                ],
                'conciliados_list' => $conciliadosList,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Error al importar: ' . $e->getMessage(),
            ], 500);
        }
    }

    /** GET /api/conciliacion/template */
    public function downloadTemplate()
    {
        return Excel::download(new ReconciliationTemplateExport, 'plantilla_conciliacion.xlsx');
    }

    /** GET /api/conciliacion/export */
    public function export(Request $request)
    {
        $from   = $request->query('from');
        $to     = $request->query('to');
        $bank   = $request->query('bank');
        $status = $request->query('status');

        $export = new ReconciliationRecordsExport($from, $to, $bank, $status);
        return Excel::download($export, 'reconciliation_records.xlsx');
    }

    private function getBankAliases(string $bankNorm): array
    {
        return match ($bankNorm) {
            'BANCO INDUSTRIAL' => ['BI', 'INDUSTRIAL', 'BANCO INDUSTRIAL'],
            'BANRURAL'         => ['BAN RURAL', 'RURAL', 'BANRURAL'],
            'BAM'              => ['BANCO AGROMERCANTIL', 'BAM'],
            'G&T CONTINENTAL'  => ['G&T', 'G Y T', 'GYT', 'G&T CONTINENTAL'],
            'PROMERICA'        => ['PROMERICA'],
            default            => [$bankNorm],
        };
    }

    /** GET /api/conciliacion/conciliados */
    public function kardexConciliados(Request $request)
    {
        try {
            $from = $request->query('from');
            $to   = $request->query('to');
            $bank = $request->query('banco');
            $bankFilterNorm = $bank ? $this->normalizeBank($bank) : null;

            $kardexQuery = KardexPago::query()->select([
                'id',
                'estudiante_programa_id',
                'cuota_id',
                'fecha_pago',
                'fecha_recibo',
                'monto_pagado',
                'numero_boleta',
                'banco',
                'numero_boleta_normalizada',
                'banco_normalizado',
            ]);
            if ($from) $kardexQuery->whereDate('fecha_pago', '>=', $from);
            if ($to)   $kardexQuery->whereDate('fecha_pago', '<=', $to);
            if ($bankFilterNorm) {
                $kardexQuery->where(function ($q) use ($bankFilterNorm) {
                    $q->where('banco_normalizado', $bankFilterNorm)
                      ->orWhere('banco', $bankFilterNorm)
                      ->orWhereRaw('UPPER(TRIM(banco)) = ?', [$bankFilterNorm]);
                });
            }
            $kardex = $kardexQuery->orderBy('fecha_pago', 'desc')->get();

            $recs = ReconciliationRecord::query()->select([
                'id','bank','reference','amount','date','auth_number','status'
            ])->get();

            $recMap = [];
            foreach ($recs as $r) {
                $bn = $this->normalizeBank((string)$r->bank);
                $rn = $this->normalizeReceiptNumber((string)$r->reference);
                $ymd = Carbon::parse($r->date)->format('Y-m-d');
                $recMap[$this->makeFingerprint($bn, $rn, (float)$r->amount, $ymd)] = $r;
            }

            $out = [];
            $i = 1;
            $sum = 0;

            foreach ($kardex as $p) {
                $bn = $p->banco_normalizado ?: $this->normalizeBank((string)$p->banco);
                $rn = $p->numero_boleta_normalizada ?: $this->normalizeReceiptNumber((string)$p->numero_boleta);
                $amt = (float)$p->monto_pagado;
                $ymd = $p->fecha_recibo
                    ? Carbon::parse($p->fecha_recibo)->format('Y-m-d')
                    : Carbon::parse($p->fecha_pago)->format('Y-m-d');

                $fp = $this->makeFingerprint($bn, $rn, $amt, $ymd);
                if (!isset($recMap[$fp])) continue;

                $est = $p->estudiantePrograma()->with(['prospecto', 'programa'])->first();

                $out[] = [
                    'index' => $i++,
                    'input' => [
                        'carnet'  => $est->prospecto->carnet ?? '',
                        'alumno'  => $est->prospecto->nombre ?? (($est->prospecto->primer_nombre ?? '') . ' ' . ($est->prospecto->primer_apellido ?? '')),
                        'carrera' => $est->programa->nombre_del_programa ?? '',
                        'banco'   => $p->banco ?? $bn,
                        'recibo'  => $p->numero_boleta ?? $rn,
                        'monto'   => $amt,
                        'fechaPago' => $ymd,
                        'autorizacion' => $recMap[$fp]->auth_number ?? null,
                    ],
                    'status'   => 'conciliado',
                    'message'  => 'Match exacto (bank, referencia, monto, fecha).',
                    'kardex_id'=> $p->id,
                    'cuota_id' => $p->cuota_id,
                ];
                $sum += $amt;
            }

            return response()->json([
                'ok' => true,
                'results' => $out,
                'summary' => [
                    'conciliados' => count($out),
                    'monto_conciliado' => $sum,
                    'con_diferencia' => 0,
                    'rechazados' => 0,
                    'sin_coincidencia' => 0,
                ],
                'message' => 'Conciliados (intersección Kardex ↔ reconciliation_records)',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'results' => [],
                'summary' => [
                    'conciliados' => 0,
                    'monto_conciliado' => 0,
                    'con_diferencia' => 0,
                    'rechazados' => 0,
                    'sin_coincidencia' => 0,
                ],
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }
}
