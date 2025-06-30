<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Prospecto;
use App\Models\Programa;
use App\Models\Course;
use App\Models\EstudiantePrograma;
use Tests\TestCase;
use Carbon\Carbon;

class EstudianteProgramaWithCoursesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure in-memory SQLite for tests
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
    }

    public function test_endpoint_returns_programs_with_prospecto_and_courses()
    {
        // Create a prospecto
        $prospecto = Prospecto::create([
            'fecha' => Carbon::now()->toDateString(),
            'nombre_completo' => 'John Doe',
            'telefono' => '123456789',
            'correo_electronico' => 'john@example.com',
            'genero' => 'M',
        ]);

        // Create a programa
        $programa = Programa::create([
            'nombre_del_programa' => 'Program Test',
            'meses' => 10,
        ]);

        // Create a course and attach to programa
        $course = Course::create([
            'name' => 'Test Course',
            'code' => 'TC01',
            'area' => 'common',
            'credits' => 3,
            'start_date' => Carbon::now()->toDateString(),
            'end_date' => Carbon::now()->addMonth()->toDateString(),
            'schedule' => 'Mon-Fri',
            'duration' => '3h',
            'status' => 'draft',
        ]);
        $programa->courses()->attach($course->id);

        // Create EstudiantePrograma record
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

        // Call endpoint
        $response = $this->getJson("/api/estudiante-programa/{$prospecto->id}/with-courses");

        $response->assertStatus(200)
            ->assertJsonStructure([
                [
                    'id',
                    'prospecto_id',
                    'programa_id',
                    'prospecto' => ['id', 'nombre_completo'],
                    'programa' => [
                        'id',
                        'nombre_del_programa',
                        'courses' => [
                            ['id', 'name']
                        ]
                    ]
                ]
            ]);

        $data = $response->json()[0];
        $this->assertEquals($prospecto->id, $data['prospecto']['id']);
        $this->assertEquals($programa->id, $data['programa']['id']);
        $this->assertEquals($course->id, $data['programa']['courses'][0]['id']);
    }
}
