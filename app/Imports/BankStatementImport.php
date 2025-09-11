<?php

namespace App\Imports;

use App\Models\ReconciliationRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class BankStatementImport implements OnEachRow, WithHeadingRow, WithChunkReading
{
    public int $created = 0, $updated = 0, $skipped = 0, $errors = 0;
    public array $details = [];
    private int $rowCounter = 0;

    public function __construct(private int $uploaderId) {}

    public function headingRow(): int { return 1; }
    public function chunkSize(): int { return 1000; }

    public function onRow(Row $row)
    {
        $this->rowCounter++;
        $rowNum = $this->rowCounter + 1; // +1 porque el encabezado está en la fila 1

        try {
            $r = $row->toArray();

            $bank       = $this->pick($r, ['banco','bank','entidad']);
            $reference  = $this->pick($r, ['referencia','referencia/boleta','referencia_boleta','boleta','numero de recibo','número de recibo','no. referencia','reference']);
            $amountRaw  = $this->pick($r, ['monto','importe','valor','amount']);
            $dateRaw    = $this->pick($r, ['fecha','fecha de pago','fecha_pago','date']);
            $auth       = $this->pick($r, ['numero de autorizacion','número de autorización','autorizacion','auth','auth_number']);

            // Si todos los campos están vacíos, saltar la fila
            if ($bank === null && $reference === null && $amountRaw === null && $dateRaw === null) {
                $this->skipped++;
                return;
            }

            // Validar campos requeridos
            if (!$bank || !$reference || $amountRaw === null || !$dateRaw) {
                $this->errors++;
                $this->details[] = ['row' => $rowNum, 'message' => 'Faltan campos requeridos (Banco, Referencia, Monto, Fecha)'];
                Log::warning('Recon import: campos faltantes', compact('rowNum','bank','reference','amountRaw','dateRaw'));
                return;
            }

            // Normalizar y validar datos
            $bankNorm  = $this->normalizeBank((string)$bank);
            $refNorm   = $this->normalizeReceiptNumber((string)$reference);
            $amount    = $this->toNumber($amountRaw);
            $dateYmd   = $this->parseDate($dateRaw);

            if ($amount === null || $dateYmd === null) {
                $this->errors++;
                $this->details[] = ['row' => $rowNum, 'message' => 'Monto o Fecha inválidos'];
                Log::warning('Recon import: monto/fecha inválidos', compact('rowNum','amountRaw','dateRaw'));
                return;
            }

            $fp = $this->makeFingerprint($bankNorm, $refNorm, $amount, $dateYmd);

            // Crear o actualizar registro
            $model = ReconciliationRecord::updateOrCreate(
                ['fingerprint' => $fp],
                [
                    'bank'                  => (string)$bank,
                    'bank_normalized'       => $bankNorm,
                    'reference'             => (string)$reference,
                    'reference_normalized'  => $refNorm,
                    'amount'                => $amount,
                    'date'                  => $dateYmd,
                    'auth_number'           => $auth,
                    'status'                => 'imported',
                    'uploaded_by'           => $this->uploaderId,
                ]
            );

            $model->wasRecentlyCreated ? $this->created++ : $this->updated++;

            // Guardar resumen en sesión cada 100 filas procesadas
            if ($this->rowCounter % 100 === 0) {
                $this->saveProgressToSession();
            }

        } catch (Throwable $e) {
            $this->errors++;
            $this->details[] = ['row' => $rowNum, 'message' => $e->getMessage()];
            Log::error('Recon import: excepción por fila', ['row' => $rowNum, 'error' => $e->getMessage()]);
        }
    }

    public function __destruct()
    {
        // Guardar resumen final al terminar el procesamiento
        $this->saveProgressToSession();

        Log::info('Reconciliation import finalizado', [
            'created' => $this->created, 'updated' => $this->updated,
            'skipped' => $this->skipped, 'errors' => $this->errors
        ]);
    }

    private function saveProgressToSession(): void
    {
        session()->put('reconciliation_import_summary', [
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'errors'  => $this->errors,
        ]);
        session()->put('reconciliation_import_details', $this->details);
    }

    /** -------- Helpers -------- */

    private function pick($row, array $candidates)
    {
        foreach ($candidates as $k) {
            foreach ([
                $k,
                strtolower($k),
                str_replace(' ', '_', strtolower($k)),
                str_replace([' ', '.'], '', strtolower($k)),
            ] as $cand) {
                if (isset($row[$cand]) && $row[$cand] !== '' && $row[$cand] !== null) return $row[$cand];
            }
        }
        return null;
    }

    private function normalizeReceiptNumber(string $n): string
    {
        $n = mb_strtoupper($n, 'UTF-8');
        return preg_replace('/[^A-Z0-9]/u', '', $n);
    }

    private function normalizeBank(string $bank): string
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

    private function makeFingerprint(string $bankNorm, string $receiptNorm, float $amount, string $dateYmd): string
    {
        return $bankNorm.'|'.$receiptNorm.'|'.number_format($amount, 2, '.', '').'|'.$dateYmd;
    }

    private function toNumber($v): ?float
    {
        if (is_numeric($v)) return (float)$v;
        $s = trim((string)$v);
        if ($s === '') return null;
        // quita símbolos y deja dígitos + separadores
        $s = preg_replace('/[^\d,.\-]/', '', $s);

        if (strpos($s, ',') !== false && strpos($s, '.') !== false) {
            // patrón "1.234,56" => quita puntos (miles) y usa coma como decimal
            if (preg_match('/\.\d{3}(,|$)/', $s)) {
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
            } else {
                // patrón "1,234.56"
                $s = str_replace(',', '', $s);
            }
        } elseif (strpos($s, ',') !== false) {
            // "1234,56" => decimal con coma
            $s = str_replace(',', '.', $s);
        }
        return is_numeric($s) ? (float)$s : null;
    }

    private function parseDate($v): ?string
    {
        if ($v instanceof \DateTimeInterface) return Carbon::instance($v)->format('Y-m-d');

        if (is_numeric($v)) {
            try {
                $dt = ExcelDate::excelToDateTimeObject($v);
                return Carbon::instance($dt)->format('Y-m-d');
            } catch (\Throwable) {}
        }

        $s = trim((string)$v);
        if ($s === '') return null;

        foreach (['Y-m-d','d/m/Y','d-m-Y','m/d/Y'] as $fmt) {
            try {
                return Carbon::createFromFormat($fmt, $s)->format('Y-m-d');
            } catch (\Throwable) {}
        }

        try {
            return Carbon::parse($s)->format('Y-m-d');
        } catch (\Throwable) {}

        return null;
    }
}
