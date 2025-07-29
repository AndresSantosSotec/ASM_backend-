<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;
use App\Models\Course;
use App\Models\Programa;

class MoodleService
{
    protected string $url;

    protected string $altUrl;
    protected string $token;
    protected string $format;

    public function __construct()
    {
        $this->url = rtrim(config('services.moodle.url'), '/');
        $this->altUrl = rtrim(config('services.moodle.alt_url') ?? $this->url, '/');
        $this->token = config('services.moodle.token');
        $this->format = config('services.moodle.format', 'json');

    }

    public function getCourse(int $id): ?array
    {

        $params = [
            'wstoken' => $this->token,
            'wsfunction' => 'core_course_get_courses',
            'moodlewsrestformat' => $this->format,
            'options[ids][0]' => $id,
        ];

        $response = Http::get($this->url . '/webservice/rest/server.php', $params);

        if (!$response->ok() && $this->altUrl !== $this->url) {
            $response = Http::get($this->altUrl . '/webservice/rest/server.php', $params);
        }


        return $response->ok() ? ($response->json()[0] ?? null) : null;
    }

    /**
     * Retrieve all course IDs from Moodle.
     *
     * @return array<int>
     */
    public function getCourseIds(): array
    {
        $params = [
            'wstoken' => $this->token,
            'wsfunction' => 'core_course_get_courses',
            'moodlewsrestformat' => $this->format,
        ];

        $response = Http::get($this->url . '/webservice/rest/server.php', $params);

        if (!$response->ok() && $this->altUrl !== $this->url) {
            $response = Http::get($this->altUrl . '/webservice/rest/server.php', $params);
        }

        if (!$response->ok()) {
            return [];
        }

        return array_column($response->json(), 'id');
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
