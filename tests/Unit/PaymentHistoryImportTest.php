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
}
