<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;
use App\Models\Course;
use App\Models\Programa;

class MoodleService
{
    protected string $url;
    protected string $token;

    public function __construct()
    {
        $this->url = config('services.moodle.url');
        $this->token = config('services.moodle.token');
    }

    public function getCourse(int $id): ?array
    {
        $response = Http::get($this->url . '/webservice/rest/server.php', [
            'wstoken' => $this->token,
            'wsfunction' => 'core_course_get_courses',
            'moodlewsrestformat' => 'json',
            'options[ids][0]' => $id,
        ]);

        return $response->ok() ? ($response->json()[0] ?? null) : null;
    }

    public function syncCourse(int $moodleId): ?Course
    {
        $data = $this->getCourse($moodleId);
        if (!$data) {
            return null;
        }

        $course = Course::firstOrNew(['moodle_id' => $data['id']]);

        $course->fill([
            'name'       => $data['fullname'] ?? $data['shortname'],
            'code'       => $data['shortname'] ?? '',
            'start_date' => Carbon::createFromTimestamp($data['startdate'])->toDateString(),
            'end_date'   => Carbon::createFromTimestamp($data['enddate'])->toDateString(),
            'status'     => 'synced',
        ]);
        $course->save();

        if (!$course->programas()->exists()) {
            $abbr = $this->extractAbbreviation($data['shortname']);
            if ($abbr) {
                if ($program = Programa::where('abreviatura', $abbr)->first()) {
                    $course->programas()->syncWithoutDetaching($program->id);
                }
            }
        }

        return $course;
    }

    protected function extractAbbreviation(string $shortname): ?string
    {
        $parts = preg_split('/\s+/', trim($shortname));
        return $parts[0] ?? null;
    }
}
