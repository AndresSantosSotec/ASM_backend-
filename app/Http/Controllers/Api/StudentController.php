<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentResource;
use App\Models\Prospecto;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'integer|exists:prospectos,id',
        ]);

        $prospectos = Prospecto::with([
            'programas.programa',
            'inscripciones.course',
            'gpaHist',
            'achievements',
        ])->whereIn('id', $request->ids)->get();

        return StudentResource::collection($prospectos);
    }

    public function show($id)
    {
        $prospecto = Prospecto::with([
            'programas.programa',
            'inscripciones.course',
            'gpaHist',
            'achievements'
        ])->findOrFail($id);

        return new StudentResource($prospecto);
    }
}
