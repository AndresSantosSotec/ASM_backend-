<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\KardexPago;

class DuplicatePaymentPreventionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test normalization functions work correctly
     */
    public function test_normalization_functions()
    {
        // Test bank normalization
        $this->assertEquals('BANCO INDUSTRIAL', KardexPago::normalizeBanco(' banco industrial '));
        $this->assertEquals('BAC', KardexPago::normalizeBanco('bac'));

        // Test receipt number normalization
        $this->assertEquals('ABC123', KardexPago::normalizeNumeroBoleta('ABC-123'));
        $this->assertEquals('XYZ456', KardexPago::normalizeNumeroBoleta(' xyz 456 '));
        $this->assertEquals('1234567890', KardexPago::normalizeNumeroBoleta('1234-567-890'));
        $this->assertEquals('ABCD1234', KardexPago::normalizeNumeroBoleta('ABCD@#$%1234'));
    }

    /**
     * Test receipt existence check
     */
    public function test_receipt_exists_check()
    {
        // Create a payment record
        KardexPago::create([
            'estudiante_programa_id' => 1,
            'cuota_id' => 1,
            'fecha_pago' => now(),
            'monto_pagado' => 100.00,
            'numero_boleta' => 'ABC123',
            'banco' => 'Banco Industrial',
            'banco_norm' => 'BANCO INDUSTRIAL',
            'numero_boleta_norm' => 'ABC123',
            'estado_pago' => 'aprobado'
        ]);

        // Check that it exists with different formatting
        $existing = KardexPago::receiptExists('banco industrial', 'abc-123');
        $this->assertNotNull($existing);

        // Check that non-existent receipt returns null
        $nonExisting = KardexPago::receiptExists('Banco BAC', 'XYZ789');
        $this->assertNull($nonExisting);
    }

    /**
     * Test file hash calculation
     */
    public function test_file_hash_calculation()
    {
        // Create a temporary test file
        $tempFile = tempnam(sys_get_temp_dir(), 'test_receipt');
        file_put_contents($tempFile, 'test content for receipt');
        
        $hash = KardexPago::calculateFileHash($tempFile);
        
        // Verify it's a valid SHA-256 hash (64 characters)
        $this->assertEquals(64, strlen($hash));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
        
        // Verify same content produces same hash
        $hash2 = KardexPago::calculateFileHash($tempFile);
        $this->assertEquals($hash, $hash2);
        
        // Clean up
        unlink($tempFile);
    }

    /**
     * Test file hash existence check
     */
    public function test_file_hash_exists_check()
    {
        $testHash = hash('sha256', 'test file content');
        
        // Create a payment record with file hash
        KardexPago::create([
            'estudiante_programa_id' => 1,
            'cuota_id' => 1,
            'fecha_pago' => now(),
            'monto_pagado' => 100.00,
            'numero_boleta' => 'ABC123',
            'banco' => 'Banco Industrial',
            'file_sha256' => $testHash,
            'estado_pago' => 'aprobado'
        ]);

        // Check that file hash exists for this student
        $existing = KardexPago::fileHashExists(1, $testHash);
        $this->assertNotNull($existing);

        // Check that same hash for different student doesn't match (allowed)
        $notExisting = KardexPago::fileHashExists(2, $testHash);
        $this->assertNull($notExisting);
    }
}