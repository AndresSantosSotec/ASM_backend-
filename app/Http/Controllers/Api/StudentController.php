<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentResource;
use App\Models\Prospecto;

class StudentController extends Controller
{
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
