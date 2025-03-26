<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Pais;

class UbicacionController extends Controller
{
    public function getUbicacionByPais($paisId)
    {
        $pais = Pais::with('departamentos.municipios')->find($paisId);

        if (!$pais) {
            return response()->json(['error' => 'País no encontrado'], 404);
        }

        return response()->json($pais);
    }
}
