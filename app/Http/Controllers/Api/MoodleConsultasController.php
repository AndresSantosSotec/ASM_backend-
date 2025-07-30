<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MoodleQueryService;
use Illuminate\Http\Request;

class MoodleConsultasController extends Controller
{
    protected MoodleQueryService $queries;

    public function __construct(MoodleQueryService $queries)
    {
        $this->queries = $queries;
    }

    public function cursosPorCarnet(Request $request, $carnet = null)
    {
        $carnetInput = $carnet ?? $request->input('carnet');
        if (!$carnetInput) {
            return response()->json(['message' => 'El campo carnet es obligatorio'], 422);
        }

        $results = $this->queries->cursosPorCarnet($carnetInput);

        return response()->json(['data' => $results]);
    }

    public function cursosAprobados(Request $request, $carnet = null)
    {
        $carnetInput = $carnet ?? $request->input('carnet');
        if (!$carnetInput) {
            return response()->json(['message' => 'El campo carnet es obligatorio'], 422);
        }

        $results = $this->queries->cursosAprobados($carnetInput);

        return response()->json(['data' => $results]);
    }

    public function cursosReprobados(Request $request, $carnet = null)
    {
        $carnetInput = $carnet ?? $request->input('carnet');
        if (!$carnetInput) {
            return response()->json(['message' => 'El campo carnet es obligatorio'], 422);
        }

        $results = $this->queries->cursosReprobados($carnetInput);

        return response()->json(['data' => $results]);
    }
}

