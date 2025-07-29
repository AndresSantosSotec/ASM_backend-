<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;
use App\Models\Prospecto;

class ProspectoCarnetGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
    }

    public function test_generate_carnet_returns_first_when_none_exist()
    {
        $year = Carbon::now()->year;
        $this->assertEquals('ASM'.$year.'1', Prospecto::generateCarnet());
    }

    public function test_generate_carnet_increments_from_latest()
    {
        $year = Carbon::now()->year;
        Prospecto::create([
            'fecha' => Carbon::now()->toDateString(),
            'nombre_completo' => 'Test A',
            'telefono' => '111',
            'correo_electronico' => 'a@example.com',
            'genero' => 'M',
            'status' => 'Activo',
            'carnet' => 'ASM'.$year.'5',
        ]);
        Prospecto::create([
            'fecha' => Carbon::now()->toDateString(),
            'nombre_completo' => 'Test B',
            'telefono' => '222',
            'correo_electronico' => 'b@example.com',
            'genero' => 'M',
            'status' => 'Activo',
            'carnet' => 'ASM'.($year-1).'9',
        ]);
        Prospecto::create([
            'fecha' => Carbon::now()->toDateString(),
            'nombre_completo' => 'Test C',
            'telefono' => '333',
            'correo_electronico' => 'c@example.com',
            'genero' => 'M',
            'status' => 'Activo',
            'carnet' => 'ASM'.$year.'8',
        ]);

        $this->assertEquals('ASM'.$year.'9', Prospecto::generateCarnet());
    }
}
