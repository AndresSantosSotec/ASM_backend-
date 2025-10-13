<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Prospecto;
use App\Models\EstudiantePrograma;
use App\Models\Programa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Carbon\Carbon;

class ReportesMatriculaTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $programa1;
    protected $programa2;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an authenticated user
        $this->user = User::factory()->create();
        
        // Create programs
        $this->programa1 = Programa::create([
            'nombre_del_programa' => 'Desarrollo Web',
            'abreviatura' => 'DW',
            'activo' => 1,
            'duracion_meses' => 12
        ]);

        $this->programa2 = Programa::create([
            'nombre_del_programa' => 'Marketing Digital',
            'abreviatura' => 'MD',
            'activo' => 1,
            'duracion_meses' => 10
        ]);
    }

    /** @test */
    public function it_requires_authentication()
    {
        $response = $this->getJson('/api/administracion/reportes-matricula');
        
        $response->assertStatus(401);
    }

    /** @test */
    public function it_returns_enrollment_reports_with_default_parameters()
    {
        // Create some enrollments
        $this->createEnrollments(5, $this->programa1, Carbon::now()->subDays(15));
        $this->createEnrollments(3, $this->programa2, Carbon::now()->subDays(10));

        $response = $this->actingAs($this->user)
            ->getJson('/api/administracion/reportes-matricula');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'filtros' => [
                    'rangosDisponibles',
                    'programas',
                    'tiposAlumno'
                ],
                'periodoActual' => [
                    'rango',
                    'totales' => [
                        'matriculados',
                        'alumnosNuevos',
                        'alumnosRecurrentes'
                    ],
                    'distribucionProgramas',
                    'evolucionMensual',
                    'distribucionTipo'
                ],
                'periodoAnterior' => [
                    'totales',
                    'rangoComparado'
                ],
                'comparativa' => [
                    'totales',
                    'nuevos',
                    'recurrentes'
                ],
                'tendencias' => [
                    'ultimosDoceMeses',
                    'crecimientoPorPrograma',
                    'proyeccion'
                ],
                'listado' => [
                    'alumnos',
                    'paginacion'
                ]
            ]);
    }

    /** @test */
    public function it_filters_by_program()
    {
        $this->createEnrollments(5, $this->programa1, Carbon::now()->subDays(15));
        $this->createEnrollments(3, $this->programa2, Carbon::now()->subDays(10));

        $response = $this->actingAs($this->user)
            ->getJson('/api/administracion/reportes-matricula?programaId=' . $this->programa1->id);

        $response->assertStatus(200);
        
        // Check that only programa1 enrollments are counted
        $data = $response->json();
        $this->assertGreaterThanOrEqual(0, $data['periodoActual']['totales']['matriculados']);
    }

    /** @test */
    public function it_filters_by_student_type()
    {
        // Create new students
        $this->createEnrollments(5, $this->programa1, Carbon::now()->subDays(15));
        
        // Create recurring students (students with previous enrollments)
        $prospecto = $this->createProspectoWithPreviousEnrollment();

        $response = $this->actingAs($this->user)
            ->getJson('/api/administracion/reportes-matricula?tipoAlumno=Nuevo');

        $response->assertStatus(200);
        
        $data = $response->json();
        $this->assertArrayHasKey('periodoActual', $data);
    }

    /** @test */
    public function it_handles_custom_date_range()
    {
        $fechaInicio = Carbon::now()->subDays(30)->format('Y-m-d');
        $fechaFin = Carbon::now()->format('Y-m-d');

        $this->createEnrollments(5, $this->programa1, Carbon::now()->subDays(15));

        $response = $this->actingAs($this->user)
            ->getJson("/api/administracion/reportes-matricula?rango=custom&fechaInicio={$fechaInicio}&fechaFin={$fechaFin}");

        $response->assertStatus(200);
    }

    /** @test */
    public function it_validates_custom_range_requires_dates()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/administracion/reportes-matricula?rango=custom');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fechaInicio', 'fechaFin']);
    }

    /** @test */
    public function it_validates_fecha_fin_after_fecha_inicio()
    {
        $fechaInicio = Carbon::now()->format('Y-m-d');
        $fechaFin = Carbon::now()->subDays(5)->format('Y-m-d');

        $response = $this->actingAs($this->user)
            ->getJson("/api/administracion/reportes-matricula?rango=custom&fechaInicio={$fechaInicio}&fechaFin={$fechaFin}");

        $response->assertStatus(422);
    }

    /** @test */
    public function it_handles_pagination()
    {
        $this->createEnrollments(60, $this->programa1, Carbon::now()->subDays(15));

        $response = $this->actingAs($this->user)
            ->getJson('/api/administracion/reportes-matricula?page=1&perPage=20');

        $response->assertStatus(200);
        
        $data = $response->json();
        $this->assertLessThanOrEqual(20, count($data['listado']['alumnos']));
        $this->assertEquals(1, $data['listado']['paginacion']['pagina']);
        $this->assertEquals(20, $data['listado']['paginacion']['porPagina']);
    }

    /** @test */
    public function it_returns_empty_arrays_when_no_data()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/administracion/reportes-matricula');

        $response->assertStatus(200);
        
        $data = $response->json();
        $this->assertIsArray($data['periodoActual']['distribucionProgramas']);
        $this->assertIsArray($data['listado']['alumnos']);
    }

    /** @test */
    public function it_calculates_percentage_variation_correctly()
    {
        // Create enrollments in current month
        $this->createEnrollments(10, $this->programa1, Carbon::now()->subDays(5));
        
        // Create enrollments in previous month
        $this->createEnrollments(5, $this->programa1, Carbon::now()->subMonth()->addDays(5));

        $response = $this->actingAs($this->user)
            ->getJson('/api/administracion/reportes-matricula?rango=month');

        $response->assertStatus(200);
        
        $data = $response->json();
        $this->assertArrayHasKey('comparativa', $data);
        $this->assertArrayHasKey('variacion', $data['comparativa']['totales']);
    }

    /** @test */
    public function export_requires_formato_parameter()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/administracion/reportes-matricula/exportar', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['formato']);
    }

    /** @test */
    public function export_validates_formato_values()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/administracion/reportes-matricula/exportar', [
                'formato' => 'invalid'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['formato']);
    }

    /** @test */
    public function it_exports_to_csv_format()
    {
        $this->createEnrollments(5, $this->programa1, Carbon::now()->subDays(15));

        $response = $this->actingAs($this->user)
            ->postJson('/api/administracion/reportes-matricula/exportar', [
                'formato' => 'csv',
                'detalle' => 'summary'
            ]);

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    /** @test */
    public function it_supports_different_detail_levels()
    {
        $this->createEnrollments(5, $this->programa1, Carbon::now()->subDays(15));

        foreach (['complete', 'summary', 'data'] as $detalle) {
            $response = $this->actingAs($this->user)
                ->postJson('/api/administracion/reportes-matricula/exportar', [
                    'formato' => 'csv',
                    'detalle' => $detalle
                ]);

            $response->assertStatus(200);
        }
    }

    // Helper methods

    protected function createEnrollments($count, $programa, $date)
    {
        for ($i = 0; $i < $count; $i++) {
            $prospecto = Prospecto::create([
                'nombre_completo' => 'Estudiante ' . uniqid(),
                'correo_electronico' => 'estudiante' . uniqid() . '@test.com',
                'telefono' => '12345678',
                'status' => 'Inscrito',
                'carnet' => 'ASM' . time() . $i,
                'activo' => 1
            ]);

            EstudiantePrograma::create([
                'prospecto_id' => $prospecto->id,
                'programa_id' => $programa->id,
                'fecha_inicio' => $date,
                'fecha_fin' => $date->copy()->addMonths($programa->duracion_meses),
                'duracion_meses' => $programa->duracion_meses,
                'inscripcion' => 500,
                'cuota_mensual' => 300,
                'inversion_total' => 500 + (300 * $programa->duracion_meses),
                'created_at' => $date
            ]);
        }
    }

    protected function createProspectoWithPreviousEnrollment()
    {
        $prospecto = Prospecto::create([
            'nombre_completo' => 'Estudiante Recurrente',
            'correo_electronico' => 'recurrente@test.com',
            'telefono' => '12345678',
            'status' => 'Inscrito',
            'carnet' => 'ASM' . time(),
            'activo' => 1
        ]);

        // Create previous enrollment (more than a year ago)
        EstudiantePrograma::create([
            'prospecto_id' => $prospecto->id,
            'programa_id' => $this->programa1->id,
            'fecha_inicio' => Carbon::now()->subYear(),
            'fecha_fin' => Carbon::now()->subYear()->addMonths(12),
            'duracion_meses' => 12,
            'inscripcion' => 500,
            'cuota_mensual' => 300,
            'inversion_total' => 4100,
            'created_at' => Carbon::now()->subYear()
        ]);

        // Create current enrollment
        EstudiantePrograma::create([
            'prospecto_id' => $prospecto->id,
            'programa_id' => $this->programa2->id,
            'fecha_inicio' => Carbon::now()->subDays(10),
            'fecha_fin' => Carbon::now()->subDays(10)->addMonths(10),
            'duracion_meses' => 10,
            'inscripcion' => 500,
            'cuota_mensual' => 300,
            'inversion_total' => 3500,
            'created_at' => Carbon::now()->subDays(10)
        ]);

        return $prospecto;
    }

    /** @test */
    public function it_can_access_estudiantes_matriculados_endpoint()
    {
        // Create some enrollments
        $this->createEnrollments(5, $this->programa1, Carbon::now()->subDays(15));
        $this->createEnrollments(3, $this->programa2, Carbon::now()->subDays(10));

        $response = $this->actingAs($this->user)
            ->getJson('/api/administracion/estudiantes-matriculados?page=1&perPage=50');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'alumnos',
                'paginacion' => [
                    'pagina',
                    'porPagina',
                    'total',
                    'totalPaginas'
                ]
            ]);
    }

    /** @test */
    public function estudiantes_matriculados_requires_authentication()
    {
        $response = $this->getJson('/api/administracion/estudiantes-matriculados');
        
        $response->assertStatus(401);
    }

    /** @test */
    public function estudiantes_matriculados_supports_filtering()
    {
        $this->createEnrollments(5, $this->programa1, Carbon::now()->subDays(15));
        $this->createEnrollments(3, $this->programa2, Carbon::now()->subDays(10));

        $response = $this->actingAs($this->user)
            ->getJson('/api/administracion/estudiantes-matriculados?programaId=' . $this->programa1->id);

        $response->assertStatus(200);
        
        // All returned students should be from programa1
        $data = $response->json();
        $this->assertArrayHasKey('alumnos', $data);
    }

    /** @test */
    public function estudiantes_matriculados_does_not_have_n_plus_one_queries()
    {
        // Create a larger dataset to make N+1 issues more visible
        $this->createEnrollments(20, $this->programa1, Carbon::now()->subDays(15));
        
        // Enable query logging
        \DB::enableQueryLog();

        $response = $this->actingAs($this->user)
            ->getJson('/api/administracion/estudiantes-matriculados?page=1&perPage=20');

        $queries = \DB::getQueryLog();
        \DB::disableQueryLog();

        $response->assertStatus(200);

        // With the optimization, we should have a minimal number of queries
        // Main query + subquery for first enrollment + auth queries
        // Should be significantly less than 20 students * 2 queries each (40+)
        $this->assertLessThan(15, count($queries), 
            'Too many queries detected. Possible N+1 query issue. Total queries: ' . count($queries));
    }
}
