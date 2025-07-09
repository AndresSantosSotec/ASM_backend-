<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CoursePerfResource;
use App\Models\Course;
use Illuminate\Http\Request;

class CoursePerformanceController extends Controller
{
    public function index(Request $request)
    {
        $threshold = $request->input('threshold', 61);

        $query = Course::select('courses.*')
            ->leftJoin('inscripciones', 'courses.id', '=', 'inscripciones.course_id')
            ->when($request->periodo, fn($q) => $q->where('inscripciones.semestre', $request->periodo))
            ->groupBy('courses.id');

        $query->selectRaw('MAX(inscripciones.semestre) as period');
        $query->selectRaw('COUNT(DISTINCT inscripciones.prospecto_id) as students');
        $query->selectRaw('AVG(inscripciones.calificacion) as promedio');
        $query->selectRaw('SUM(CASE WHEN inscripciones.calificacion >= ? THEN 1 ELSE 0 END)/NULLIF(COUNT(inscripciones.id),0) as tasa_aprobacion', [$threshold]);

        $perPage   = $request->input('per_page', 15);
        $sortBy    = $request->input('sort_by', 'promedio');
        $direction = $request->input('direction', 'desc');

        $courses = $query->orderBy($sortBy, $direction)->paginate($perPage);

        $courses->getCollection()->each(function ($course) {
            $top = $course->inscripciones()->orderByDesc('calificacion')->first();
            if ($top) {
                $course->top_student = [
                    'id'    => $top->prospecto_id,
                    'name'  => optional($top->prospecto)->nombre_completo,
                    'grade' => $top->calificacion,
                ];
            } else {
                $course->top_student = null;
            }
        });

        return CoursePerfResource::collection($courses);
    }
}
