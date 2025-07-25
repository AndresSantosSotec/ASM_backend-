<?php

namespace Tests\Unit;

use App\Imports\InscripcionesImport;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class InscripcionesImportTest extends TestCase
{
    public function test_prepare_for_validation_fills_defaults()
    {
        $import = new InscripcionesImport();

        $data = [
            'carnet' => 'A1',
            'nombre' => 'John',
            'apellido' => '',
            'email' => 'bad-email',
            'fecha_de_inscripcion' => 'not a date',
            'cumpleanos' => '',
        ];

        $result = $import->prepareForValidation($data, 1);

        $this->assertEquals('Desconocido', $result['apellido']);
        $this->assertStringStartsWith('sin-email-', $result['email']);
        $this->assertEquals(Carbon::now()->toDateString(), $result['fecha_de_inscripcion']);
        $this->assertEquals('2000-01-01', $result['cumpleanos']);
    }
}
