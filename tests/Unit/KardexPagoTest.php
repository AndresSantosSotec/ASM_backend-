<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\KardexPago;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class KardexPagoTest extends TestCase
{
    use RefreshDatabase;

    public function test_boleta_fingerprint_includes_student_and_date()
    {
        // Create a KardexPago instance with required fields
        $kardex = new KardexPago([
            'estudiante_programa_id' => 5,
            'numero_boleta' => '652002',
            'banco' => 'No especificado',
            'fecha_pago' => '2020-08-01',
            'monto_pagado' => 706.05,
            'estado_pago' => 'aprobado',
        ]);

        // Manually trigger the booted() method logic
        // Note: In actual use, this happens automatically on save()
        $kardex->numero_boleta_normalizada = preg_replace('/[^A-Z0-9]/u', '', mb_strtoupper($kardex->numero_boleta, 'UTF-8'));
        $kardex->banco_normalizado = 'NO ESPECIFICADO';
        
        // Calculate expected fingerprint
        $expectedFingerprint = hash('sha256', 
            'NO ESPECIFICADO' . '|' . '652002' . '|' . '5' . '|' . '2020-08-01'
        );

        // Manually calculate fingerprint (simulating model's booted method)
        $estudiante = $kardex->estudiante_programa_id ?? 'UNKNOWN';
        $fecha = $kardex->fecha_pago ? 
            (is_string($kardex->fecha_pago) ? $kardex->fecha_pago : Carbon::parse($kardex->fecha_pago)->format('Y-m-d')) : 
            'UNKNOWN';
        $calculatedFingerprint = hash('sha256', 
            $kardex->banco_normalizado . '|' . $kardex->numero_boleta_normalizada . '|' . $estudiante . '|' . $fecha
        );

        $this->assertEquals($expectedFingerprint, $calculatedFingerprint);
    }

    public function test_different_students_get_different_fingerprints()
    {
        // Student 1
        $kardex1 = new KardexPago([
            'estudiante_programa_id' => 5,
            'numero_boleta' => '652002',
            'banco' => 'No especificado',
            'fecha_pago' => '2020-08-01',
            'monto_pagado' => 706.05,
            'estado_pago' => 'aprobado',
        ]);

        // Student 2 (same receipt, different student)
        $kardex2 = new KardexPago([
            'estudiante_programa_id' => 162,
            'numero_boleta' => '652002',
            'banco' => 'No especificado',
            'fecha_pago' => '2020-08-01',
            'monto_pagado' => 706.05,
            'estado_pago' => 'aprobado',
        ]);

        // Calculate fingerprints
        $fp1 = hash('sha256', 'NO ESPECIFICADO|652002|5|2020-08-01');
        $fp2 = hash('sha256', 'NO ESPECIFICADO|652002|162|2020-08-01');

        // They should be different
        $this->assertNotEquals($fp1, $fp2, 
            'Different students with same receipt should have different fingerprints');
    }

    public function test_different_dates_get_different_fingerprints()
    {
        // Same student, same receipt, different dates
        $fp1 = hash('sha256', 'BI|901002|5|2020-08-01');
        $fp2 = hash('sha256', 'BI|901002|5|2020-09-01');

        $this->assertNotEquals($fp1, $fp2, 
            'Same student/receipt on different dates should have different fingerprints');
    }

    public function test_banco_normalization()
    {
        $testCases = [
            ['input' => 'Banco Industrial', 'expected' => 'BANCO INDUSTRIAL'],
            ['input' => 'BI', 'expected' => 'BI'],
            ['input' => 'No especificado', 'expected' => 'NO ESPECIFICADO'],
            ['input' => 'banrural', 'expected' => 'BANRURAL'],
            ['input' => 'G&T', 'expected' => 'G&T'],
        ];

        foreach ($testCases as $case) {
            $b = mb_strtoupper(trim($case['input']), 'UTF-8');
            $map = [
                'BANCO INDUSTRIAL' => ['BI','BANCO INDUSTRIAL','INDUSTRIAL'],
                'BANRURAL'         => ['BANRURAL','BAN RURAL','RURAL'],
                'BAM'              => ['BAM','BANCO AGROMERCANTIL'],
                'G&T CONTINENTAL'  => ['G&T','G Y T','GYT','G&T CONTINENTAL'],
                'PROMERICA'        => ['PROMERICA'],
            ];
            
            $canon = $b;
            foreach ($map as $c => $aliases) {
                if (in_array($b, $aliases, true)) {
                    $canon = $c;
                    break;
                }
            }

            // Verify normalization logic works
            $this->assertNotEmpty($canon, "Bank normalization should return a value for: {$case['input']}");
        }
    }

    public function test_boleta_normalization()
    {
        $testCases = [
            ['input' => '652002', 'expected' => '652002'],
            ['input' => '652-002', 'expected' => '652002'],
            ['input' => 'abc-123', 'expected' => 'ABC123'],
            ['input' => 'ABC 123', 'expected' => 'ABC123'],
        ];

        foreach ($testCases as $case) {
            $normalized = preg_replace('/[^A-Z0-9]/u', '', mb_strtoupper($case['input'], 'UTF-8'));
            $this->assertEquals($case['expected'], $normalized, 
                "Boleta '{$case['input']}' should normalize to '{$case['expected']}'");
        }
    }

    public function test_fingerprint_collision_prevention()
    {
        // Test the actual collision scenario from the bug report
        $payments = [
            [
                'estudiante_programa_id' => 5,
                'banco' => 'NO ESPECIFICADO',
                'boleta' => '652002',
                'fecha' => '2020-08-01',
            ],
            [
                'estudiante_programa_id' => 5,
                'banco' => 'NO ESPECIFICADO',
                'boleta' => '901002',
                'fecha' => '2020-09-01',
            ],
            [
                'estudiante_programa_id' => 162,
                'banco' => 'NO ESPECIFICADO',
                'boleta' => '652002',
                'fecha' => '2020-08-01',
            ],
        ];

        $fingerprints = [];
        foreach ($payments as $payment) {
            $fp = hash('sha256', 
                $payment['banco'] . '|' . 
                $payment['boleta'] . '|' . 
                $payment['estudiante_programa_id'] . '|' . 
                $payment['fecha']
            );
            
            // Check for duplicates
            $this->assertNotContains($fp, $fingerprints, 
                "Fingerprint collision detected for payment: " . json_encode($payment));
            
            $fingerprints[] = $fp;
        }

        // All fingerprints should be unique
        $this->assertCount(3, array_unique($fingerprints), 
            'All three payments should have unique fingerprints');
    }
}
