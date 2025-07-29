<?php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;
use App\Models\Course;
use App\Models\Programa;
use Tests\TestCase;

class MoodleSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
    }

    public function test_sync_command_creates_course()
    {
        Programa::create([
            'abreviatura' => 'BBA',
            'nombre_del_programa' => 'Admin',
            'meses' => 1,
        ]);

        Http::fake([
            '*' => Http::response([
                [
                    'id' => 10,
                    'fullname' => 'Curso Test',
                    'shortname' => 'BBA01',
                    'startdate' => 1717132800,
                    'enddate' => 1717219200,
                ]
            ])
        ]);

        Artisan::call('moodle:sync', ['courseId' => 10]);

        $this->assertDatabaseHas('courses', [
            'moodle_id' => 10,
            'name' => 'Curso Test',
        ]);
    }

    public function test_sync_command_formats_name_and_code()
    {
        $program = Programa::create([
            'abreviatura' => 'BBA',
            'nombre_del_programa' => 'Admin',
            'meses' => 1,
        ]);

        Http::fake([
            '*' => Http::response([
                [
                    'id' => 15,
                    'fullname' => 'Lunes BBA Manejo de Crisis Octubre 2023',
                    'shortname' => 'Lunes BBA Manejo de Crisis Octubre 2023',
                    'summary' => '<p><span>BBA07</span></p>',
                    'startdate' => 1717132800,
                    'enddate' => 1717219200,
                ]
            ])
        ]);

        Artisan::call('moodle:sync', ['courseId' => 15]);

        $course = Course::where('moodle_id', 15)->first();
        $this->assertNotNull($course);
        $this->assertEquals('BBA Manejo de Crisis', $course->name);
        $this->assertEquals('BBA07', $course->code);
        $this->assertDatabaseHas('programa_course', [
            'course_id' => $course->id,
            'programa_id' => $program->id,
        ]);
    }
}
