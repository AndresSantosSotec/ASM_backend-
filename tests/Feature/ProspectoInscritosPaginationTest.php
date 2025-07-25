<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\{User, Prospecto, Programa, Course, EstudiantePrograma};
use Carbon\Carbon;

class ProspectoInscritosPaginationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
    }

    public function test_endpoint_returns_paginated_inscritos()
    {
        $user = User::create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password_hash' => bcrypt('secret'),
            'first_name' => 'Admin',
            'last_name' => 'User',
        ]);

        $this->actingAs($user);

        $programa = Programa::create([
            'nombre_del_programa' => 'Prog',
            'meses' => 10,
        ]);

        $course = Course::create([
            'name' => 'Test Course',
            'code' => 'TC1',
            'area' => 'common',
            'credits' => 3,
            'start_date' => Carbon::now()->toDateString(),
            'end_date' => Carbon::now()->addMonth()->toDateString(),
            'schedule' => 'X',
            'duration' => '1h',
            'status' => 'draft',
        ]);
        $programa->courses()->attach($course->id);

        for ($i = 0; $i < 3; $i++) {
            $prospecto = Prospecto::create([
                'fecha' => Carbon::now()->toDateString(),
                'nombre_completo' => 'P'.$i,
                'telefono' => '12345'.$i,
                'correo_electronico' => "p{$i}@example.com",
                'genero' => 'M',
                'status' => 'Inscrito',
            ]);

            EstudiantePrograma::create([
                'prospecto_id' => $prospecto->id,
                'programa_id' => $programa->id,
                'fecha_inicio' => Carbon::now()->toDateString(),
                'fecha_fin' => Carbon::now()->addMonths(10)->toDateString(),
                'duracion_meses' => 10,
                'inscripcion' => 100,
                'cuota_mensual' => 10,
                'inversion_total' => 200,
            ]);
        }

        $response = $this->getJson('/api/prospectos/inscritos-with-courses?per_page=2');

        $response->assertStatus(200)
                 ->assertJsonStructure(['message','data','links','meta']);

        $this->assertCount(2, $response->json('data'));
        $this->assertEquals(3, $response->json('meta.total'));
    }
}
