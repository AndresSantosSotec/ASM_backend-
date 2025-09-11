<?php
// app/Imports/BankStatementImport.php

namespace App\Imports;

use App\Models\ReconciliationRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Carbon\Carbon;

class BankStatementImport implements ToCollection, WithHeadingRow
{
    /** Normaliza número de boleta/referencia */
    protected function normalizeReceiptNumber(string $n): string
    {
        $n = mb_strtoupper($n, 'UTF-8');
        return preg_replace('/[^A-Z0-9]/u', '', $n);
    }

    /** Normaliza banco a canon */
    protected function normalizeBank(string $bank): string
    {
        $b = mb_strtoupper(trim($bank), 'UTF-8');
        $map = [
            'BANCO INDUSTRIAL' => ['BI','BANCO INDUSTRIAL','INDUSTRIAL'],
            'BANRURAL'         => ['BANRURAL','BAN RURAL','RURAL'],
            'BAM'              => ['BAM','BANCO AGROMERCANTIL'],
            'G&T CONTINENTAL'  => ['G&T','G Y T','GYT','G&T CONTINENTAL'],
            'PROMERICA'        => ['PROMERICA'],
        ];
        foreach ($map as $canon => $aliases) {
            if (in_array($b, $aliases, true)) return $canon;
        }
        return $b;
    }

    /** Convierte Q-montos comunes GT a float (1.234,56 | 1,234.56 | 1234) */
    protected function parseAmount($v): float
    {
        if (is_null($v)) return 0.0;
        if (is_numeric($v)) return (float) $v;
        $s = strtoupper(trim((string) $v));
        $s = str_replace(['Q',' ','\u{00A0}'], '', $s);
        if (str_contains($s, ',') && str_contains($s, '.')) {
            // "1.234,56" -> "1234.56"
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } else {
            // "1,234" -> "1.234"
            $s = str_replace(',', '.', $s);
        }
        return (float) (is_numeric($s) ? $s : 0.0);
    }

    /** Acepta yyyy-mm-dd, dd/mm/yyyy, Excel serial, etc. */
    protected function parseDate($v): ?string
    {
        if (is_null($v) || $v === '') return null;

        // Excel serial
        if (is_numeric($v)) {
            try {
                $dt = ExcelDate::excelToDateTimeObject((float)$v);
                return Carbon::instance($dt)->format('Y-m-d');
            } catch (\Throwable $e) {}
        }

        $s = trim((string) $v);

        // dd/mm/yyyy
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $s, $m)) {
            return Carbon::createFromFormat('d/m/Y', $s)->format('Y-m-d');
        }

        // yyyy-mm-dd
        if (preg_match('#^\d{4}-\d{2}-\d{2}$#', $s)) {
            return Carbon::parse($s)->format('Y-m-d');
        }

        // Intento general
        try {
            return Carbon::parse($s)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Mapea encabezados variados a claves canónicas */
    protected function mapHeader(string $h): ?string
    {
        $h = mb_strtolower(trim($h), 'UTF-8');
        return match (true) {
            str_contains($h, 'banco')                       => 'bank',
            str_contains($h, 'referencia')                  => 'reference',
            str_contains($h, 'boleta')                      => 'reference',
            str_contains($h, 'volante') || str_contains($h, 'voucher') => 'reference',
            str_contains($h, 'monto') || str_contains($h, 'importe')    => 'amount',
            str_contains($h, 'fecha')                       => 'date',
            str_contains($h, 'autoriz')                     => 'auth_number',
            default                                         => null,
        };
    }

    /** Construye fingerprint lógico: bank|reference|amount|date */
    protected function makeFingerprint(string $bankNorm, string $refNorm, float $amount, ?string $dateYmd): string
    {
        $amt = number_format($amount, 2, '.', '');
        $dateYmd = $dateYmd ?: '0000-00-00';
        return "{$bankNorm}|{$refNorm}|{$amt}|{$dateYmd}";
    }

    public function headingRow(): int
    {
        return 1; // Primera fila como encabezados
    }

    public function collection(Collection $rows)
    {
        // Normaliza encabezados a canónicos
        // $rows viene como colección de arrays asociativos (por WithHeadingRow)
        $created = 0; $updated = 0; $skipped = 0; $errors = 0;

        $userId = Auth::id();

        foreach ($rows as $row) {
            try {
                // 1) Renombra claves a canónicas
                $canonical = [
                    'bank'        => null,
                    'reference'   => null,
                    'amount'      => null,
                    'date'        => null,
                    'auth_number' => null,
                ];

                foreach ($row as $key => $val) {
                    $mapped = $this->mapHeader((string)$key);
                    if ($mapped && array_key_exists($mapped, $canonical)) {
                        $canonical[$mapped] = $val;
                    }
                }

                // Requeridos mínimos
                if (empty($canonical['bank']) || empty($canonical['reference']) || is_null($canonical['amount'])) {
                    $skipped++;
                    continue;
                }

                // 2) Parseos/normalizaciones
                $bankNorm = $this->normalizeBank((string)$canonical['bank']);
                $refNorm  = $this->normalizeReceiptNumber((string)$canonical['reference']);
                $amount   = $this->parseAmount($canonical['amount']);
                $dateYmd  = $this->parseDate($canonical['date']);

                // 3) Fingerprint
                $finger  = $this->makeFingerprint($bankNorm, $refNorm, $amount, $dateYmd);

                // 4) Upsert por fingerprint (ignora prospecto_id)
                $payload = [
                    'bank'                 => (string)$canonical['bank'],
                    'reference'            => (string)$canonical['reference'],
                    'amount'               => $amount,
                    'date'                 => $dateYmd,
                    'auth_number'          => $canonical['auth_number'] ? (string)$canonical['auth_number'] : null,
                    'status'               => 'uploaded', // o 'pendiente_revision'
                    'uploaded_by'          => $userId,
                    // derivados
                    'bank_normalized'      => $bankNorm,
                    'reference_normalized' => $refNorm,
                    'fingerprint'          => $finger,
                ];

                $rec = ReconciliationRecord::query()->where('fingerprint', $finger)->first();

                if ($rec) {
                    $rec->fill($payload);
                    if ($rec->isDirty()) {
                        $rec->save();
                        $updated++;
                    } else {
                        $skipped++; // ya existía idéntico
                    }
                } else {
                    ReconciliationRecord::create($payload);
                    $created++;
                }
            } catch (\Throwable $e) {
                $errors++;
            }
        }

        // Puedes guardar un log/summary si quieres
        session()->flash('reconciliation_import_summary', compact('created','updated','skipped','errors'));
    }
}

