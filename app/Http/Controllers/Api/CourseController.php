<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Prospecto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CourseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $courses = Course::with(['facilitator', 'programas'])
            ->when($request->search, fn($q) => $q->where(fn($q) => $q
                ->where('name', 'like', "%{$request->search}%")
                ->orWhere('code', 'like', "%{$request->search}%")
            ))
            ->when(in_array($request->area, ['common', 'specialty']),
                  fn($q) => $q->where('area', $request->area))
            ->when(in_array($request->status, ['draft', 'approved', 'synced']),
                  fn($q) => $q->where('status', $request->status))
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
     * Approve course
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

        $course->update(['status' => 'synced']);
        return response()->json($course);
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
}
