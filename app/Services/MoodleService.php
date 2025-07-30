<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        $this->url     = rtrim(config('services.moodle.url'), '/');
        $this->altUrl  = rtrim(config('services.moodle.alt_url') ?? $this->url, '/');
        $this->token   = config('services.moodle.token');
        $this->format  = config('services.moodle.format', 'json');
    }

    /**
     * Obtiene un curso desde Moodle
     */
    public function getCourse(int $id): ?array
    {
        $params = [
            'wstoken'            => $this->token,
            'wsfunction'         => 'core_course_get_courses',
            'moodlewsrestformat' => $this->format,
            'options[ids][0]'    => $id,
        ];

        $response = Http::get("{$this->url}/webservice/rest/server.php", $params);
        if (! $response->ok() && $this->altUrl !== $this->url) {
            $response = Http::get("{$this->altUrl}/webservice/rest/server.php", $params);
        }

        return $response->ok() ? ($response->json()[0] ?? null) : null;
    }

    /**
     * Recupera todos los IDs de curso de Moodle
     *
     * @return array<int>
     */
    public function getCourseIds(): array
    {
        $params = [
            'wstoken'            => $this->token,
            'wsfunction'         => 'core_course_get_courses',
            'moodlewsrestformat' => $this->format,
        ];

        $response = Http::get("{$this->url}/webservice/rest/server.php", $params);
        if (! $response->ok() && $this->altUrl !== $this->url) {
            $response = Http::get("{$this->altUrl}/webservice/rest/server.php", $params);
        }
        if (! $response->ok()) {
            return [];
        }

        return array_column($response->json(), 'id');
    }

    /**
     * Sincroniza un curso concreto:
     */
    public function syncCourse(int $moodleId): ?Course
    {
        $data = $this->getCourse($moodleId);
        if (! $data) {
            return null;
        }

        $originalName = $data['fullname'] ?? $data['shortname'];

        // Extraer código antes de verificar exclusiones
        $code = $this->extractCourseCode($data['summary'] ?? '')
              ?? $this->extractCodeFromName($originalName)
              ?? '';

        // Verificar si el curso debe ser excluido
        if ($this->shouldExcludeCourse($originalName, $code)) {
            Log::info("Curso excluido de sincronización: {$originalName}");
            return null;
        }

        $schedule     = $this->extractSchedule($originalName);
        $cleanName    = $this->cleanCourseName($originalName, $code);

        // Mapping area/créditos (default credits = 2)
        $mapping = config('course_areas')[$cleanName] ?? ['area' => 'common', 'credits' => 2];
        // Nombre a mostrar: "Mes Día Año Código Nombre Limpio"
        $displayName = $this->buildDisplayName($data['startdate'], $code, $cleanName, $originalName);

        // Buscar curso por moodle_id o por code, incluyendo soft-deleted
        $course = Course::withTrashed()
            ->where('moodle_id', $data['id'])
            ->orWhere('code', $code)
            ->first();

        if ($course) {
            if ($course->trashed()) {
                $course->restore();
            }
        } else {
            $course = new Course();
            $course->moodle_id = $data['id'];
        }

        // Asignar campos
        $course->name       = $displayName;
        $course->code       = $code;
        $course->area       = $mapping['area'];
        $course->credits    = $mapping['credits'];
        $course->start_date = Carbon::createFromTimestamp($data['startdate'])->toDateString();
        $course->end_date   = Carbon::createFromTimestamp($data['enddate'])->toDateString();
        $course->schedule   = $schedule;
        $course->duration   = '';
        $course->status     = 'synced';
        $course->origen     = 'moodle';
        $course->save();

        // Vincular programa si no tiene ninguno
        if (! $course->programas()->exists()) {
            $abbr = preg_replace('/\d+$/', '', $code);
            if ($program = Programa::where('abreviatura', $abbr)->first()) {
                $course->programas()->syncWithoutDetaching($program->id);
            }
        }

        return $course;
    }

    /**
     * Construye el nombre de despliegue incluyendo fecha, código y nombre limpio
     */
    protected function buildDisplayName(int $timestamp, string $code, string $cleanName, string $originalName): string
    {
        // Intentar extraer la fecha del nombre original primero
        $extractedDate = $this->extractDateFromName($originalName);

        if ($extractedDate) {
            $monthName = $extractedDate['month'];
            $dayName = $extractedDate['day'];
            $year = $extractedDate['year'];
        } else {
            // Fallback al timestamp si no se puede extraer del nombre
            $date      = Carbon::createFromTimestamp($timestamp)->locale('es');
            $monthName = ucfirst($date->isoFormat('MMMM'));
            $dayName   = ucfirst($date->isoFormat('dddd'));
            $year      = $date->year;
        }

        // Extraer solo las letras del código (ej: BBA de BBA044)
        $codePrefix = preg_replace('/\d+$/', '', $code);

        // Si el nombre limpio ya contiene el prefijo del código, no lo agregamos
        if (!empty($codePrefix) && strpos($cleanName, $codePrefix) === 0) {
            return "{$monthName} {$dayName} {$year} {$cleanName}";
        }

        return "{$monthName} {$dayName} {$year} {$codePrefix} {$cleanName}";
    }

    /**
     * Limpia el nombre completo quitando días, meses, años y prefijos de código
     */
    protected function cleanCourseName(string $fullname, string $code = ''): string
    {
        $days   = ['Lunes','Martes','Miércoles','Miercoles','Jueves','Viernes','Sábado','Sabado','Domingo'];
        $months = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        $tokens = array_merge($days, $months);

        // Remover días, meses y años
        $pattern = '/\b(' . implode('|', $tokens) . '|\d{4})\b/u';
        $clean = preg_replace($pattern, '', $fullname);

        // Remover CUALQUIER código completo con números al inicio (ej: BBA044, MBA123, etc.)
        $clean = preg_replace('/^[A-Z]{2,}\d+\s+/', '', $clean);

        // Remover SOLO códigos de letras al inicio si coinciden con el código extraído
        if ($code) {
            $codePrefix = preg_replace('/\d+$/', '', $code);
            if (!empty($codePrefix)) {
                // Solo remover si está exactamente al inicio seguido de espacio o fin de cadena
                $clean = preg_replace('/^' . preg_quote($codePrefix) . '(?=\s|$)/', '', $clean);
            }
        }

        // Limpiar espacios múltiples y trim
        return trim(preg_replace('/\s+/', ' ', $clean));
    }

    /**
     * Valida si un curso debe ser excluido de la sincronización
     */
    protected function shouldExcludeCourse(string $fullname, string $code): bool
    {
        // Lista de patrones a excluir
        $excludePatterns = [
            '/BBA045.*BBA.*Análisis de Mercados/i',
            // Agregar más patrones según sea necesario
        ];

        foreach ($excludePatterns as $pattern) {
            if (preg_match($pattern, $fullname)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extrae el código del summary
     */
    protected function extractCourseCode(string $summary): ?string
    {
        $text = strip_tags($summary);
        if (preg_match('/[A-Z]{2,}\d+/', $text, $m)) {
            return $m[0];
        }
        return null;
    }

    /**
     * Extrae el código (letras+digitos opcionales) al inicio del nombre completo
     */
    protected function extractCodeFromName(string $fullname): ?string
    {
        if (preg_match('/^([A-Z]{2,}\d*)/', $fullname, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Extrae fecha del nombre original del curso
     */
    protected function extractDateFromName(string $fullname): ?array
    {
        $months = [
            'Enero' => 'Enero', 'Febrero' => 'Febrero', 'Marzo' => 'Marzo',
            'Abril' => 'Abril', 'Mayo' => 'Mayo', 'Junio' => 'Junio',
            'Julio' => 'Julio', 'Agosto' => 'Agosto', 'Septiembre' => 'Septiembre',
            'Octubre' => 'Octubre', 'Noviembre' => 'Noviembre', 'Diciembre' => 'Diciembre'
        ];

        $days = [
            'Lunes' => 'Lunes', 'Martes' => 'Martes', 'Miércoles' => 'Miércoles',
            'Miercoles' => 'Miércoles', 'Jueves' => 'Jueves', 'Viernes' => 'Viernes',
            'Sábado' => 'Sábado', 'Sabado' => 'Sábado', 'Domingo' => 'Domingo'
        ];

        // Buscar patrón: Mes Día Año
        $pattern = '/\b(' . implode('|', array_keys($months)) . ')\s+(' . implode('|', array_keys($days)) . ')\s+(\d{4})\b/u';

        if (preg_match($pattern, $fullname, $matches)) {
            return [
                'month' => $months[$matches[1]],
                'day' => $days[$matches[2]],
                'year' => $matches[3]
            ];
        }

        return null;
    }

    /**
     * Extrae el prefijo de días de la semana del fullname
     */
    protected function extractSchedule(string $fullname): string
    {
        $days   = ['Lunes','Martes','Miércoles','Miercoles','Jueves','Viernes','Sábado','Sabado','Domingo'];
        $tokens = preg_split('/\s+/', trim($fullname));
        $found  = array_filter($tokens, fn($t) => in_array($t, $days));
        return $found ? implode('-', array_values($found)) : '';
    }
}
