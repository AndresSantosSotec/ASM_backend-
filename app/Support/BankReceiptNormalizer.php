<?php

namespace App\Support;

trait BankReceiptNormalizer
{
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

    protected function bankAliases(string $bankNorm): array
    {
        return match ($bankNorm) {
            'BANCO INDUSTRIAL' => ['BI','INDUSTRIAL','BANCO INDUSTRIAL'],
            'BANRURAL'         => ['BAN RURAL','RURAL','BANRURAL'],
            'BAM'              => ['BANCO AGROMERCANTIL','BAM'],
            'G&T CONTINENTAL'  => ['G&T','G Y T','GYT','G&T CONTINENTAL'],
            'PROMERICA'        => ['PROMERICA'],
            default            => [$bankNorm],
        };
    }
}
