<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Prospecto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Programa;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class CourseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $courses = Course::with(['facilitator', 'programas'])
            ->when($request->search, fn($q) => $q->where(
                fn($q) => $q
                    ->where('name', 'like', "%{$request->search}%")
                    ->orWhere('code', 'like', "%{$request->search}%")
            ))
            ->when(
                in_array($request->area, ['common', 'specialty']),
                fn($q) => $q->where('area', $request->area)
            )
            ->when(
                in_array($request->status, ['draft', 'approved', 'synced']),
                fn($q) => $q->where('status', $request->status)
            )
            ->get();

        return response()->json($courses);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'moodle_id'      => 'nullable|integer|unique:courses,moodle_id',
            'name'           => 'required|string|max:255',
            'code'           => 'required|string|max:50|unique:courses,code',
            'area'           => 'required|in:common,specialty',
            'credits'        => 'required|integer|min:1|max:10',
            'start_date'     => 'required|date',
            'end_date'       => 'required|date|after_or_equal:start_date',
            'schedule'       => 'required|string|max:255',
            'duration'       => 'required|string|max:100',
            'facilitator_id' => 'nullable|exists:users,id',
            'program_ids'    => 'nullable|array',
            'program_ids.*'  => 'exists:tb_programas,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $course = Course::create($validator->validated());

        if (!empty($data['program_ids'])) {
            $course->programas()->sync($data['program_ids']);
        }

        $course->load(['programas', 'facilitator']);

        return response()->json($course, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $course = Course::with(['facilitator', 'programas'])->findOrFail($id);
        return response()->json($course);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $course = Course::findOrFail($id);
        $data   = $request->all();

        $validator = Validator::make($data, [
            'moodle_id'      => ["sometimes","integer", Rule::unique('courses','moodle_id')->ignore($course->id)],
            'name'           => 'sometimes|required|string|max:255',
            'code'           => ["sometimes","required","string","max:50", Rule::unique('courses','code')->ignore($course->id)],
            'area'           => 'sometimes|required|in:common,specialty',
            'credits'        => 'sometimes|required|integer|min:1|max:10',
            'start_date'     => 'sometimes|required|date',
            'end_date'       => 'sometimes|required|date|after_or_equal:start_date',
            'schedule'       => 'sometimes|required|string|max:255',
            'duration'       => 'sometimes|required|string|max:100',
            'facilitator_id' => 'nullable|exists:users,id',
            'status'         => 'sometimes|in:draft,approved,synced',
            'program_ids'    => 'nullable|array',
            'program_ids.*'  => 'exists:tb_programas,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $course->update($validator->validated());

        if (array_key_exists('program_ids', $data)) {
            $course->programas()->sync($data['program_ids'] ?? []);
        }

        $course->load(['programas', 'facilitator']);

        return response()->json($course);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $course = Course::findOrFail($id);
        $course->delete();
        return response()->json(null, 204);
    }

    /**
     * Approve course for the facilitator module
     */
    public function approve(string $id)
    {
        $course = Course::findOrFail($id);

        if (!$course->facilitator_id) {
            return response()->json([
                'message' => 'No se puede aprobar un curso sin facilitador asignado'
            ], 422);
        }

        $course->update(['status' => 'approved']);
        return response()->json($course);
    }

    /**
     * Sync course to Moodle (single).
     */
    public function syncToMoodle(string $id)
    {
        $course = Course::findOrFail($id);

        if ($course->status !== 'approved') {
            return response()->json([
                'message' => 'Solo se pueden sincronizar cursos aprobados'
            ], 422);
        }

        if (!$course->programas()->exists()) {
            return response()->json([
                'message' => 'El curso debe estar asociado a al menos un programa para sincronizar'
            ], 422);
        }

        if (!$course->facilitator_id) {
            return response()->json([
                'message' => 'El curso debe tener un facilitador asignado para sincronizar'
            ], 422);
        }

        $service = app(\App\Services\MoodleService::class);
        $synced = $service->syncCourse($course->moodle_id ?? 0);

        if (!$synced) {
            return response()->json(['message' => 'No se pudo sincronizar'], 500);
        }

        return response()->json($synced);
    }

    /**
     * Sync multiple Moodle courses by ID or course-objects.
     *
     * Acepta:
     * - {"moodle_ids":[1442,1464,...]}
     * - [{"moodle_id":1442,"fullname":"...","shortname":"..."}]
     * - [{"id":1442,"fullname":"...","shortname":"..."}]
     * - {"id":1442,"fullname":"...","shortname":"..."}
     */
    public function bulkSyncToMoodle(Request $request)
    {
        // Diagnóstico básico del request
        Log::info('[bulkSyncToMoodle] Content-Type: ' . ($request->header('Content-Type') ?? 'N/A'));
        Log::info('[bulkSyncToMoodle] Raw body: ' . $request->getContent());

        $data = $request->all();
        Log::info('Datos recibidos en bulkSyncToMoodle (parsed):', is_array($data) ? $data : ['_non_array_' => $data]);

        $coursesToSync = [];

        // 1) moodle_ids: [1405, 1406, …]
        if (isset($data['moodle_ids']) && is_array($data['moodle_ids'])) {
            $ids = array_values(array_filter($data['moodle_ids'], fn($id) => is_numeric($id)));
            foreach ($ids as $id) {
                $coursesToSync[] = ['moodle_id' => (int)$id];
            }
        }
        // 2) Array de objetos
        elseif (is_array($data) && array_is_list($data)) {
            foreach ($data as $idx => $item) {
                if (!is_array($item)) {
                    Log::warning("Elemento #{$idx} no es array; se ignora");
                    continue;
                }
                $normalized = $this->normalizeMoodlePayloadItem($item);
                if ($normalized['moodle_id'] === null) {
                    Log::warning("Elemento #{$idx} sin moodle_id/id; se ignora", $item);
                    continue;
                }
                if (empty($normalized['fullname'])) {
                    Log::notice("Elemento #{$idx} sin fullname; se intentará sync solo por ID", ['moodle_id' => $normalized['moodle_id']]);
                }
                $coursesToSync[] = $normalized;
            }
        }
        // 3) Objeto plano
        elseif (is_array($data) && (isset($data['id']) || isset($data['moodle_id']))) {
            $normalized = $this->normalizeMoodlePayloadItem($data);
            if ($normalized['moodle_id'] !== null) {
                $coursesToSync[] = $normalized;
            }
        }

        if (empty($coursesToSync)) {
            Log::warning('No se encontraron cursos válidos para sincronizar (post-normalización)');
            return response()->json(['message' => 'No hay cursos válidos para sincronizar'], 422);
        }

        Log::info('Cursos a sincronizar (normalizados):', $coursesToSync);

        $service = app(\App\Services\MoodleService::class);
        $synced = [];
        $errors = [];

        foreach ($coursesToSync as $courseData) {
            try {
                $nonEmpty = array_filter($courseData, fn($v) => $v !== null && $v !== '');
                if (count($nonEmpty) === 1 && isset($courseData['moodle_id'])) {
                    // Solo ID → usa método clásico
                    $result = $service->syncCourse($courseData['moodle_id']);
                } else {
                    // Crear/actualizar local con datos
                    $result = $this->syncCourseFromData($courseData, $service);
                }

                if ($result) {
                    $synced[] = $result;
                    Log::info('Curso sincronizado exitosamente:', ['course_id' => is_object($result) ? $result->id : $result]);
                } else {
                    $msg = "No se pudo sincronizar el curso ID: " . ($courseData['moodle_id'] ?? 'unknown');
                    $errors[] = $msg;
                    Log::error($msg, $courseData);
                }
            } catch (\Exception $e) {
                $errorMsg = "Error sincronizando curso ID " . ($courseData['moodle_id'] ?? 'unknown') . ": " . $e->getMessage();
                $errors[] = $errorMsg;
                Log::error($errorMsg, ['exception' => $e]);
            }
        }

        $response = [
            'synced'          => $synced,
            'synced_count'    => count($synced),
            'total_attempted' => count($coursesToSync),
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response);
    }

    /**
     * Normaliza un item de curso recibido desde el frontend/Moodle.
     * Acepta tanto 'id' como 'moodle_id'.
     */
    private function normalizeMoodlePayloadItem(array $item): array
    {
        $moodleId = null;

        if (isset($item['moodle_id']) && is_numeric($item['moodle_id'])) {
            $moodleId = (int)$item['moodle_id'];
        } elseif (isset($item['id']) && is_numeric($item['id'])) {
            $moodleId = (int)$item['id'];
        }

        return [
            'moodle_id'   => $moodleId,
            'fullname'    => $item['fullname'] ?? null,
            'shortname'   => $item['shortname'] ?? null,
            'summary'     => $item['summary'] ?? '',
            'categoryid'  => isset($item['categoryid']) && is_numeric($item['categoryid']) ? (int)$item['categoryid'] : null,
            'numsections' => isset($item['numsections']) && is_numeric($item['numsections']) ? (int)$item['numsections'] : null,
            'timecreated' => isset($item['timecreated']) && is_numeric($item['timecreated']) ? (int)$item['timecreated'] : null,
        ];
    }

    private function syncCourseFromData(array $courseData, $moodleService)
    {
        try {
            if (empty($courseData['moodle_id'])) {
                Log::warning('syncCourseFromData llamado sin moodle_id; se aborta', $courseData);
                return null;
            }

            // ¿ya existe por moodle_id?
            $existingCourse = Course::where('moodle_id', $courseData['moodle_id'])->first();
            if ($existingCourse) {
                Log::info('Curso ya existe, no se duplica. Retornando existente.', [
                    'moodle_id' => $courseData['moodle_id'],
                    'course_id' => $existingCourse->id
                ]);
                return $existingCourse;
            }

            // Evitar colisión por nombre (case-insensitive)
            if (!empty($courseData['fullname'])) {
                $duplicateName = Course::whereRaw('LOWER(name) = ?', [mb_strtolower(trim($courseData['fullname']))])->first();
                if ($duplicateName) {
                    Log::warning('Ya existe un curso con el mismo nombre; se omite creación.', ['name' => $courseData['fullname']]);
                    return null;
                }
            }

            // === NUEVO: generar code canónico (BBA14, etc.) o fallback limpio ===
            $nameForCode = $courseData['shortname'] ?: ($courseData['fullname'] ?? ('COURSE'.$courseData['moodle_id']));
            $baseCode = $this->generateCourseCode(
                $nameForCode,
                $courseData['fullname'] ?? null
            );
            $code = $baseCode;
            $counter = 1;
            while (Course::where('code', $code)->exists()) {
                $code = $baseCode . '-' . $counter;
                $counter++;
            }

            // Fechas por defecto
            $start = now();
            $end   = (clone $start)->addMonths(4);

            $newCourse = Course::create([
                'moodle_id'  => $courseData['moodle_id'],
                'name'       => $courseData['fullname'] ?: ('Curso '.$courseData['moodle_id']),
                'code'       => $code,
                'area'       => 'common',
                'credits'    => 3,
                'start_date' => $start,
                'end_date'   => $end,
                'schedule'   => 'Por definir',
                'duration'   => '4 meses',
                'status'     => 'draft',
                'origen'     => 'moodle',
            ]);

            Log::info('Curso creado desde datos de Moodle', [
                'course_id' => $newCourse->id,
                'moodle_id' => $courseData['moodle_id'],
            ]);

            return $newCourse;
        } catch (\Exception $e) {
            Log::error('Error creando curso desde datos de Moodle:', [
                'courseData' => $courseData,
                'error'      => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Quita prefijos de Mes[/Día] Año y deja el título canónico.
     * "Octubre Sábado 2025 BBA Contabilidad Aplicada" -> "BBA Contabilidad Aplicada"
     */
    private function canonicalTitle(string $name): string
    {
        $n = trim(preg_replace('/\s+/', ' ', $name));

        $meses = '(Enero|Febrero|Marzo|Abril|Mayo|Junio|Julio|Agosto|Septiembre|Octubre|Noviembre|Diciembre)';
        $dias  = '(Lunes|Martes|Miércoles|Miercoles|Jueves|Viernes|Sábado|Sabado|Domingo)';
        $pattern = "/^{$meses}(?:\s+{$dias})?\s+\d{4}\s+/iu";

        $canon = preg_replace($pattern, '', $n);
        return $canon ?: $n;
    }

    /**
     * Tabla de mapeos canónicos -> código fijo.
     * Puedes mover esto a config('courses.code_map') si prefieres.
     */
    private function codeMap(): array
    {
        return [
            // === BBA ===
            'BBA Contabilidad Aplicada'   => 'BBA14',
            // Agrega el resto cuando los confirmes, por ejemplo:
            // 'BBA Contabilidad Financiera' => 'BBA13',
            // 'BBA Excel Ejecutivo'         => 'BBA12',
            // 'BBA Power BI'                => 'BBA16',
        ];
    }

    /**
     * Genera un código de curso:
     * 1) Usa mapeo fijo si el título canónico coincide (BBA14).
     * 2) Si no hay mapeo, usa fallback "limpio" desde shortname/fullname (A-Z0-9).
     * 3) Limita a 50 chars (columna `code`) y no devuelve vacío.
     */
    private function generateCourseCode(string $shortnameOrFullname, ?string $fullname = null): string
    {
        $title = $this->canonicalTitle($fullname ?: $shortnameOrFullname);
        $map = $this->codeMap();

        if (isset($map[$title])) {
            $base = $map[$title];
        } else {
            $src  = $shortnameOrFullname ?: $title;
            $base = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $src));
            if ($base === '') {
                $base = 'COURSE' . time();
            }
        }

        $maxLen = 50;
        if (strlen($base) > $maxLen) {
            $base = substr($base, 0, $maxLen);
        }

        return $base;
    }

    /**
     * Assign facilitator to course
     */
    public function assignFacilitator(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'facilitator_id' => 'nullable|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $course = Course::findOrFail($id);
        $course->update(['facilitator_id' => $request->facilitator_id]);
        return response()->json($course);
    }

    /**
     * Bulk assign courses to prospectos
     */
    public function assignCourses(Request $request)
    {
        $payload = $request->validate([
            'prospecto_ids'   => 'required|array',
            'prospecto_ids.*' => 'exists:prospectos,id',
            'course_ids'      => 'required|array',
            'course_ids.*'    => 'exists:courses,id',
        ]);

        foreach ($payload['prospecto_ids'] as $prospectoId) {
            $prospecto = Prospecto::findOrFail($prospectoId);
            $prospecto->courses()->syncWithoutDetaching($payload['course_ids']);
        }

        return response()->json(['message' => 'Cursos asignados correctamente']);
    }

    /**
     * Bulk unassign courses from prospectos
     */
    public function unassignCourses(Request $request)
    {
        $payload = $request->validate([
            'prospecto_ids'   => 'required|array',
            'prospecto_ids.*' => 'exists:prospectos,id',
            'course_ids'      => 'required|array',
            'course_ids.*'    => 'exists:courses,id',
        ]);

        foreach ($payload['prospecto_ids'] as $prospectoId) {
            $prospecto = Prospecto::findOrFail($prospectoId);
            $prospecto->courses()->detach($payload['course_ids']);
        }

        return response()->json(['message' => 'Cursos desasignados correctamente']);
    }

    public function bulkAssignCourses(Request $request)
    {
        $payload = $request->validate([
            'prospecto_ids'   => 'required|array',
            'prospecto_ids.*' => 'exists:prospectos,id',
            'course_ids'      => 'required|array',
            'course_ids.*'    => 'exists:courses,id',
        ]);

        foreach ($payload['prospecto_ids'] as $prospectoId) {
            $prospecto = Prospecto::findOrFail($prospectoId);
            $prospecto->courses()->syncWithoutDetaching($payload['course_ids']);
        }

        return response()->json(['message' => 'Cursos asignados correctamente']);
    }

    /**
     * List courses associated with one or more programas.
     */
    public function byPrograms(Request $request)
    {
        $data = $request->validate([
            'program_ids'   => 'required|array',
            'program_ids.*' => 'exists:tb_programas,id',
        ]);

        $courses = Course::select('courses.*')
            ->join('programa_course', 'courses.id', '=', 'programa_course.course_id')
            ->whereIn('programa_course.programa_id', $data['program_ids'])
            ->distinct()
            ->get();

        return response()->json($courses);
    }

    /**
     * Get available courses for students/prospectos
     */
    public function getAvailableCourses(Request $request)
    {
        $request->validate([
            'prospecto_ids' => 'required|array',
            'prospecto_ids.*' => 'exists:prospectos,id',
        ]);

        $prospectoIds = $request->prospecto_ids;

        if (count($prospectoIds) === 1) {
            $prospecto = Prospecto::with('programas.programa.courses')->find($prospectoIds[0]);
            $assignedCourseIds = $prospecto->courses()->pluck('courses.id')->toArray();

            $availableCourses = collect();
            foreach ($prospecto->programas as $estudiantePrograma) {
                if ($estudiantePrograma->programa) {
                    $availableCourses = $availableCourses->merge($estudiantePrograma->programa->courses);
                }
            }

            $availableCourses = $availableCourses->unique('id')
                ->whereNotIn('id', $assignedCourseIds)
                ->values();

            return response()->json($availableCourses);
        }

        $commonCourses = null;

        foreach ($prospectoIds as $prospectoId) {
            $prospecto = Prospecto::with('programas.programa.courses')->find($prospectoId);

            $currentCourses = collect();
            foreach ($prospecto->programas as $estudiantePrograma) {
                if ($estudiantePrograma->programa) {
                    $currentCourses = $currentCourses->merge($estudiantePrograma->programa->courses);
                }
            }

            $currentCourseIds = $currentCourses->pluck('id')->unique()->toArray();

            if ($commonCourses === null) {
                $commonCourses = $currentCourseIds;
            } else {
                $commonCourses = array_intersect($commonCourses, $currentCourseIds);
            }
        }

        $assignedToAll = Course::whereHas('prospectos', function ($query) use ($prospectoIds) {
            $query->whereIn('prospecto_id', $prospectoIds);
        }, '=', count($prospectoIds))->pluck('id')->toArray();

        $availableCourses = Course::with('programas')
            ->whereIn('id', $commonCourses)
            ->whereNotIn('id', $assignedToAll)
            ->get();

        return response()->json($availableCourses);
    }
}
