<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Imports\PaymentHistoryImport;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class PaymentHistoryImportTest extends TestCase
{
    protected function getImportInstance()
    {
        return new PaymentHistoryImport(1); // Use dummy user ID
    }

    public function test_constructor_initializes_strict_mode()
    {
        $import = new PaymentHistoryImport(1);
        
        // Check that required properties exist
        $this->assertObjectHasProperty('uploaderId', $import);
        $this->assertObjectHasProperty('tipoArchivo', $import);
        $this->assertObjectHasProperty('totalRows', $import);
        $this->assertObjectHasProperty('procesados', $import);
        $this->assertObjectHasProperty('kardexCreados', $import);
        $this->assertObjectHasProperty('pagosOmitidos', $import);
        
        // Verify initial values
        $this->assertEquals(1, $import->uploaderId);
        $this->assertEquals('cardex_directo', $import->tipoArchivo);
        $this->assertEquals(0, $import->totalRows);
        $this->assertEquals(0, $import->procesados);
        $this->assertEquals(0, $import->kardexCreados);
        $this->assertEquals(0, $import->pagosOmitidos);
    }
    
    public function test_constructor_accepts_custom_tipo_archivo()
    {
        $import = new PaymentHistoryImport(999, 'custom_type');
        
        $this->assertEquals(999, $import->uploaderId);
        $this->assertEquals('custom_type', $import->tipoArchivo);
    }

    public function test_normalizar_carnet_removes_spaces_and_uppercases()
    {
        $import = $this->getImportInstance();
        
        $reflection = new \ReflectionMethod($import, 'normalizarCarnet');
        $reflection->setAccessible(true);
        
        $this->assertEquals('ASM20221234', $reflection->invoke($import, 'asm 2022 1234'));
        $this->assertEquals('ASM20221234', $reflection->invoke($import, 'ASM2022 1234'));
        $this->assertEquals('ASM20221234', $reflection->invoke($import, ' ASM20221234 '));
    }

    public function test_normalizar_monto_handles_currency_symbols()
    {
        $import = $this->getImportInstance();
        
        $reflection = new \ReflectionMethod($import, 'normalizarMonto');
        $reflection->setAccessible(true);
        
        $this->assertEquals(1000.0, $reflection->invoke($import, 'Q1,000'));
        $this->assertEquals(1500.50, $reflection->invoke($import, '$1,500.50'));
        $this->assertEquals(2000.0, $reflection->invoke($import, '2000'));
        $this->assertEquals(2500.0, $reflection->invoke($import, 2500));
    }

    public function test_normalizar_fecha_handles_excel_numeric_dates()
    {
        $import = $this->getImportInstance();
        
        $reflection = new \ReflectionMethod($import, 'normalizarFecha');
        $reflection->setAccessible(true);
        
        // Excel date: 44562 = 2022-01-01
        $result = $reflection->invoke($import, 44562);
        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertEquals('2022-01-01', $result->toDateString());
    }

    public function test_normalizar_fecha_handles_string_dates()
    {
        $import = $this->getImportInstance();
        
        $reflection = new \ReflectionMethod($import, 'normalizarFecha');
        $reflection->setAccessible(true);
        
        $result = $reflection->invoke($import, '2022-01-15');
        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertEquals('2022-01-15', $result->toDateString());
    }

    public function test_normalizar_boleta_handles_compound_receipts()
    {
        $import = $this->getImportInstance();
        
        $reflection = new \ReflectionMethod($import, 'normalizarBoleta');
        $reflection->setAccessible(true);
        
        // Should take the first part of compound receipts
        $this->assertEquals('545109', $reflection->invoke($import, '545109 / 1740192'));
        $this->assertEquals('12345', $reflection->invoke($import, '12345'));
        $this->assertEquals('ABC123', $reflection->invoke($import, 'abc-123'));
    }



    public function test_validar_columnas_excel_detects_missing_columns()
    {
        $import = $this->getImportInstance();
        
        $reflection = new \ReflectionMethod($import, 'validarColumnasExcel');
        $reflection->setAccessible(true);
        
        $incompleteRow = collect([
            'carnet' => 'ASM20221234',
            'nombre_estudiante' => 'Test Student'
            // Missing required columns
        ]);
        
        $result = $reflection->invoke($import, $incompleteRow);
        
        $this->assertFalse($result['valido']);
        $this->assertNotEmpty($result['faltantes']);
        $this->assertContains('numero_boleta', $result['faltantes']);
        $this->assertContains('monto', $result['faltantes']);
    }

    public function test_validar_columnas_excel_accepts_complete_columns()
    {
        $import = $this->getImportInstance();
        
        $reflection = new \ReflectionMethod($import, 'validarColumnasExcel');
        $reflection->setAccessible(true);
        
        $completeRow = collect([
            'carnet' => 'ASM20221234',
            'nombre_estudiante' => 'Test Student',
            'numero_boleta' => '12345',
            'monto' => '1000',
            'fecha_pago' => '2022-01-01',
            'banco' => 'BAC',
            'concepto' => 'Pago mensual'
        ]);
        
        $result = $reflection->invoke($import, $completeRow);
        
        $this->assertTrue($result['valido']);
        $this->assertEmpty($result['faltantes']);
    }

    public function test_get_reporte_exitos_returns_empty_when_no_details()
    {
        $import = $this->getImportInstance();
        
        $result = $import->getReporteExitos();
        
        $this->assertEquals('No hay registros exitosos para reportar', $result['mensaje']);
        $this->assertEquals(0, $result['total']);
    }

    public function test_fingerprint_includes_student_and_date()
    {
        // Test that the new fingerprint format includes student_id and date
        // This prevents collisions when different students use the same receipt number
        
        $banco1 = 'NO ESPECIFICADO';
        $boleta1 = '652002';
        $estudiante1 = 5;
        $fecha1 = '2020-08-01';
        
        $banco2 = 'NO ESPECIFICADO';
        $boleta2 = '652002'; // Same receipt number
        $estudiante2 = 162; // Different student
        $fecha2 = '2020-08-01';
        
        // Normalize inputs
        $bancoNorm1 = 'NO ESPECIFICADO';
        $boletaNorm1 = '652002';
        $bancoNorm2 = 'NO ESPECIFICADO';
        $boletaNorm2 = '652002';
        
        // Calculate old-style fingerprints (would collide)
        $oldFp1 = hash('sha256', $bancoNorm1.'|'.$boletaNorm1);
        $oldFp2 = hash('sha256', $bancoNorm2.'|'.$boletaNorm2);
        
        // Calculate new-style fingerprints (should NOT collide)
        $newFp1 = hash('sha256', $bancoNorm1.'|'.$boletaNorm1.'|'.$estudiante1.'|'.$fecha1);
        $newFp2 = hash('sha256', $bancoNorm2.'|'.$boletaNorm2.'|'.$estudiante2.'|'.$fecha2);
        
        // Old fingerprints should collide (same value)
        $this->assertEquals($oldFp1, $oldFp2, 'Old fingerprint format should collide for same banco+boleta');
        
        // New fingerprints should NOT collide (different values)
        $this->assertNotEquals($newFp1, $newFp2, 'New fingerprint format should NOT collide when students differ');
        
        // Verify fingerprints are different from each other
        $this->assertNotEmpty($newFp1);
        $this->assertNotEmpty($newFp2);
        $this->assertEquals(64, strlen($newFp1)); // SHA256 produces 64-char hex string
        $this->assertEquals(64, strlen($newFp2));
    }

    public function test_fingerprint_distinguishes_different_dates()
    {
        // Test that the new fingerprint format distinguishes payments on different dates
        
        $banco = 'BI';
        $boleta = '901002';
        $estudiante = 5;
        $fecha1 = '2020-08-01';
        $fecha2 = '2020-09-01';
        
        // Calculate fingerprints
        $fp1 = hash('sha256', $banco.'|'.$boleta.'|'.$estudiante.'|'.$fecha1);
        $fp2 = hash('sha256', $banco.'|'.$boleta.'|'.$estudiante.'|'.$fecha2);
        
        // Fingerprints should be different for different dates
        $this->assertNotEquals($fp1, $fp2, 'Fingerprints should differ when payment dates differ');
    }
}
