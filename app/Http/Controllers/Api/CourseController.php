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
        // Obtener parámetros de paginación
        $perPage = $request->input('per_page', 15); // Default: 15 por página
        $perPage = min(max((int)$perPage, 1), 200); // Limitar entre 1 y 200

        $query = Course::with(['facilitator', 'programas'])
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
            ->when(
                $request->program_id,
                fn($q) => $q->whereHas('programas', fn($q) => $q->where('tb_programas.id', $request->program_id))
            )
            ->orderBy('created_at', 'desc');

        // Usar paginación Laravel estándar
        $courses = $query->paginate($perPage);

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

            // 🆕 Obtener área y créditos basándose en el código
            $courseMetadata = $this->getCourseMetadata($code);

            // Fechas por defecto
            $start = now();
            $end   = (clone $start)->addMonths(4);

            $newCourse = Course::create([
                'moodle_id'  => $courseData['moodle_id'],
                'name'       => $courseData['fullname'] ?: ('Curso '.$courseData['moodle_id']),
                'code'       => $code,
                'area'       => $courseMetadata['area'],
                'credits'    => $courseMetadata['credits'],
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
                'code' => $code,
                'area' => $courseMetadata['area'],
                'credits' => $courseMetadata['credits'],
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
     * Obtiene los metadatos del curso (área y créditos) basándose en el código.
     *
     * Reglas:
     * - BBA01-BBA29: common (3-5 créditos)
     * - BBACM24-BBACM29, BBABF25-BBABF29: specialty (4-5 créditos)
     * - BBA30-BBA33: specialty (5-8 créditos)
     * - MBA01-MBA19: specialty (4-8 créditos)
     * - Otros masters (MLDO, MKD, MMK, MHTM, MFIN, MPM): common 01-07, specialty 08-19
     */
    private function getCourseMetadata(string $code): array
    {
        // Extrae el número del código (ej: BBA14 → 14, MBA01 → 1)
        preg_match('/(\d+)$/', $code, $matches);
        $number = isset($matches[1]) ? (int)$matches[1] : 0;

        // Extrae el prefijo (ej: BBA14 → BBA, BBACM24 → BBACM)
        $prefix = preg_replace('/\d+$/', '', $code);

        // 🔵 BBA (Bachelor)
        if ($prefix === 'BBA') {
            if ($number >= 1 && $number <= 29) {
                return ['area' => 'common', 'credits' => ($number >= 18 && $number <= 29) ? 5 : 3];
            }
            if ($number >= 30 && $number <= 33) {
                return ['area' => 'specialty', 'credits' => ($number === 33) ? 8 : ($number >= 31 ? 6 : 5)];
            }
        }

        // 🔵 BBA CM (Commercial Management)
        if ($prefix === 'BBACM') {
            return ['area' => 'specialty', 'credits' => 5];
        }

        // 🔵 BBA BF (Banking and Fintech)
        if ($prefix === 'BBABF') {
            return ['area' => 'specialty', 'credits' => 5];
        }

        // 🟢 MBA (Master of Business Administration)
        if ($prefix === 'MBA') {
            if ($number >= 1 && $number <= 15) {
                return ['area' => 'specialty', 'credits' => ($number >= 8 && $number <= 14) ? 5 : 4];
            }
            if ($number >= 16 && $number <= 19) {
                return ['area' => 'specialty', 'credits' => ($number === 19) ? 8 : 6];
            }
        }

        // 🟢 MLDO (Master of Logistics)
        if ($prefix === 'MLDO') {
            if ($number >= 1 && $number <= 7) {
                return ['area' => 'common', 'credits' => 4];
            }
            if ($number >= 8 && $number <= 19) {
                return ['area' => 'specialty', 'credits' => ($number === 19) ? 8 : (($number >= 16 && $number <= 18) ? 6 : (($number === 8 || $number === 12 || $number === 14) ? 5 : 4))];
            }
        }

        // 🟢 MKD (Master of Digital Marketing)
        if ($prefix === 'MKD') {
            if ($number >= 1 && $number <= 7) {
                return ['area' => 'common', 'credits' => 4];
            }
            if ($number >= 8 && $number <= 19) {
                return ['area' => 'specialty', 'credits' => ($number === 19) ? 8 : (($number >= 16 && $number <= 18) ? 6 : (($number === 15) ? 5 : 4))];
            }
        }

        // 🟢 MMK/MMKD (Master of Marketing in Commercial Management)
        if ($prefix === 'MMK' || $prefix === 'MMKD') {
            if ($number >= 1 && $number <= 7) {
                return ['area' => 'common', 'credits' => 4];
            }
            if ($number >= 8 && $number <= 19) {
                return ['area' => 'specialty', 'credits' => ($number === 19) ? 8 : (($number >= 16 && $number <= 18) ? 6 : (($number === 10 || $number === 11 || $number === 15) ? 5 : (($number === 9 || $number === 12) ? 3 : 4)))];
            }
        }

        // 🟢 MHTM (Master in Human Talent Management)
        if ($prefix === 'MHTM') {
            if ($number >= 1 && $number <= 7) {
                return ['area' => 'common', 'credits' => 4];
            }
            if ($number >= 8 && $number <= 19) {
                return ['area' => 'specialty', 'credits' => ($number === 19) ? 8 : (($number >= 16 && $number <= 18) ? 6 : (($number === 15) ? 5 : 4))];
            }
        }

        // 🟢 MFIN (Master of Financial Management)
        if ($prefix === 'MFIN') {
            if ($number >= 1 && $number <= 7) {
                return ['area' => 'common', 'credits' => 4];
            }
            if ($number >= 8 && $number <= 19) {
                return ['area' => 'specialty', 'credits' => ($number === 19) ? 8 : (($number >= 16 && $number <= 18) ? 6 : (($number >= 9 && $number <= 14) ? 5 : 4))];
            }
        }

        // 🟢 MPM (Master of Project Management)
        if ($prefix === 'MPM') {
            if ($number >= 1 && $number <= 7) {
                return ['area' => 'common', 'credits' => 4];
            }
            if ($number >= 8 && $number <= 19) {
                return ['area' => 'specialty', 'credits' => ($number === 19) ? 8 : (($number >= 16 && $number <= 18) ? 6 : 5)];
            }
        }

        // Fallback: common, 3 créditos
        return ['area' => 'common', 'credits' => 3];
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
     * Mapea nombres de cursos a sus códigos específicos.
     */
    private function codeMap(): array
    {
        return [
            // ⚠️ === CORRECCIONES DE NOMBRES MAL ESCRITOS EN MOODLE ===
            // Estos son cursos que en Moodle tienen el prefijo incorrecto
            'MBA Gestión del Talento Humano y Liderazgo' => 'MHTM08',
            'MBA Gestión del Talento y Desarrollo Organizacional' => 'MHTM10',
            'BBA Contabilidad Financiera' => 'BBA15',
            
            // === BBA (Bachelor) - Common ===
            'BBA Comunicación y Redacción Ejecutiva' => 'BBA01',
            'BBA Razonamiento Crítico' => 'BBA02',
            'BBA Derecho Empresarial' => 'BBA03',
            'BBA Estadística aplicada' => 'BBA04',
            'BBA Introducción a la microeconomía' => 'BBA05',
            'BBA Fundamentos de la Negociación' => 'BBA06',
            'BBA Manejo de Crisis Económica' => 'BBA07',
            'BBA Principios de Estrategia (Manejo de Herramientas Estratégicas)' => 'BBA08',
            'BBA Introducción a la Macroeconomía' => 'BBA10',
            'BBA Excel Ejecutivo' => 'BBA11',
            'BBA Emprendimiento e innovación' => 'BBA12',
            'BBA Introducción al Marketing Digital' => 'BBA13',
            'BBA Contabilidad Aplicada' => 'BBA14',
            'BBA Contabilidad Financiera' => 'BBA15',
            'BBA Análisis de Mercados y Marketing de Servicios' => 'BBA16',
            'BBA Marketing Plan' => 'BBA17',
            'BBA Introducción de Big Data' => 'BBA18',
            'BBA Finanzas para Ejecutivos' => 'BBA19',
            'BBA Responsabilidad Social Corporativa' => 'BBA20',
            'BBA Planificación y Organización de la Producción' => 'BBA21',
            'BBA Gestión Comercial' => 'BBA22',
            'BBA Gestión Estratégica de Talento Humano' => 'BBA23',
            'BBA Inteligencia Artificial' => 'BBA24',
            'BBA Matemática Financiera' => 'BBA25',
            'BBA Finanzas para toma de decisiones' => 'BBA26',
            'BBA Innovación en Ventas' => 'BBA27',
            'BBA Administración tributaria' => 'BBA28',
            'BBA Power BI' => 'BBA29',

            // === BBA CM (Commercial Management) - Specialty ===
            'BBA CM Psicología y Análisis del Consumidor' => 'BBACM24',
            'BBA CM Prospección Estratégica en Ventas' => 'BBACM25',
            'BBA CM Presentación Efectiva de Ventas' => 'BBACM26',
            'BBA CM Negociación' => 'BBACM27',
            'BBA CM Manejo de Objeciones en Ventas' => 'BBACM28',
            'BBA CM Planeación Estratégica de Ventas' => 'BBACM29',

            // === BBA BF (Banking and Fintech) - Specialty ===
            'BBA BF Lavado de Activos' => 'BBABF25',
            'BBA BF Banca Digital' => 'BBABF26',
            'BBA BF Innovación en Finanzas: Fintech y Blockchain (Neobanca)' => 'BBABF27',
            'BBA BF Camino Disruptivo en Banca' => 'BBABF28',
            'BBA BF Banca Internacional' => 'BBABF29',

            // === BBA - Specialty (Final) ===
            'BBA Seminario de Gerencia' => 'BBA30',
            'BBA Proyecto de Grado I' => 'BBA31',
            'BBA Proyecto de Grado II' => 'BBA32',
            'BBA Certificación Internacional' => 'BBA33',

            // === MBA (Master of Business Administration) ===
            'MBA Gestión de Crisis y Resiliencia' => 'MBA01',
            'MBA Negociación y Resolución de Conflictos' => 'MBA02',
            'MBA Macroeconomía y Políticas Económicas' => 'MBA03',
            'MBA E-Business y Estrategias Digitales' => 'MBA04',
            'MBA Gerencia de Operaciones y Logística' => 'MBA05',
            'MBA Benchmarking y Competitividad' => 'MBA06',
            'MBA Comunicación Efectiva, Branding y Marca Personal' => 'MBA07',
            'MBA Big Data y Análisis de Datos' => 'MBA08',
            'MBA Marketing Estratégico' => 'MBA09',
            'MBA Estrategia Corporativa' => 'MBA10',
            'MBA Experiencia del Cliente y CRM' => 'MBA11',
            'MBA Análisis de Estados Financieros' => 'MBA12',
            'MBA Cash Flow Management' => 'MBA13',
            'MBA Finanzas Corporativas' => 'MBA14',
            'MBA Seminario de Gerencia' => 'MBA15',
            'MBA Escritura de Caso' => 'MBA16',
            'MBA Proyecto de Grado I' => 'MBA17',
            'MBA Proyecto de Grado II' => 'MBA18',
            'MBA Certificación Internacional' => 'MBA19',

            // === MLDO (Master of Logistics) ===
            'MLDO Gestión de Crisis y Resiliencia' => 'MLDO01',
            'MLDO Negociación y Resolución de Conflictos' => 'MLDO02',
            'MLDO Macroeconomía y Políticas Económicas' => 'MLDO03',
            'MLDO Gerencia de Operaciones y Logística' => 'MLDO04',
            'MLDO Benchmarking y Competitividad' => 'MLDO05',
            'MLDO Comunicación Efectiva, Branding y Marca Personal' => 'MLDO06',
            'MLDO Big Data y Análisis de Datos' => 'MLDO07',
            'MLDO Dirección y Gestión Avanzada de la Cadena de Suministro' => 'MLDO08',
            'MLDO Logística y Transporte Nacional e Internacional' => 'MLDO09',
            'MLDO Análisis Predictivo y Gestión de la Demanda' => 'MLDO10',
            'MLDO Gestión de Riesgos y Cumplimiento Normativo en Logística' => 'MLDO11',
            'MLDO Finanzas y Optimización de Costos en Logística' => 'MLDO12',
            'MLDO Lean Management y Mejora Continua' => 'MLDO13',
            'MLDO Transformación Digital y Automatización en Logística' => 'MLDO14',
            'MLDO Seminario de Gerencia' => 'MLDO15',
            'MLDO Escritura de Caso' => 'MLDO16',
            'MLDO Capstone Project I' => 'MLDO17',
            'MLDO Capstone Project II and Business Plan' => 'MLDO18',
            'MLDO Certificación Internacional' => 'MLDO19',

            // === MKD (Master of Digital Marketing) ===
            'MKD Gestión de Crisis y Resiliencia' => 'MKD01',
            'MKD Negociación y Resolución de Conflictos' => 'MKD02',
            'MKD Macroeconomía y Políticas Económicas' => 'MKD03',
            'MKD Gerencia de Operaciones y Logística' => 'MKD04',
            'MKD Benchmarking y Competitividad' => 'MKD05',
            'MKD Comunicación Efectiva, Branding y Marca Personal' => 'MKD06',
            'MKD Big Data y Análisis de Datos' => 'MKD07',
            'MKD Estrategias de Lead Generation y Omnicanalidad' => 'MKD08',
            'MKD Innovación y Transformación Digital en Marketing' => 'MKD09',
            'MKD Estrategias de Marketing de Afiliados y Asociados' => 'MKD10',
            'MKD SEO, SEM y Optimización de Motores de Búsqueda' => 'MKD11',
            'MKD Gestión Estratégica de Redes Sociales' => 'MKD12',
            'MKD Gestión Financiera de Proyectos Digitales' => 'MKD13',
            'MKD Neuromarketing y Psicología del Consumidor' => 'MKD14',
            'MKD Seminario de Gerencia' => 'MKD15',
            'MKD Escritura de Caso' => 'MKD16',
            'MKD Capstone Project I' => 'MKD17',
            'MKD Capstone Project II and Business Plan' => 'MKD18',
            'MKD Certificación Internacional' => 'MKD19',

            // === MMK (Master of Marketing in Commercial Management) ===
            'MMK Gestión de Crisis y Resiliencia' => 'MMK01',
            'MMKD Gestión de Crisis y Resiliencia' => 'MMK01',
            'MMK Negociación y Resolución de Conflictos' => 'MMK02',
            'MMKD Negociación y Resolución de Conflictos' => 'MMK02',
            'MMK Macroeconomía y Políticas Económicas' => 'MMK03',
            'MMKD Macroeconomía y Políticas Económicas' => 'MMK03',
            'MMK Gerencia de Operaciones y Logística' => 'MMK04',
            'MMKD Gerencia de Operaciones y Logística' => 'MMK04',
            'MMK Benchmarking y Competitividad' => 'MMK05',
            'MMKD Benchmarking y Competitividad' => 'MMK05',
            'MMK Comunicación Efectiva, Branding y Marca Personal' => 'MMK06',
            'MMKD Comunicación Efectiva, Branding y Marca Personal' => 'MMK06',
            'MMK Big Data y Análisis de Datos' => 'MMK07',
            'MMKD Big Data y Análisis de Datos' => 'MMK07',
            'MMK Marketing Estratégico Financiero' => 'MMK08',
            'MMKD Marketing Estratégico Financiero' => 'MMK08',
            'MMK Gestión y Dirección de Equipos Comerciales' => 'MMK09',
            'MMKD Gestión y Dirección de Equipos Comerciales' => 'MMK09',
            'MMK Key Account Management' => 'MMK10',
            'MMKD Key Account Management' => 'MMK10',
            'MMK Analítica para Mercadeo y Ventas' => 'MMK11',
            'MMKD Analítica para Mercadeo y Ventas' => 'MMK11',
            'MMK Estrategias Digitales en la Gestión Comercial' => 'MMK12',
            'MMKD Estrategias Digitales en la Gestión Comercial' => 'MMK12',
            'MMK Negociación Avanzada y Gestión de Conflictos' => 'MMK13',
            'MMKD Negociación Avanzada y Gestión de Conflictos' => 'MMK13',
            'MMK Comportamiento del Consumidor y Neuromarketing' => 'MMK14',
            'MMKD Comportamiento del Consumidor y Neuromarketing' => 'MMK14',
            'MMK Seminario de Gerencia' => 'MMK15',
            'MMKD Seminario de Gerencia' => 'MMK15',
            'MMK Escritura de Caso' => 'MMK16',
            'MMKD Escritura de Caso' => 'MMK16',
            'MMK Capstone Project I' => 'MMK17',
            'MMKD Capstone Project I' => 'MMK17',
            'MMK Capstone Project II and Business Plan' => 'MMK18',
            'MMKD Capstone Project II and Business Plan' => 'MMK18',
            'MMK Certificación Internacional' => 'MMK19',
            'MMKD Certificación Internacional' => 'MMK19',

            // === MHTM (Master in Human Talent Management) ===
            'MHTM Gestión de Crisis y Resiliencia' => 'MHTM01',
            'MHTM Negociación y Resolución de Conflictos' => 'MHTM02',
            'MHTM Macroeconomía y Políticas Económicas' => 'MHTM03',
            'MHTM Gerencia de Operaciones y Logística' => 'MHTM04',
            'MHTM Benchmarking y Competitividad' => 'MHTM05',
            'MHTM Comunicación Efectiva, Branding y Marca Personal' => 'MHTM06',
            'MHTM Big Data y Análisis de Datos' => 'MHTM07',
            'MHTM Liderazgo de Equipos de Alto Rendimiento y Cultura Organizacional' => 'MHTM08',
            'MHTM Legislación Laboral y Compliance Global' => 'MHTM09',
            'MHTM Gestión del Talento y Desarrollo Organizacional' => 'MHTM10',
            'MHTM Reclutamiento Estratégico y Retención de Talento' => 'MHTM11',
            'MHTM Métricas y Análisis de Rendimiento en Talento Humano' => 'MHTM12',
            'MHTM Finanzas para la Gestión del Talento Humano' => 'MHTM13',
            'MHTM Transformación Digital y Ética en la Gestión del Talento Humano' => 'MHTM14',
            'MHTM Seminario de Gerencia' => 'MHTM15',
            'MHTM Escritura de Caso' => 'MHTM16',
            'MHTM Capstone Project I' => 'MHTM17',
            'MHTM Capstone Project II and Business Plan' => 'MHTM18',
            'MHTM Certificación Internacional' => 'MHTM19',

            // === MFIN (Master of Financial Management) ===
            'MFIN Gestión de Crisis y Resiliencia' => 'MFIN01',
            'MFIN Negociación y Resolución de Conflictos' => 'MFIN02',
            'MFIN Macroeconomía y Políticas Económicas' => 'MFIN03',
            'MFIN Gerencia de Operaciones y Logística' => 'MFIN04',
            'MFIN Benchmarking y Competitividad' => 'MFIN05',
            'MFIN Comunicación Efectiva, Branding y Marca Personal' => 'MFIN06',
            'MFIN Big Data y Análisis de Datos' => 'MFIN07',
            'MFIN Planeación Financiera y Presupuestaria' => 'MFIN08',
            'MFIN Valoración de Empresas y Estrategias de M&A' => 'MFIN09',
            'MFIN Finanzas Corporativas Internacionales' => 'MFIN10',
            'MFIN Gestión de Riesgos Financieros y Seguros' => 'MFIN11',
            'MFIN Finanzas Sostenibles y ESG' => 'MFIN12',
            'MFIN Inversiones y Gestión de Activos' => 'MFIN13',
            'MFIN Innovación Financiera y Tecnologías Fintech' => 'MFIN14',
            'MFIN Seminario de Gerencia' => 'MFIN15',
            'MFIN Escritura de Caso' => 'MFIN16',
            'MFIN Capstone Project I' => 'MFIN17',
            'MFIN Capstone Project II and Business Plan' => 'MFIN18',
            'MFIN Certificación Internacional' => 'MFIN19',

            // === MPM (Master of Project Management) ===
            'MPM Gestión de Crisis y Resiliencia' => 'MPM01',
            'MPM Negociación y Resolución de Conflictos' => 'MPM02',
            'MPM Macroeconomía y Políticas Económicas' => 'MPM03',
            'MPM Gerencia de Operaciones y Logística' => 'MPM04',
            'MPM Benchmarking y Competitividad' => 'MPM05',
            'MPM Comunicación Efectiva, Branding y Marca Personal' => 'MPM06',
            'MPM Big Data y Análisis de Datos' => 'MPM07',
            'MPM Formulación y Evaluación de Proyectos' => 'MPM08',
            'MPM Metodología SCRUM y enfoque ágil' => 'MPM09',
            'MPM Gestión del tiempo, presupuestos y costos en los proyectos' => 'MPM10',
            'MPM Gestión de la calidad, riesgos y recursos en los proyectos' => 'MPM11',
            'MPM Herramientas y Metodologías Ágiles para Sistemas de Gestión de Proyectos' => 'MPM12',
            'MPM Habilidades de un PMP' => 'MPM13',
            'MPM Design Thinking Pro' => 'MPM14',
            'MPM Seminario de Gerencia' => 'MPM15',
            'MPM Escritura de Caso' => 'MPM16',
            'MPM Capstone Project I' => 'MPM17',
            'MPM Capstone Project II and Business Plan' => 'MPM18',
            'MPM Certificación Internacional' => 'MPM19',
        ];
    }

    /**
     * Genera un código de curso basado en programas académicos reales.
     *
     * Proceso de identificación:
     * 1. Limpia el título (quita "Noviembre Lunes 2025")
     * 2. Busca coincidencia exacta en el mapa de códigos
     * 3. Busca coincidencia parcial por nombre de curso
     * 4. Extrae el prefijo del programa (MBA, BBA, MFIN, etc.)
     *
     * Ejemplo: "Noviembre Lunes 2025 MBA Gestión del Talento Humano y Liderazgo"
     *   → Limpia a: "MBA Gestión del Talento Humano y Liderazgo"
     *   → No encuentra exacto
     *   → Busca "Gestión del Talento" → No encuentra
     *   → Extrae prefijo: MBA
     *   → Retorna: "MBA"
     */
    private function generateCourseCode(string $shortnameOrFullname, ?string $fullname = null): string
    {
        // Obtener el título canónico (sin fechas ni días de la semana)
        $title = $this->canonicalTitle($fullname ?: $shortnameOrFullname);
        $map = $this->codeMap();

        Log::info('[generateCourseCode] Procesando curso', [
            'original' => $shortnameOrFullname,
            'fullname' => $fullname,
            'canonical_title' => $title
        ]);

        // 1️⃣ Búsqueda EXACTA en el mapa
        if (isset($map[$title])) {
            Log::info('[generateCourseCode] ✅ Coincidencia EXACTA encontrada', [
                'title' => $title,
                'code' => $map[$title]
            ]);
            return $map[$title];
        }

        // 2️⃣ Búsqueda PARCIAL: buscar por nombre del curso sin el prefijo del programa
        // Ejemplo: "MBA Gestión de Crisis y Resiliencia" → buscar "Gestión de Crisis y Resiliencia"
        $programPrefixes = [
            'BBA CM', 'BBA BF',  // BBA especializados (primero)
            'MMKD', 'MHTM', 'MLDO', 'MHHRR', 'MDGP',  // Masters especializados
            'MBA', 'BBA', 'MFIN', 'MPM', 'MKD', 'MDM', 'MGP',  // Masters y programas generales
            'EMBA', 'DBA', 'MSc', 'PhD',  // Doctorados y especiales
            'TEMP', 'MMK'  // Temporal y otros
        ];

        $detectedPrefix = null;
        $courseNameWithoutPrefix = $title;

        // Detectar el prefijo del programa
        foreach ($programPrefixes as $prefix) {
            $pattern = '/^' . preg_quote($prefix, '/') . '\s+/i';
            if (preg_match($pattern, $title, $matches)) {
                $detectedPrefix = strtoupper($prefix);
                $courseNameWithoutPrefix = trim(preg_replace($pattern, '', $title));
                Log::info('[generateCourseCode] Prefijo detectado', [
                    'prefix' => $detectedPrefix,
                    'course_name' => $courseNameWithoutPrefix
                ]);
                break;
            }
        }

        // Si detectamos un prefijo, buscar en el mapa con ese prefijo + nombre del curso
        if ($detectedPrefix && $courseNameWithoutPrefix) {
            // Buscar coincidencias parciales por similitud de texto
            foreach ($map as $mapKey => $mapCode) {
                // Normalizar para comparación
                $normalizedMapKey = mb_strtolower(trim($mapKey));
                $normalizedTitle = mb_strtolower(trim($title));
                $normalizedCourseName = mb_strtolower(trim($courseNameWithoutPrefix));

                // Coincidencia exacta ignorando mayúsculas/minúsculas
                if ($normalizedMapKey === $normalizedTitle) {
                    Log::info('[generateCourseCode] ✅ Coincidencia PARCIAL (case-insensitive)', [
                        'map_key' => $mapKey,
                        'code' => $mapCode
                    ]);
                    return $mapCode;
                }

                // Coincidencia por nombre del curso (sin prefijo)
                // Ejemplo: "Gestión de Crisis y Resiliencia" en "MBA Gestión de Crisis y Resiliencia"
                if (strlen($normalizedCourseName) > 10 && strpos($normalizedMapKey, $normalizedCourseName) !== false) {
                    // Verificar que el código también empiece con el prefijo detectado
                    if (strpos($mapCode, $detectedPrefix) === 0) {
                        Log::info('[generateCourseCode] ✅ Coincidencia PARCIAL por nombre de curso', [
                            'detected_prefix' => $detectedPrefix,
                            'course_name' => $courseNameWithoutPrefix,
                            'matched_key' => $mapKey,
                            'code' => $mapCode
                        ]);
                        return $mapCode;
                    }
                }
            }
        }

        // 3️⃣ Fallback: Si no encontramos en el mapa, devolver solo el prefijo del programa
        if ($detectedPrefix) {
            Log::info('[generateCourseCode] ⚠️ No se encontró en el mapa, usando prefijo', [
                'prefix' => $detectedPrefix
            ]);
            return $detectedPrefix;
        }

        // 4️⃣ Último recurso: limpiar el shortname/fullname
        $src = $shortnameOrFullname ?: $title;
        $base = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $src));

        if ($base === '') {
            $base = 'COURSE' . time();
        }

        $maxLen = 50;
        if (strlen($base) > $maxLen) {
            $base = substr($base, 0, $maxLen);
        }

        Log::warning('[generateCourseCode] ⚠️ No se pudo identificar, usando fallback', [
            'fallback_code' => $base
        ]);

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
