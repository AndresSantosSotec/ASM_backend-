<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MoodleTestController extends Controller
{
    protected string $url;
    protected string $altUrl;
    protected string $token;

    public function __construct()
    {
        $this->url = rtrim(config('services.moodle.url'), '/');
        $this->altUrl = rtrim(config('services.moodle.alt_url') ?? $this->url, '/');
        $this->token = config('services.moodle.token');
    }

    /**
     * Verificar conexión con Moodle
     * GET /api/moodle/test/connection
     */
    public function testConnection()
    {
        try {
            $params = [
                'wstoken' => $this->token,
                'wsfunction' => 'core_webservice_get_site_info',
                'moodlewsrestformat' => 'json',
            ];

            Log::info('🔍 [MOODLE TEST] Probando conexión', [
                'url' => $this->url,
                'token' => substr($this->token, 0, 10) . '...',
            ]);

            $response = Http::timeout(10)->get("{$this->url}/webservice/rest/server.php", $params);

            if (!$response->ok()) {
                // Intentar con URL alternativa
                Log::warning('⚠️ [MOODLE TEST] URL principal falló, intentando alternativa');
                $response = Http::timeout(10)->get("{$this->altUrl}/webservice/rest/server.php", $params);
            }

            if (!$response->ok()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo conectar a Moodle',
                    'status_code' => $response->status(),
                    'error' => $response->body(),
                ], 500);
            }

            $data = $response->json();

            if (isset($data['exception'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de autenticación Moodle',
                    'error' => $data['message'] ?? 'Token inválido',
                    'exception' => $data['exception'],
                ], 401);
            }

            Log::info('✅ [MOODLE TEST] Conexión exitosa', [
                'sitename' => $data['sitename'] ?? 'N/A',
                'version' => $data['version'] ?? 'N/A',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Conexión exitosa con Moodle',
                'data' => [
                    'sitename' => $data['sitename'] ?? 'N/A',
                    'username' => $data['username'] ?? 'N/A',
                    'firstname' => $data['firstname'] ?? 'N/A',
                    'lastname' => $data['lastname'] ?? 'N/A',
                    'userid' => $data['userid'] ?? 'N/A',
                    'version' => $data['version'] ?? 'N/A',
                    'release' => $data['release'] ?? 'N/A',
                    'functions' => count($data['functions'] ?? []),
                ],
                'url_used' => $response->effectiveUri(),
            ]);

        } catch (\Throwable $th) {
            Log::error('❌ [MOODLE TEST] Error en conexión', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al conectar con Moodle',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar funciones disponibles en Moodle
     * GET /api/moodle/test/functions
     */
    public function listFunctions()
    {
        try {
            $params = [
                'wstoken' => $this->token,
                'wsfunction' => 'core_webservice_get_site_info',
                'moodlewsrestformat' => 'json',
            ];

            $response = Http::get("{$this->url}/webservice/rest/server.php", $params);

            if (!$response->ok()) {
                $response = Http::get("{$this->altUrl}/webservice/rest/server.php", $params);
            }

            if (!$response->ok()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo obtener funciones de Moodle',
                ], 500);
            }

            $data = $response->json();
            $functions = $data['functions'] ?? [];

            // Agrupar funciones por categoría
            $grouped = [];
            foreach ($functions as $func) {
                $category = explode('_', $func['name'])[0] ?? 'other';
                $grouped[$category][] = $func['name'];
            }

            return response()->json([
                'success' => true,
                'total_functions' => count($functions),
                'categories' => array_keys($grouped),
                'functions_by_category' => $grouped,
                'all_functions' => array_column($functions, 'name'),
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener funciones',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar todos los cursos de Moodle
     * GET /api/moodle/test/courses
     */
    public function listCourses(Request $request)
    {
        try {
            $params = [
                'wstoken' => $this->token,
                'wsfunction' => 'core_course_get_courses',
                'moodlewsrestformat' => 'json',
            ];

            $response = Http::get("{$this->url}/webservice/rest/server.php", $params);

            if (!$response->ok()) {
                $response = Http::get("{$this->altUrl}/webservice/rest/server.php", $params);
            }

            if (!$response->ok()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudieron obtener cursos',
                ], 500);
            }

            $courses = $response->json();

            if (isset($courses['exception'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al obtener cursos',
                    'error' => $courses['message'] ?? 'Error desconocido',
                ], 401);
            }

            // Filtrar solo información relevante
            $courseList = array_map(function($course) {
                return [
                    'id' => $course['id'],
                    'shortname' => $course['shortname'],
                    'fullname' => $course['fullname'],
                    'startdate' => date('Y-m-d', $course['startdate']),
                    'enddate' => date('Y-m-d', $course['enddate']),
                    'visible' => $course['visible'],
                    'enrollmentmethods' => $course['enrollmentmethods'] ?? [],
                ];
            }, $courses);

            return response()->json([
                'success' => true,
                'total' => count($courseList),
                'courses' => $courseList,
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error al listar cursos',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener detalles de un curso específico
     * GET /api/moodle/test/courses/{id}
     */
    public function getCourse($id)
    {
        try {
            $params = [
                'wstoken' => $this->token,
                'wsfunction' => 'core_course_get_courses',
                'moodlewsrestformat' => 'json',
                'options[ids][0]' => $id,
            ];

            $response = Http::get("{$this->url}/webservice/rest/server.php", $params);

            if (!$response->ok()) {
                $response = Http::get("{$this->altUrl}/webservice/rest/server.php", $params);
            }

            if (!$response->ok()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo obtener el curso',
                ], 500);
            }

            $courses = $response->json();

            if (isset($courses['exception'])) {
                return response()->json([
                    'success' => false,
                    'error' => $courses['message'] ?? 'Error desconocido',
                ], 401);
            }

            $course = $courses[0] ?? null;

            if (!$course) {
                return response()->json([
                    'success' => false,
                    'message' => 'Curso no encontrado',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'course' => $course,
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener curso',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Crear un nuevo curso en Moodle
     * POST /api/moodle/test/courses
     */
    public function createCourse(Request $request)
    {
        try {
            $validated = $request->validate([
                'fullname' => 'required|string|max:254',
                'shortname' => 'required|string|max:100',
                'categoryid' => 'required|integer',
                'summary' => 'nullable|string',
                'startdate' => 'nullable|date',
                'enddate' => 'nullable|date',
            ]);

            $params = [
                'wstoken' => $this->token,
                'wsfunction' => 'core_course_create_courses',
                'moodlewsrestformat' => 'json',
                'courses[0][fullname]' => $validated['fullname'],
                'courses[0][shortname]' => $validated['shortname'],
                'courses[0][categoryid]' => $validated['categoryid'],
            ];

            if (isset($validated['summary'])) {
                $params['courses[0][summary]'] = $validated['summary'];
            }

            if (isset($validated['startdate'])) {
                $params['courses[0][startdate]'] = strtotime($validated['startdate']);
            }

            if (isset($validated['enddate'])) {
                $params['courses[0][enddate]'] = strtotime($validated['enddate']);
            }

            Log::info('📝 [MOODLE TEST] Creando curso', [
                'fullname' => $validated['fullname'],
                'shortname' => $validated['shortname'],
            ]);

            $response = Http::post("{$this->url}/webservice/rest/server.php", $params);

            if (!$response->ok()) {
                $response = Http::post("{$this->altUrl}/webservice/rest/server.php", $params);
            }

            if (!$response->ok()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo crear el curso',
                    'status' => $response->status(),
                ], 500);
            }

            $result = $response->json();

            if (isset($result['exception'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al crear curso en Moodle',
                    'error' => $result['message'] ?? 'Error desconocido',
                    'exception' => $result['exception'],
                ], 400);
            }

            Log::info('✅ [MOODLE TEST] Curso creado exitosamente', [
                'course_id' => $result[0]['id'] ?? 'N/A',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Curso creado exitosamente en Moodle',
                'course' => $result[0] ?? $result,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Throwable $th) {
            Log::error('❌ [MOODLE TEST] Error al crear curso', [
                'error' => $th->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear curso',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar un curso de Moodle
     * DELETE /api/moodle/test/courses/{id}
     */
    public function deleteCourse($id)
    {
        try {
            $params = [
                'wstoken' => $this->token,
                'wsfunction' => 'core_course_delete_courses',
                'moodlewsrestformat' => 'json',
                'courseids[0]' => $id,
            ];

            Log::warning('🗑️ [MOODLE TEST] Eliminando curso', [
                'course_id' => $id,
            ]);

            $response = Http::post("{$this->url}/webservice/rest/server.php", $params);

            if (!$response->ok()) {
                $response = Http::post("{$this->altUrl}/webservice/rest/server.php", $params);
            }

            if (!$response->ok()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo eliminar el curso',
                    'status' => $response->status(),
                ], 500);
            }

            $result = $response->json();

            if (isset($result['exception'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al eliminar curso de Moodle',
                    'error' => $result['message'] ?? 'Error desconocido',
                    'exception' => $result['exception'],
                ], 400);
            }

            Log::info('✅ [MOODLE TEST] Curso eliminado exitosamente', [
                'course_id' => $id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Curso eliminado exitosamente de Moodle',
                'course_id' => $id,
            ]);

        } catch (\Throwable $th) {
            Log::error('❌ [MOODLE TEST] Error al eliminar curso', [
                'course_id' => $id,
                'error' => $th->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar curso',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener categorías de Moodle
     * GET /api/moodle/test/categories
     */
    public function getCategories()
    {
        try {
            $params = [
                'wstoken' => $this->token,
                'wsfunction' => 'core_course_get_categories',
                'moodlewsrestformat' => 'json',
            ];

            $response = Http::get("{$this->url}/webservice/rest/server.php", $params);

            if (!$response->ok()) {
                $response = Http::get("{$this->altUrl}/webservice/rest/server.php", $params);
            }

            if (!$response->ok()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudieron obtener categorías',
                ], 500);
            }

            $categories = $response->json();

            if (isset($categories['exception'])) {
                return response()->json([
                    'success' => false,
                    'error' => $categories['message'] ?? 'Error desconocido',
                ], 401);
            }

            return response()->json([
                'success' => true,
                'total' => count($categories),
                'categories' => $categories,
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener categorías',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
}
