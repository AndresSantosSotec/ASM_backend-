<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
     * Método mejorado para extraer fecha consistente
     */
    protected function extractDateFromKardex($kardexRecord): string
    {
        // Prioridad: fecha_recibo, luego fecha_pago
        $fechaRecibo = $kardexRecord->fecha_recibo;
        $fechaPago   = $kardexRecord->fecha_pago;

        if ($fechaRecibo) {
            return $fechaRecibo instanceof Carbon
                ? $fechaRecibo->format('Y-m-d')
                : Carbon::parse($fechaRecibo)->format('Y-m-d');
        }

        if ($fechaPago) {
            return $fechaPago instanceof Carbon
                ? $fechaPago->format('Y-m-d')
                : Carbon::parse($fechaPago)->format('Y-m-d');
        }

        // Último recurso (no debería ocurrir)
        return Carbon::now()->format('Y-m-d');
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

            Log::info('kardexNoConciliados: filtros recibidos', [
                'from' => $from,
                'to' => $to,
                'bank' => $bank,
                'bank_norm' => $bankFilterNorm
            ]);

            // 1) Kardex - usando DATE para evitar problemas de timezone
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

            // Usar COALESCE para priorizar fecha_recibo sobre fecha_pago en filtros
            if ($from) {
                $kardexQuery->whereRaw('DATE(COALESCE(fecha_recibo, fecha_pago)) >= ?', [$from]);
            }
            if ($to) {
                $kardexQuery->whereRaw('DATE(COALESCE(fecha_recibo, fecha_pago)) <= ?', [$to]);
            }

            if ($bankFilterNorm) {
                $kardexQuery->where(function ($q) use ($bankFilterNorm) {
                    $q->where('banco_normalizado', $bankFilterNorm)
                        ->orWhere('banco', $bankFilterNorm)
                        ->orWhereRaw('UPPER(TRIM(banco)) = ?', [$bankFilterNorm]);
                });
            }

            $kardex = $kardexQuery->orderByRaw('COALESCE(fecha_recibo, fecha_pago) DESC')->get();

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

            Log::info('kardexNoConciliados: conteos base', [
                'kardex_count' => $kardex->count(),
                'recs_count'   => $recs->count(),
            ]);

            // Crear mapa de fingerprints de reconciliation_records
            $recFingerprints = [];
            foreach ($recs as $r) {
                $bn = $this->normalizeBank((string) $r->bank);
                $rn = $this->normalizeReceiptNumber((string) $r->reference);
                $amt = (float) $r->amount;
                $ymd = Carbon::parse($r->date)->format('Y-m-d');
                $recFingerprints[$this->makeFingerprint($bn, $rn, $amt, $ymd)] = true;
            }

            // 3) Buscar pendientes
            $pendientesUi = [];
            $i = 1;
            foreach ($kardex as $p) {
                $bn  = $p->banco_normalizado ?: $this->normalizeBank((string) $p->banco);
                $rn  = $p->numero_boleta_normalizada ?: $this->normalizeReceiptNumber((string) $p->numero_boleta);
                $amt = (float) $p->monto_pagado;

                $ymd = $this->extractDateFromKardex($p);

                $fp = $this->makeFingerprint($bn, $rn, $amt, $ymd);

                if (!isset($recFingerprints[$fp])) {
                    // (Opcional) log de muestra para diagnosticar (no loguear todos si hay miles)
                    if ($i <= 5) {
                        Log::debug('kardexNoConciliados: sin match fingerprint (muestra)', [
                            'kardex_id' => $p->id,
                            'fp'        => $fp,
                            'bn'        => $bn,
                            'rn'        => $rn,
                            'amt'       => $amt,
                            'ymd'       => $ymd,
                        ]);
                    }

                    $est = $p->estudiantePrograma()->with(['prospecto', 'programa'])->first();
                    $pendientesUi[] = [
                        'index'  => $i++,
                        'input'  => [
                            'carnet'  => $est->prospecto->carnet ?? '',
                            'alumno'  => $est->prospecto->nombre
                                ?? (($est->prospecto->primer_nombre ?? '') . ' ' . ($est->prospecto->primer_apellido ?? '')),
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
                        'debug_fingerprint' => $fp,
                    ];
                }
            }

            Log::info('kardexNoConciliados: resultado', [
                'pendientes' => count($pendientesUi),
            ]);

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
            Log::error('kardexNoConciliados: error', ['exception' => $e]);
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

    /** POST /api/conciliacion/import (rápido y funcional) */
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
            // 1) Importar filas
            $import = new BankStatementImport(uploaderId: $uploaderId);
            Excel::import($import, $request->file('file'));

            // 2) Tomar registros recién importados (o pendientes), no los ya conciliados
            $recs = ReconciliationRecord::where('uploaded_by', $uploaderId)
                ->where(function ($q) {
                    $q->whereNull('status')
                        ->orWhereIn('status', ['imported', 'rechazado']); // procesar los “pendientes”
                })
                ->get();

            $conciliados = 0;
            $montoConciliado = 0.0;
            $conciliadosList = [];

            foreach ($recs as $r) {
                // Validación mínima de campos
                if (!$r->bank || !$r->reference || !$r->amount || !$r->date) {
                    $r->update(['status' => 'rechazado']);
                    continue;
                }

                // Normalizaciones
                $bn  = $this->normalizeBank((string)$r->bank);
                $rn  = $this->normalizeReceiptNumber((string)$r->reference);
                $amt = (float)$r->amount;
                $ymd = Carbon::parse($r->date)->format('Y-m-d');

                // 3) Buscar el kardex a conciliar (acepta ambos estados de revisión)
                $kardex = KardexPago::where(function ($q) use ($bn) {
                    $q->where('banco_normalizado', $bn)
                        ->orWhereRaw('UPPER(TRIM(banco)) = ?', [$bn]);
                })
                    ->where(function ($q) use ($rn) {
                        // si ya está normalizado o si solo tienen el número original
                        $q->where('numero_boleta_normalizada', $rn)
                            ->orWhere('numero_boleta', $rn);
                    })
                    ->whereRaw("DATE(COALESCE(fecha_recibo, fecha_pago)) = ?", [$ymd])
                    ->where('monto_pagado', $amt)
                    ->whereIn('estado_pago', ['pendiente_revision', 'en_revision'])
                    ->first();

                if (!$kardex) {
                    // No hubo match exacto → lo dejamos como imported para futuro reproceso
                    // $r->update(['status' => 'imported']); // opcional; ya está en ese estado
                    continue;
                }

                // 4) Conciliar y actualizar todo en una transacción
                DB::transaction(function () use ($kardex, $r, $amt, &$conciliados, &$montoConciliado, &$conciliadosList) {
                    // a) Kardex aprobado
                    $kardex->update([
                        'estado_pago'   => 'aprobado',
                        'observaciones' => trim(($kardex->observaciones ?? '') . ' Conciliado automáticamente con estado de cuenta'),
                        'fecha_aprobacion' => now(),
                        'aprobado_por'  => auth()->id(), // opcional si existe la columna
                    ]);

                    // b) Cuota pagada (primero por relación; si falla, por SQL directo)
                    $updated = $kardex->cuota()->update([
                        'estado'   => 'pagado',
                        'paid_at'  => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);

                    if (!$updated && $kardex->cuota_id) {
                        DB::table('cuotas_programa_estudiante')
                            ->where('id', $kardex->cuota_id)
                            ->update([
                                'estado'     => 'pagado',
                                'paid_at'    => Carbon::now(),
                                'updated_at' => Carbon::now(),
                            ]);
                    }

                    // c) ReconciliationRecord marcado como conciliado
                    $r->update(['status' => 'conciliado']);

                    // d) Resumen
                    $conciliados++;
                    $montoConciliado += (float)$r->amount;
                    $conciliadosList[] = [
                        'kardex_id' => $kardex->id,
                        'cuota_id'  => $kardex->cuota_id,
                        'monto'     => (float)$r->amount,
                    ];
                });
            }

            return response()->json([
                'ok' => true,
                'message' => 'Importación y conciliación completadas',
                'summary' => [
                    'conciliados'       => $conciliados,
                    'monto_conciliado'  => $montoConciliado,
                    'created'           => $import->created,
                    'updated'           => $import->updated,
                    'skipped'           => $import->skipped,
                    'errors'            => $import->errors,
                ],
                'conciliados_list' => $conciliadosList,
                'errors_detail'    => $import->details,
            ]);
        } catch (\Throwable $e) {
            Log::error("ReconciliationController import error", ['exception' => $e]);
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

            if ($from) {
                $kardexQuery->whereRaw('DATE(COALESCE(fecha_recibo, fecha_pago)) >= ?', [$from]);
            }
            if ($to) {
                $kardexQuery->whereRaw('DATE(COALESCE(fecha_recibo, fecha_pago)) <= ?', [$to]);
            }

            if ($bankFilterNorm) {
                $kardexQuery->where(function ($q) use ($bankFilterNorm) {
                    $q->where('banco_normalizado', $bankFilterNorm)
                        ->orWhere('banco', $bankFilterNorm)
                        ->orWhereRaw('UPPER(TRIM(banco)) = ?', [$bankFilterNorm]);
                });
            }
            $kardex = $kardexQuery->orderByRaw('COALESCE(fecha_recibo, fecha_pago) DESC')->get();

            $recs = ReconciliationRecord::query()->select([
                'id',
                'bank',
                'reference',
                'amount',
                'date',
                'auth_number',
                'status'
            ])->get();

            $recMap = [];
            foreach ($recs as $r) {
                $bn  = $this->normalizeBank((string)$r->bank);
                $rn  = $this->normalizeReceiptNumber((string)$r->reference);
                $ymd = Carbon::parse($r->date)->format('Y-m-d');
                $recMap[$this->makeFingerprint($bn, $rn, (float)$r->amount, $ymd)] = $r;
            }

            $out = [];
            $i = 1;
            $sum = 0;

            foreach ($kardex as $p) {
                $bn  = $p->banco_normalizado ?: $this->normalizeBank((string)$p->banco);
                $rn  = $p->numero_boleta_normalizada ?: $this->normalizeReceiptNumber((string)$p->numero_boleta);
                $amt = (float)$p->monto_pagado;

                $ymd = $this->extractDateFromKardex($p);

                $fp = $this->makeFingerprint($bn, $rn, $amt, $ymd);
                if (!isset($recMap[$fp])) continue;

                $est = $p->estudiantePrograma()->with(['prospecto', 'programa'])->first();

                $out[] = [
                    'index' => $i++,
                    'input' => [
                        'carnet'  => $est->prospecto->carnet ?? '',
                        'alumno'  => $est->prospecto->nombre
                            ?? (($est->prospecto->primer_nombre ?? '') . ' ' . ($est->prospecto->primer_apellido ?? '')),
                        'carrera' => $est->programa->nombre_del_programa ?? '',
                        'banco'   => $p->banco ?? $bn,
                        'recibo'  => $p->numero_boleta ?? $rn,
                        'monto'   => $amt,
                        'fechaPago' => $ymd,
                        'autorizacion' => $recMap[$fp]->auth_number ?? null,
                    ],
                    'status'   => 'conciliado',
                    'message'  => 'Match exacto (bank, referencia, monto, fecha).',
                    'kardex_id' => $p->id,
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
            Log::error('kardexConciliados: error', ['exception' => $e]);
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
