<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\{Programa, Course};
use Carbon\Carbon;

class CourseByProgramsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
    }

    public function test_endpoint_returns_courses_for_multiple_programs()
    {
        $p1 = Programa::create([
            'nombre_del_programa' => 'P1',
            'meses' => 10,
        ]);

        $p2 = Programa::create([
            'nombre_del_programa' => 'P2',
            'meses' => 12,
        ]);

        $c1 = Course::create([
            'name' => 'C1',
            'code' => 'C1',
            'area' => 'common',
            'credits' => 3,
            'start_date' => Carbon::now()->toDateString(),
            'end_date' => Carbon::now()->addMonth()->toDateString(),
            'schedule' => 'X',
            'duration' => '1h',
            'status' => 'draft',
        ]);

        $c2 = Course::create([
            'name' => 'C2',
            'code' => 'C2',
            'area' => 'common',
            'credits' => 3,
            'start_date' => Carbon::now()->toDateString(),
            'end_date' => Carbon::now()->addMonth()->toDateString(),
            'schedule' => 'X',
            'duration' => '1h',
            'status' => 'draft',
        ]);

        $c3 = Course::create([
            'name' => 'C3',
            'code' => 'C3',
            'area' => 'specialty',
            'credits' => 2,
            'start_date' => Carbon::now()->toDateString(),
            'end_date' => Carbon::now()->addMonth()->toDateString(),
            'schedule' => 'X',
            'duration' => '1h',
            'status' => 'draft',
        ]);

        $p1->courses()->attach([$c1->id, $c3->id]);
        $p2->courses()->attach([$c2->id, $c3->id]);

        $response = $this->postJson('/api/courses/by-programs', [
            'program_ids' => [$p1->id, $p2->id],
        ]);

        $response->assertStatus(200)
                 ->assertJsonCount(3);
    }
}

