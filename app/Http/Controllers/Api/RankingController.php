<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RankingResource;
use App\Models\Prospecto;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class RankingController extends Controller
{
    public function index(Request $request)
    {
        $query = Prospecto::select('prospectos.*', 'p.nombre_del_programa')
            ->join('inscripciones', 'prospectos.id', '=', 'inscripciones.prospecto_id')
            ->leftJoin('estudiante_programa as ep', 'ep.prospecto_id', '=', 'prospectos.id')
            ->leftJoin('tb_programas as p', 'p.id', '=', 'ep.programa_id')
            ->when($request->semestre, fn($q) => $q->where('inscripciones.semestre', $request->semestre))
            ->groupBy('prospectos.id', 'p.total_cursos', 'p.nombre_del_programa');

        $query->selectRaw('SUM(inscripciones.calificacion * inscripciones.credits)/NULLIF(SUM(inscripciones.credits),0) as gpa_actual');
        $query->selectRaw('SUM(inscripciones.credits) as credits');
        $query->selectRaw('p.total_cursos as total_courses');
        $query->selectRaw('p.total_cursos * 3 as total_credits');
        $query->selectRaw('SUM(CASE WHEN inscripciones.calificacion IS NOT NULL THEN 1 ELSE 0 END) as courses_completed');
        $query->selectRaw('MAX(inscripciones.semestre) as semestre_actual');
        $query->selectRaw('SUM(inscripciones.credits)/NULLIF(p.total_cursos,0)*100 as progreso');

        if ($request->filled('gpa_min')) {
            $query->having('gpa_actual', '>=', $request->gpa_min);
        }
        if ($request->filled('gpa_max')) {
            $query->having('gpa_actual', '<=', $request->gpa_max);
        }

        $sortBy    = $request->input('sort_by', 'gpa_actual');
        $direction = $request->input('direction', 'desc');
        $perPage   = $request->input('per_page', 15);

        $ranked = $query->orderBy($sortBy, $direction)->paginate($perPage);

        $ranked->getCollection()->transform(function ($student, $index) use ($ranked) {
            $student->ranking_position = ($ranked->currentPage() - 1) * $ranked->perPage() + ($index + 1);
            $student->badges = $student->achievements()->pluck('tipo')->toArray();
            return $student;
        });

        return RankingResource::collection($ranked);
    }

    /**
     * Generate a PDF report with the student ranking
     */
    public function report(Request $request)
    {
        $query = Prospecto::select('prospectos.*', 'p.nombre_del_programa')
            ->join('inscripciones', 'prospectos.id', '=', 'inscripciones.prospecto_id')
            ->leftJoin('estudiante_programa as ep', 'ep.prospecto_id', '=', 'prospectos.id')
            ->leftJoin('tb_programas as p', 'p.id', '=', 'ep.programa_id')
            ->when($request->semestre, fn($q) => $q->where('inscripciones.semestre', $request->semestre))
            ->groupBy('prospectos.id', 'p.total_cursos', 'p.nombre_del_programa');

        $query->selectRaw('SUM(inscripciones.calificacion * inscripciones.credits)/NULLIF(SUM(inscripciones.credits),0) as gpa_actual');
        $query->selectRaw('SUM(inscripciones.credits) as credits');
        $query->selectRaw('p.total_cursos as total_courses');
        $query->selectRaw('p.total_cursos * 3 as total_credits');
        $query->selectRaw('MAX(inscripciones.semestre) as semestre_actual');
        $query->selectRaw('SUM(CASE WHEN inscripciones.calificacion IS NOT NULL THEN 1 ELSE 0 END) as courses_completed');
        $query->selectRaw('SUM(inscripciones.credits)/NULLIF(p.total_cursos,0)*100 as progreso');

        $sortBy    = $request->input('sort_by', 'gpa_actual');
        $direction = $request->input('direction', 'desc');

        $students = $query->orderBy($sortBy, $direction)->get();

        $pdf = Pdf::loadView('pdf.ranking-report', [
            'students' => $students,
        ]);

        return $pdf->download('ranking.pdf');
    }
}
