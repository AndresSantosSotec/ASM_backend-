<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Prospecto;
use Illuminate\Http\Request;

class ProspectoController extends Controller
{
    // Obtener todos los prospectos
    public function index()
    {
        $prospectos = Prospecto::all();

        return response()->json([
            'message' => 'Datos de prospectos obtenidos con éxito',
            'data' => $prospectos,
        ]);
    }

    // Crear un nuevo prospecto
    public function store(Request $request)
    {
        // Verificar que el usuario esté autenticado
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        $validated = $request->validate([
            'fecha' => 'required|date',
            'nombreCompleto' => 'required|string',
            'telefono' => 'required|string',
            'correoElectronico' => 'required|email',
            'genero' => 'required|string',
            'empresaDondeLaboraActualmente' => 'nullable|string',
            'puesto' => 'nullable|string',
            'notasGenerales' => 'nullable|string',
            'observaciones' => 'nullable|string',
            'interes' => 'nullable|string',
            'status' => 'nullable|string',
            'nota1' => 'nullable|string',
            'nota2' => 'nullable|string',
            'nota3' => 'nullable|string',
            'cierre' => 'nullable|string',
            'departamento' => 'required|string',
            'municipio' => 'required|string'
        ]);

        // Al crear el prospecto, se asigna el id del usuario autenticado
        $prospecto = Prospecto::create([
            'fecha' => $validated['fecha'],
            'nombre_completo' => $validated['nombreCompleto'],
            'telefono' => $validated['telefono'],
            'correo_electronico' => $validated['correoElectronico'],
            'genero' => $validated['genero'],
            'empresa_donde_labora_actualmente' => $validated['empresaDondeLaboraActualmente'] ?? null,
            'puesto' => $validated['puesto'] ?? null,
            'notas_generales' => $validated['notasGenerales'] ?? null,
            'observaciones' => $validated['observaciones'] ?? null,
            'interes' => $validated['interes'] ?? null,
            'status' => $validated['status'] ?? 'En seguimiento',
            'nota1' => $validated['nota1'] ?? null,
            'nota2' => $validated['nota2'] ?? null,
            'nota3' => $validated['nota3'] ?? null,
            'cierre' => $validated['cierre'] ?? null,
            'departamento' => $validated['departamento'],
            'municipio' => $validated['municipio'],
            'created_by' => $user->id,  // Aquí se guarda el id del usuario autenticado
        ]);

        return response()->json([
            'message' => 'Prospecto guardado con éxito',
            'data' => $prospecto,
        ], 201);
    }

    // Mostrar un prospecto específico
    public function show($id)
    {
        $prospecto = Prospecto::find($id);

        if (!$prospecto) {
            return response()->json([
                'message' => 'Prospecto no encontrado',
            ], 404);
        }

        return response()->json([
            'message' => 'Prospecto obtenido con éxito',
            'data' => $prospecto,
        ]);
    }

    // Actualizar un prospecto
    public function update(Request $request, $id)
    {
        // Verificar que el usuario esté autenticado
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        $prospecto = Prospecto::find($id);

        if (!$prospecto) {
            return response()->json([
                'message' => 'Prospecto no encontrado',
            ], 404);
        }

        $validated = $request->validate([
            'fecha' => 'nullable|date',
            'nombreCompleto' => 'nullable|string',
            'telefono' => 'nullable|string',
            'correoElectronico' => 'nullable|email',
            'genero' => 'nullable|string',
            'empresaDondeLaboraActualmente' => 'nullable|string',
            'puesto' => 'nullable|string',
            'notasGenerales' => 'nullable|string',
            'observaciones' => 'nullable|string',
            'interes' => 'nullable|string',
            'status' => 'nullable|string',
            'nota1' => 'nullable|string',
            'nota2' => 'nullable|string',
            'nota3' => 'nullable|string',
            'cierre' => 'nullable|string',
            'departamento' => 'nullable|string',
            'municipio' => 'nullable|string'
        ]);

        if (isset($validated['fecha'])) {
            $prospecto->fecha = $validated['fecha'];
        }
        if (isset($validated['nombreCompleto'])) {
            $prospecto->nombre_completo = $validated['nombreCompleto'];
        }
        if (isset($validated['telefono'])) {
            $prospecto->telefono = $validated['telefono'];
        }
        if (isset($validated['correoElectronico'])) {
            $prospecto->correo_electronico = $validated['correoElectronico'];
        }
        if (isset($validated['genero'])) {
            $prospecto->genero = $validated['genero'];
        }
        if (isset($validated['empresaDondeLaboraActualmente'])) {
            $prospecto->empresa_donde_labora_actualmente = $validated['empresaDondeLaboraActualmente'];
        }
        if (isset($validated['puesto'])) {
            $prospecto->puesto = $validated['puesto'];
        }
        if (isset($validated['notasGenerales'])) {
            $prospecto->notas_generales = $validated['notasGenerales'];
        }
        if (isset($validated['observaciones'])) {
            $prospecto->observaciones = $validated['observaciones'];
        }
        if (isset($validated['interes'])) {
            $prospecto->interes = $validated['interes'];
        }
        if (isset($validated['status'])) {
            $prospecto->status = $validated['status'];
        }
        if (isset($validated['nota1'])) {
            $prospecto->nota1 = $validated['nota1'];
        }
        if (isset($validated['nota2'])) {
            $prospecto->nota2 = $validated['nota2'];
        }
        if (isset($validated['nota3'])) {
            $prospecto->nota3 = $validated['nota3'];
        }
        if (isset($validated['cierre'])) {
            $prospecto->cierre = $validated['cierre'];
        }
        if (isset($validated['departamento'])) {
            $prospecto->departamento = $validated['departamento'];
        }
        if (isset($validated['municipio'])) {
            $prospecto->municipio = $validated['municipio'];
        }

        // Asignar el id del usuario que actualiza
        $prospecto->updated_by = $user->id;
        $prospecto->save();

        return response()->json([
            'message' => 'Prospecto actualizado con éxito',
            'data' => $prospecto,
        ]);
    }
    
    // Eliminar un prospecto
    public function destroy($id)
    {
        $prospecto = Prospecto::find($id);

        if (!$prospecto) {
            return response()->json([
                'message' => 'Prospecto no encontrado',
            ], 404);
        }

        $prospecto->delete();

        return response()->json([
            'message' => 'Prospecto eliminado con éxito',
        ]);
    }
}
