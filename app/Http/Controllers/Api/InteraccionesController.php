<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Interacciones;
use Illuminate\Http\Request;

class InteraccionesController extends Controller
{
    // Obtener todas las interacciones; si se envía un "lead_id" en la query,
    // se filtran las interacciones asociadas a ese prospecto.
    public function index(Request $request)
    {
        // Obtener el usuario autenticado
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }
        
        if ($request->has('id_lead')) {
            $leadId = $request->id_lead;
            $interacciones = Interacciones::where('id_lead', $leadId)->get();
        } else {
            // Si no se especifica lead_id, la lógica puede ser:
            // - Si el usuario es administrador, se muestran todas.
            // - Si no, se muestran solo las creadas por él.
            $isAdmin = strtolower($user->rol) === 'administrador';
            if ($isAdmin) {
                $interacciones = Interacciones::all();
            } else {
                $interacciones = Interacciones::where('created_by', $user->id)->get();
            }
        }
        
        return response()->json([
            'message' => 'Datos de interacciones obtenidos con éxito',
            'data'    => $interacciones,
        ]);
    }

    // Mostrar una interacción específica
    public function show($id)
    {
        $interaccion = Interacciones::find($id);
        if (!$interaccion) {
            return response()->json(['message' => 'Interacción no encontrada'], 404);
        }
        return response()->json([
            'message' => 'Interacción obtenida con éxito',
            'data'    => $interaccion,
        ]);
    }

    // Crear una nueva interacción
    public function store(Request $request)
    {
        // Verificar que el usuario esté autenticado
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        // Validar los datos recibidos
        $validated = $request->validate([
            'id_lead'         => 'required|integer',  // ID del prospecto (lead)
            'id_actividades'  => 'required|integer',  // ID de la actividad seleccionada
            'fecha'           => 'required|date',     // Fecha de la interacción
            'duracion'        => 'nullable|string',
            'notas'           => 'nullable|string',
        ]);

        // Forzar que el id del usuario autenticado se use para los campos relacionados
        $validated['id_asesor'] = $user->id;
        $validated['user_id']   = $user->id;
        $validated['created_by'] = $user->id;

        $interaccion = Interacciones::create($validated);

        return response()->json([
            'message' => 'Interacción guardada con éxito',
            'data'    => $interaccion,
        ], 201);
    }

    // Actualizar una interacción existente
    public function update(Request $request, $id)
    {
        // Verificar que el usuario esté autenticado
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }
        
        $interaccion = Interacciones::find($id);
        if (!$interaccion) {
            return response()->json(['message' => 'Interacción no encontrada'], 404);
        }

        // Validar los campos a actualizar
        $validated = $request->validate([
            'id_lead'         => 'sometimes|required|integer',
            'id_actividades'  => 'sometimes|required|integer',
            'fecha'           => 'sometimes|required|date',
            'duracion'        => 'nullable|string',
            'notas'           => 'nullable|string',
        ]);

        $interaccion->update($validated);

        return response()->json([
            'message' => 'Interacción actualizada con éxito',
            'data'    => $interaccion,
        ]);
    }

    // Eliminar una interacción
    public function destroy($id)
    {
        $interaccion = Interacciones::find($id);
        if (!$interaccion) {
            return response()->json(['message' => 'Interacción no encontrada'], 404);
        }

        $interaccion->delete();

        return response()->json([
            'message' => 'Interacción eliminada con éxito',
        ]);
    }
}
