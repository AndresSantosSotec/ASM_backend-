<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TareasGen;

class TareasGenController extends Controller
{
    /**
     * Muestra una lista de tareas.
     * Si se envía el parámetro "asesor_id", se filtra por ese campo.
     * Si el usuario no es administrador, se muestran solo las tareas creadas por él.
     */
    public function index(Request $request)
    {
        // Obtener el usuario autenticado
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        // Si se pasa un parámetro "asesor_id" en la query, se filtra por ese asesor
        if ($request->has('asesor_id')) {
            $asesorId = $request->asesor_id;
            $tareas = TareasGen::where('asesor_id', $asesorId)->get();
        } else {
            // Si no se especifica, se determina la lógica:
            // - Si el usuario es administrador, se muestran todas las tareas.
            // - Si no, se muestran solo las tareas creadas por el usuario (campo "creado_por").
            $isAdmin = strtolower($user->rol) === 'administrador';
            if ($isAdmin) {
                $tareas = TareasGen::all();
            } else {
                $tareas = TareasGen::where('creado_por', $user->id)->get();
            }
        }

        return response()->json([
            'message' => 'Datos de tareas obtenidos con éxito',
            'data'    => $tareas,
        ]);
    }

    /**
     * Muestra una tarea específica.
     */
    public function show($id)
    {
        $tarea = TareasGen::find($id);
        if (!$tarea) {
            return response()->json(['message' => 'Tarea no encontrada'], 404);
        }
        return response()->json([
            'message' => 'Tarea obtenida con éxito',
            'data'    => $tarea,
        ]);
    }

    /**
     * Almacena una nueva tarea.
     * Se asigna automáticamente el asesor y creador según el usuario autenticado.
     */
    public function store(Request $request)
    {
        // Verificar que el usuario esté autenticado
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        // Validar los datos recibidos
        $validated = $request->validate([
            'titulo'      => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'fecha'       => 'required|date',
            'hora_inicio' => 'nullable|date_format:H:i',
            'hora_fin'    => 'nullable|date_format:H:i',
            'tipo'        => 'required|string|max:50',
            'completada'  => 'boolean',
        ]);

        // Forzar que el asesor y el creador sean el usuario autenticado
        $validated['asesor_id']  = $user->id;
        $validated['creado_por'] = $user->id;

        $tarea = TareasGen::create($validated);

        return response()->json([
            'message' => 'Tarea guardada con éxito',
            'data'    => $tarea,
        ], 201);
    }

    /**
     * Actualiza una tarea existente.
     */
    public function update(Request $request, $id)
    {
        // Verificar que el usuario esté autenticado
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        $tarea = TareasGen::find($id);
        if (!$tarea) {
            return response()->json(['message' => 'Tarea no encontrada'], 404);
        }

        $validated = $request->validate([
            'titulo'      => 'sometimes|required|string|max:255',
            'descripcion' => 'sometimes|nullable|string',
            'fecha'       => 'sometimes|required|date',
            'hora_inicio' => 'sometimes|nullable|date_format:H:i',
            'hora_fin'    => 'sometimes|nullable|date_format:H:i',
            'tipo'        => 'sometimes|required|string|max:50',
            'completada'  => 'sometimes|boolean',
        ]);

        $tarea->update($validated);

        return response()->json([
            'message' => 'Tarea actualizada con éxito',
            'data'    => $tarea,
        ]);
    }

    /**
     * Elimina una tarea.
     */
    public function destroy($id)
    {
        $tarea = TareasGen::find($id);
        if (!$tarea) {
            return response()->json(['error' => 'Tarea no encontrada'], 404);
        }

        $tarea->delete();

        return response()->json([
            'message' => 'Tarea eliminada con éxito',
        ]);
    }
}
