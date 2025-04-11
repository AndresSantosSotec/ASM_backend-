<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Actividades;
use Illuminate\Http\Request;

class ActividadesController extends Controller
{
    // Mostrar todos los registros
    public function index()
{
    $actividades = Actividades::all()->map(function($actividad) {
        return [
            'id' => $actividad->id,
            'nombre' => $actividad->actividades, // Renombramos el campo
        ];
    });
    return response()->json($actividades);
}

    // Mostrar un registro específico
    public function show($id)
    {
        $actividad = Actividades::find($id);
        if ($actividad) {
            return response()->json($actividad);
        } else {
            return response()->json(['message' => 'Actividad no encontrada'], 404);
        }
    }

    // Crear un nuevo registro
    public function store(Request $request)
    {
        $actividad = Actividades::create($request->all());
        return response()->json($actividad, 201);
    }

    // Actualizar un registro existente
    public function update(Request $request, $id)
    {
        $actividad = Actividades::find($id);
        if ($actividad) {
            $actividad->update($request->all());
            return response()->json($actividad);
        } else {
            return response()->json(['message' => 'Actividad no encontrada'], 404);
        }
    }

    // Eliminar un registro
    public function destroy($id)
    {
        $actividad = Actividades::find($id);
        if ($actividad) {
            $actividad->delete();
            return response()->json(['message' => 'Actividad eliminada']);
        } else {
            return response()->json(['message' => 'Actividad no encontrada'], 404);
        }
    }
}
