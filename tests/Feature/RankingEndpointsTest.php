<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\{User, Prospecto, Course, Inscripcion};

class RankingEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
    }

    public function test_student_ranking_endpoint()
    {
        $user = User::create([
            'username' => 'u1',
            'email' => 'u1@example.com',
            'password_hash' => bcrypt('secret'),
            'first_name' => 'U',
            'last_name' => 'One',
        ]);

        $this->actingAs($user);

        $prospecto = Prospecto::create([
            'fecha' => now()->toDateString(),
            'nombre_completo' => 'John Doe',
            'telefono' => '123',
            'correo_electronico' => 'john@example.com',
            'genero' => 'M',
        ]);

        $course = Course::create([
            'name' => 'C1',
            'code' => 'C1',
            'area' => 'common',
            'credits' => 3,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'schedule' => 'X',
            'duration' => '1h',
            'status' => 'draft',
        ]);

        Inscripcion::create([
            'prospecto_id' => $prospecto->id,
            'course_id' => $course->id,
            'semestre' => '2023-1',
            'credits' => 3,
            'calificacion' => 95,
        ]);

        $response = $this->getJson('/api/ranking/students');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         [
                             'id',
                             'nombre',
                             'gpa',
                             'progreso',
                         ]
                     ]
                 ]);
    }

    public function test_course_performance_endpoint()
    {
        $user = User::create([
            'username' => 'u2',
            'email' => 'u2@example.com',
            'password_hash' => bcrypt('secret'),
            'first_name' => 'U',
            'last_name' => 'Two',
        ]);

        $this->actingAs($user);

        $prospecto = Prospecto::create([
            'fecha' => now()->toDateString(),
            'nombre_completo' => 'Jane Doe',
            'telefono' => '456',
            'correo_electronico' => 'jane@example.com',
            'genero' => 'F',
        ]);

        $course = Course::create([
            'name' => 'C2',
            'code' => 'C2',
            'area' => 'common',
            'credits' => 3,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'schedule' => 'X',
            'duration' => '1h',
            'status' => 'draft',
        ]);

        Inscripcion::create([
            'prospecto_id' => $prospecto->id,
            'course_id' => $course->id,
            'semestre' => '2023-1',
            'credits' => 3,
            'calificacion' => 80,
        ]);

        $response = $this->getJson('/api/ranking/courses');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         [
                             'course_id',
                             'name',
                             'promedio',
                             'tasa_aprobacion',
                         ]
                     ]
                 ]);
    }
}
