<?php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;
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

    public function test_sync_command_all_option()
    {
        Programa::create([
            'abreviatura' => 'BBA',
            'nombre_del_programa' => 'Admin',
            'meses' => 1,
        ]);

        Http::fakeSequence()
            ->push([
                ['id' => 1],
                ['id' => 2],
            ], 200)
            ->push([
                [
                    'id' => 1,
                    'fullname' => 'Course 1',
                    'shortname' => 'BBA01',
                    'startdate' => 1717132800,
                    'enddate' => 1717219200,
                ]
            ], 200)
            ->push([
                [
                    'id' => 2,
                    'fullname' => 'Course 2',
                    'shortname' => 'BBA02',
                    'startdate' => 1717132800,
                    'enddate' => 1717219200,
                ]
            ], 200);

        Artisan::call('moodle:sync', ['--all' => true]);

        $this->assertDatabaseCount('courses', 2);
        $this->assertDatabaseHas('courses', ['moodle_id' => 1]);
        $this->assertDatabaseHas('courses', ['moodle_id' => 2]);
    }
}
