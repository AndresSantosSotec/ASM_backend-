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
        $query = Course::with('facilitator');

        // Filtros
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->has('area') && in_array($request->area, ['common', 'specialty'])) {
            $query->where('area', $request->area);
        }

        if ($request->has('status') && in_array($request->status, ['draft', 'approved', 'synced'])) {
            $query->where('status', $request->status);
        }

        $courses = $query->get();

        return response()->json($courses);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:courses',
            'area' => 'required|in:common,specialty',
            'credits' => 'required|integer|min:1|max:10',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'schedule' => 'required|string|max:255',
            'duration' => 'required|string|max:100',
            'facilitator_id' => 'nullable|exists:users,id',
            'carrera' => 'nullable|string|max:255', // Si es string
            // 'carrera' => 'nullable|exists:programas,id', // Si es id de programa
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $course = Course::create($validator->validated());

        return response()->json($course, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $course = Course::with('facilitator')->findOrFail($id);
        return response()->json($course);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $course = Course::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|max:50|unique:courses,code,' . $course->id,
            'area' => 'sometimes|required|in:common,specialty',
            'credits' => 'sometimes|required|integer|min:1|max:10',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
            'schedule' => 'sometimes|required|string|max:255',
            'duration' => 'sometimes|required|string|max:100',
            'facilitator_id' => 'nullable|exists:users,id',
            'status' => 'sometimes|in:draft,approved,synced',
            'carrera' => 'nullable|string|max:255', // Si es string
            // 'carrera' => 'nullable|exists:programas,id', // Si es id de programa
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $course->update($validator->validated());

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
            return response()->json(['message' => 'No se puede aprobar un curso sin facilitador asignado'], 422);
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
            return response()->json(['message' => 'Solo se pueden sincronizar cursos aprobados'], 422);
        }

        // Aquí iría la lógica real de sincronización con Moodle
        // Por ahora simulamos la sincronización

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

    public function assignCourses(Request $request)
    {
        $payload = $request->validate([
            'prospecto_ids' => 'required|array',
            'prospecto_ids.*' => 'exists:prospectos,id',
            'course_ids'    => 'required|array',
            'course_ids.*'  => 'exists:courses,id',
        ]);

        // Por cada prospecto, sincronizamos (o adjuntamos) los cursos:
        foreach ($payload['prospecto_ids'] as $prospectoId) {
            $prospecto = Prospecto::findOrFail($prospectoId);
            // attach() agregará nuevos registros en curso_prospecto; si quieres reemplazar, usar sync():
            $prospecto->courses()->syncWithoutDetaching($payload['course_ids']);
        }

        return response()->json(['message' => 'Cursos asignados correctamente']);
    }
}
