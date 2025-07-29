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

    public function syncCourse(int $moodleId): ?Course
    {
        $data = $this->getCourse($moodleId);
        if (!$data) {
            return null;
        }

        $course = Course::firstOrNew(['moodle_id' => $data['id']]);

        $cleanName = $this->cleanCourseName($data['fullname'] ?? $data['shortname']);
        $code = $this->extractCourseCode($data['summary'] ?? '')
            ?? ($data['shortname'] ?? '');

        $course->fill([
            'name'       => $cleanName,
            'code'       => $code,
            'start_date' => Carbon::createFromTimestamp($data['startdate'])->toDateString(),
            'end_date'   => Carbon::createFromTimestamp($data['enddate'])->toDateString(),
            'status'     => 'synced',
        ]);
        $course->save();

        if (!$course->programas()->exists()) {
            $abbr = $this->extractAbbreviation($cleanName);
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

    protected function cleanCourseName(string $fullname): string
    {
        $name = trim($fullname);
        $parts = preg_split('/\s+/', $name);

        $days = ['Lunes','Martes','Miércoles','Miercoles','Jueves','Viernes','Sábado','Sabado','Domingo'];
        $months = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

        if ($parts && in_array($parts[0], $days)) {
            array_shift($parts);
        }

        if ($parts && preg_match('/^\d{4}$/', end($parts))) {
            array_pop($parts);
        }

        if ($parts && in_array(end($parts), $months)) {
            array_pop($parts);
        }

        return implode(' ', $parts);
    }

    protected function extractCourseCode(string $summary): ?string
    {
        $text = strip_tags($summary);
        if (preg_match('/[A-Z]{3,}[A-Z]*\d+/', $text, $m)) {
            return $m[0];
        }
        return null;
    }
}
