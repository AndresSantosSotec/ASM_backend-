<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CuotaProgramaEstudiante;
use Illuminate\Http\Request;

class CuotaController extends Controller
{
    public function byProspecto($prospectoId)
    {
        $cuotas = CuotaProgramaEstudiante::whereHas('estudiantePrograma', function ($q) use ($prospectoId) {
            $q->where('prospecto_id', $prospectoId);
        })->with('estudiantePrograma.programa')->get();

        return response()->json($cuotas);
    }

    public function byPrograma($estudianteProgramaId)
    {
        $cuotas = CuotaProgramaEstudiante::where('estudiante_programa_id', $estudianteProgramaId)->get();

        return response()->json($cuotas);
    }
}
