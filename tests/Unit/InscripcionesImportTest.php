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
            'telefono' => 12345,
            'fecha_de_inscripcion' => 'not a date',
            'cumpleanos' => '',
        ];

        $result = $import->prepareForValidation($data, 1);

        $this->assertEquals('Desconocido', $result['apellido']);
        $this->assertStringStartsWith('sin-email-', $result['email']);
        $this->assertSame('12345', $result['telefono']);
        $this->assertEquals(Carbon::now()->toDateString(), $result['fecha_de_inscripcion']);
        $this->assertEquals('2000-01-01', $result['cumpleanos']);
    }

    public function test_program_code_normalization_and_dia_truncation()
    {
        $import = new InscripcionesImport();

        $normMethod = new \ReflectionMethod($import, 'normalizeProgramaCodigo');
        $normMethod->setAccessible(true);

        $this->assertEquals('MBA', $normMethod->invoke($import, 'MBA 21'));
        $this->assertEquals('MMK', $normMethod->invoke($import, 'MMK18'));
        $this->assertEquals('MHTM', $normMethod->invoke($import, 'MRRHH21'));

        $diaMethod = new \ReflectionMethod($import, 'sanitizeDiaEstudio');
        $diaMethod->setAccessible(true);

        $truncated = $diaMethod->invoke($import, 'Lunes, Miércoles y Sábado');
        $this->assertSame(20, mb_strlen($truncated));
    }
}
