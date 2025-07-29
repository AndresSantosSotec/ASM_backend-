<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Prospecto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Programa;


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
            'moodle_id'      => 'nullable|integer|unique:courses',
            'name'           => 'required|string|max:255',
            'code'           => 'required|string|max:50|unique:courses',
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

        // Creamos el curso
        $course = Course::create($validator->validated());

        // Sincronizamos la pivote programa_course
        if (!empty($data['program_ids'])) {
            $course->programas()->sync($data['program_ids']);
        }

        // Cargamos relaciones para la respuesta
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
            'moodle_id'      => "sometimes|integer|unique:courses,moodle_id,{$course->id}",
            'name'           => 'sometimes|required|string|max:255',
            'code'           => "sometimes|required|string|max:50|unique:courses,code,{$course->id}",
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

        // Actualizamos datos básicos
        $course->update($validator->validated());

        // Si mandan program_ids, sincronizamos; si mandan vacío, desvincula todos
        if (array_key_exists('program_ids', $data)) {
            $course->programas()->sync($data['program_ids'] ?? []);
        }

        // Cargamos relaciones
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
     * Approve course for the Falilitador module
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
     * Sync course to Moodle
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
     * Sync multiple Moodle courses by ID.
     */
    public function bulkSyncToMoodle(Request $request)
    {
        $payload = $request->validate([
            'moodle_ids'   => 'required|array',
            'moodle_ids.*' => 'integer',
        ]);

        $service = app(\App\Services\MoodleService::class);
        $synced = [];

        foreach ($payload['moodle_ids'] as $moodleId) {
            if ($course = $service->syncCourse($moodleId)) {
                $synced[] = $course;
            }
        }

        return response()->json($synced);
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

    //traer cursos displnipes por programa, por estudiante que puede llevar
    // los cursos, y para asiganacion masiva si se seleciona mas de
    //traer los cursos que se puedan asignar a ambos
    //por medio de la tabla pivote couse program que es dede donde se resghirtarn esos cursos pro programa
    /**
     * Get available courses for students/prospectos
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAvailableCourses(Request $request)
    {
        $request->validate([
            'prospecto_ids' => 'required|array',
            'prospecto_ids.*' => 'exists:prospectos,id',
        ]);

        $prospectoIds = $request->prospecto_ids;

        // Si es solo un estudiante
        if (count($prospectoIds) === 1) {
            $prospecto = Prospecto::with('programas.programa.courses')->find($prospectoIds[0]);

            // Cursos ya asignados al estudiante
            $assignedCourseIds = $prospecto->courses()->pluck('courses.id')->toArray();

            // Cursos disponibles según sus programas
            $availableCourses = collect();
            foreach ($prospecto->programas as $estudiantePrograma) {
                if ($estudiantePrograma->programa) {
                    $availableCourses = $availableCourses->merge($estudiantePrograma->programa->courses);
                }
            }

            // Eliminar duplicados y cursos ya asignados
            $availableCourses = $availableCourses->unique('id')
                ->whereNotIn('id', $assignedCourseIds)
                ->values();

            return response()->json($availableCourses);
        }

        // Para múltiples estudiantes - intersectamos los cursos de sus programas
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

        // Obtenemos los cursos comunes que no estén asignados a TODOS los estudiantes
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
