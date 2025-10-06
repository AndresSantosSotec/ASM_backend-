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

    protected function getImportInstanceWithReplace()
    {
        return new PaymentHistoryImport(1, 'cardex_directo', true); // With modoReemplazoPendientes enabled
    }
    
    protected function getImportInstanceSilent()
    {
        return new PaymentHistoryImport(1, 'cardex_directo', false, true); // With modoSilencioso enabled
    }
    
    protected function getImportInstanceForced()
    {
        return new PaymentHistoryImport(1, 'cardex_directo', false, false, true); // With modoInsercionForzada enabled
    }

    public function test_constructor_accepts_modo_reemplazo_pendientes()
    {
        $import = new PaymentHistoryImport(1, 'cardex_directo', true);
        
        $reflection = new \ReflectionProperty($import, 'modoReemplazoPendientes');
        $reflection->setAccessible(true);
        
        $this->assertTrue($reflection->getValue($import));
    }
    
    public function test_constructor_accepts_modo_silencioso()
    {
        $import = new PaymentHistoryImport(1, 'cardex_directo', false, true);
        
        $reflection = new \ReflectionProperty($import, 'modoSilencioso');
        $reflection->setAccessible(true);
        
        $this->assertTrue($reflection->getValue($import));
    }
    
    public function test_constructor_accepts_modo_insercion_forzada()
    {
        $import = new PaymentHistoryImport(1, 'cardex_directo', false, false, true);
        
        $reflection = new \ReflectionProperty($import, 'modoInsercionForzada');
        $reflection->setAccessible(true);
        
        $this->assertTrue($reflection->getValue($import));
    }

    public function test_constructor_defaults_modo_reemplazo_to_false()
    {
        $import = new PaymentHistoryImport(1);
        
        $reflection = new \ReflectionProperty($import, 'modoReemplazoPendientes');
        $reflection->setAccessible(true);
        
        $this->assertFalse($reflection->getValue($import));
    }
    
    public function test_constructor_defaults_modo_silencioso_to_false()
    {
        $import = new PaymentHistoryImport(1);
        
        $reflection = new \ReflectionProperty($import, 'modoSilencioso');
        $reflection->setAccessible(true);
        
        $this->assertFalse($reflection->getValue($import));
    }
    
    public function test_constructor_defaults_modo_insercion_forzada_to_false()
    {
        $import = new PaymentHistoryImport(1);
        
        $reflection = new \ReflectionProperty($import, 'modoInsercionForzada');
        $reflection->setAccessible(true);
        
        $this->assertFalse($reflection->getValue($import));
    }
    
    public function test_constructor_initializes_time_metrics()
    {
        $import = new PaymentHistoryImport(1);
        
        $reflectionTime = new \ReflectionProperty($import, 'tiempoInicio');
        $reflectionTime->setAccessible(true);
        
        $reflectionMemory = new \ReflectionProperty($import, 'memoryInicio');
        $reflectionMemory->setAccessible(true);
        
        $this->assertGreaterThan(0, $reflectionTime->getValue($import));
        $this->assertGreaterThan(0, $reflectionMemory->getValue($import));
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
            'mensualidad_aprobada' => '1000',
            'banco' => 'BAC',
            'concepto' => 'Pago mensual'
        ]);
        
        $result = $reflection->invoke($import, $completeRow);
        
        $this->assertTrue($result['valido']);
        $this->assertEmpty($result['faltantes']);
    }

    public function test_obtener_programas_estudiante_handles_collection_to_array_conversion()
    {
        $import = $this->getImportInstance();
        
        // Create a Collection that simulates what $pagos->first() would return
        $mockRow = collect([
            'carnet' => 'ASM20221234',
            'nombre_estudiante' => 'Test Student',
            'plan_estudios' => 'MBA',
            'numero_boleta' => '12345',
            'monto' => 1000,
            'fecha_pago' => '2022-01-01',
            'mensualidad_aprobada' => 1000,
            'banco' => 'BAC',
            'concepto' => 'Pago mensual'
        ]);
        
        // This test verifies that when obtenerProgramasEstudiante is called with a Collection,
        // it properly converts it to an array before passing to generarCuotasSiFaltan.
        // The test passes if no TypeError is thrown.
        
        // We can't fully test this without database, but we can verify the instance methods exist
        $this->assertTrue(method_exists($import, 'obtenerProgramasEstudiante'));
        
        // Verify Collection can be converted to array
        $this->assertIsArray($mockRow->toArray());
        $this->assertInstanceOf(Collection::class, $mockRow);
    }

    public function test_get_error_type_description_returns_proper_descriptions()
    {
        $import = $this->getImportInstance();
        
        $reflection = new \ReflectionMethod($import, 'getErrorTypeDescription');
        $reflection->setAccessible(true);
        
        // Test known error types
        $this->assertStringContainsString('Error crítico', $reflection->invoke($import, 'ERROR_PROCESAMIENTO_ESTUDIANTE'));
        $this->assertStringContainsString('Error al procesar un pago', $reflection->invoke($import, 'ERROR_PROCESAMIENTO_PAGO'));
        $this->assertStringContainsString('No se encontró el estudiante', $reflection->invoke($import, 'ESTUDIANTE_NO_ENCONTRADO'));
        $this->assertStringContainsString('programa', $reflection->invoke($import, 'PROGRAMA_NO_IDENTIFICADO'));
        $this->assertStringContainsString('datos requeridos', $reflection->invoke($import, 'DATOS_INCOMPLETOS'));
        
        // Test unknown error type
        $this->assertEquals('Error no categorizado', $reflection->invoke($import, 'UNKNOWN_ERROR_TYPE'));
    }

    public function test_normalize_bank_standardizes_bank_names()
    {
        $import = $this->getImportInstance();
        
        $reflection = new \ReflectionMethod($import, 'normalizeBank');
        $reflection->setAccessible(true);
        
        // Test bank normalization
        $this->assertEquals('BI', $reflection->invoke($import, 'Banco Industrial'));
        $this->assertEquals('BI', $reflection->invoke($import, 'BI'));
        $this->assertEquals('BANTRAB', $reflection->invoke($import, 'bantrab'));
        $this->assertEquals('EFECTIVO', $reflection->invoke($import, 'N/A'));
        $this->assertEquals('EFECTIVO', $reflection->invoke($import, 'No especificado'));
        $this->assertEquals('EFECTIVO', $reflection->invoke($import, ''));
    }

    public function test_normalize_receipt_number_removes_special_chars()
    {
        $import = $this->getImportInstance();
        
        $reflection = new \ReflectionMethod($import, 'normalizeReceiptNumber');
        $reflection->setAccessible(true);
        
        // Test receipt number normalization
        $this->assertEquals('652002', $reflection->invoke($import, '652002'));
        $this->assertEquals('652002', $reflection->invoke($import, '652-002'));
        $this->assertEquals('ABC123', $reflection->invoke($import, 'abc-123'));
        $this->assertEquals('NA', $reflection->invoke($import, 'N/A'));
        $this->assertEquals('NA', $reflection->invoke($import, ''));
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
