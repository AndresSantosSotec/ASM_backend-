<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RankingResource;
use App\Models\Prospecto;
use Illuminate\Http\Request;

class RankingController extends Controller
{
    public function index(Request $request)
    {
        $query = Prospecto::select('prospectos.*')
            ->join('inscripciones', 'prospectos.id', '=', 'inscripciones.prospecto_id')
            ->leftJoin('estudiante_programa as ep', 'ep.prospecto_id', '=', 'prospectos.id')
            ->leftJoin('tb_programas as p', 'p.id', '=', 'ep.programa_id')
            ->when($request->semestre, fn($q) => $q->where('inscripciones.semestre', $request->semestre))
            ->groupBy('prospectos.id', 'p.total_cursos');

        $query->selectRaw('SUM(inscripciones.calificacion * inscripciones.credits)/NULLIF(SUM(inscripciones.credits),0) as gpa_actual');
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

        return RankingResource::collection($ranked);
    }
}
