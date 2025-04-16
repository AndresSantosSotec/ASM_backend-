<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Prospecto;
use Illuminate\Http\Request;

class ProspectoController extends Controller
{
    /**
     * Listar prospectos.
     * - Si es administrador, devuelve todos.
     * - Si no, solo los creados por el propio usuario.
     * Cada prospecto incluye la relación `creator`.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        $isAdmin = strtolower($user->rol) === 'administrador';

        $query = Prospecto::with('creator');
        if (! $isAdmin) {
            $query->where('created_by', $user->id);
        }

        $prospectos = $query->get();

        return response()->json([
            'message' => 'Datos de prospectos obtenidos con éxito',
            'data'    => $prospectos,
        ]);
    }

    /**
     * Crear un prospecto y asignar `created_by` al usuario autenticado.
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        if (! $user) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        $v = $request->validate([
            'fecha'                         => 'required|date',
            'nombreCompleto'                => 'required|string',
            'telefono'                      => 'required|string',
            'correoElectronico'             => 'required|email',
            'genero'                        => 'required|string',
            'empresaDondeLaboraActualmente' => 'nullable|string',
            'puesto'                        => 'nullable|string',
            'notasGenerales'                => 'nullable|string',
            'observaciones'                 => 'nullable|string',
            'interes'                       => 'nullable|string',
            'status'                        => 'nullable|string',
            'nota1'                         => 'nullable|string',
            'nota2'                         => 'nullable|string',
            'nota3'                         => 'nullable|string',
            'cierre'                        => 'nullable|string',
            'departamento'                  => 'required|string',
            'municipio'                     => 'required|string',
        ]);

        $prospecto = Prospecto::create([
            'fecha'                            => $v['fecha'],
            'nombre_completo'                  => $v['nombreCompleto'],
            'telefono'                         => $v['telefono'],
            'correo_electronico'               => $v['correoElectronico'],
            'genero'                           => $v['genero'],
            'empresa_donde_labora_actualmente' => $v['empresaDondeLaboraActualmente'] ?? null,
            'puesto'                           => $v['puesto'] ?? null,
            'notas_generales'                  => $v['notasGenerales'] ?? null,
            'observaciones'                    => $v['observaciones'] ?? null,
            'interes'                          => $v['interes'] ?? null,
            'status'                           => $v['status'] ?? 'En seguimiento',
            'nota1'                            => $v['nota1'] ?? null,
            'nota2'                            => $v['nota2'] ?? null,
            'nota3'                            => $v['nota3'] ?? null,
            'cierre'                           => $v['cierre'] ?? null,
            'departamento'                     => $v['departamento'],
            'municipio'                        => $v['municipio'],
            'created_by'                       => $user->id,
        ]);

        // Cargamos la relación antes de devolver
        $prospecto->load('creator');

        return response()->json([
            'message' => 'Prospecto guardado con éxito',
            'data'    => $prospecto,
        ], 201);
    }

    /**
     * Mostrar un prospecto con su creador.
     */
    public function show($id)
    {
        $prospecto = Prospecto::with('creator')->find($id);

        if (! $prospecto) {
            return response()->json(['message' => 'Prospecto no encontrado'], 404);
        }

        return response()->json([
            'message' => 'Prospecto obtenido con éxito',
            'data'    => $prospecto,
        ]);
    }

    /**
     * Actualizar un prospecto.
     * Mapeamos camelCase → snake_case y asignamos `updated_by`.
     */
    public function update(Request $request, $id)
    {
        $user = auth()->user();
        if (! $user) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        $prospecto = Prospecto::find($id);
        if (! $prospecto) {
            return response()->json(['message' => 'Prospecto no encontrado'], 404);
        }

        $v = $request->validate([
            'fecha'                         => 'nullable|date',
            'nombreCompleto'                => 'nullable|string',
            'telefono'                      => 'nullable|string',
            'correoElectronico'             => 'nullable|email',
            'genero'                        => 'nullable|string',
            'empresaDondeLaboraActualmente' => 'nullable|string',
            'puesto'                        => 'nullable|string',
            'notasGenerales'                => 'nullable|string',
            'observaciones'                 => 'nullable|string',
            'interes'                       => 'nullable|string',
            'status'                        => 'nullable|string',
            'nota1'                         => 'nullable|string',
            'nota2'                         => 'nullable|string',
            'nota3'                         => 'nullable|string',
            'cierre'                        => 'nullable|string',
            'departamento'                  => 'nullable|string',
            'municipio'                     => 'nullable|string',
        ]);

        foreach ($v as $field => $value) {
            $attr = match ($field) {
                'nombreCompleto'                => 'nombre_completo',
                'correoElectronico'             => 'correo_electronico',
                'empresaDondeLaboraActualmente' => 'empresa_donde_labora_actualmente',
                default                         => $field,
            };
            $prospecto->$attr = $value;
        }

        $prospecto->updated_by = $user->id;
        $prospecto->save();
        $prospecto->load('creator');

        return response()->json([
            'message' => 'Prospecto actualizado con éxito',
            'data'    => $prospecto,
        ]);
    }

    /**
     * Soft‑delete de prospecto.
     */
    public function destroy($id)
    {
        $prospecto = Prospecto::find($id);
        if (! $prospecto) {
            return response()->json(['message' => 'Prospecto no encontrado'], 404);
        }
        $prospecto->delete();

        return response()->json(['message' => 'Prospecto eliminado con éxito']);
    }

    /**
     * Actualizar solo el estado de un prospecto.
     */
    public function updateStatus(Request $request, $id)
    {
        $user = auth()->user();
        if (! $user) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        $v = $request->validate([
            'status' => 'required|string',
        ]);

        $prospecto = Prospecto::find($id);
        if (! $prospecto) {
            return response()->json(['message' => 'Prospecto no encontrado'], 404);
        }

        $prospecto->status     = $v['status'];
        $prospecto->updated_by = $user->id;
        $prospecto->save();

        return response()->json([
            'message' => 'Estado del prospecto actualizado con éxito',
            'data'    => $prospecto,
        ]);
    }

    /**
     * Reasignación masiva:
     * Actualiza `created_by` (el “asesor asignado”) en lote.
     */
    public function bulkAssign(Request $request)
    {
        $data = $request->validate([
            'prospecto_ids'   => 'required|array',
            'prospecto_ids.*' => 'integer|exists:prospectos,id',
            'created_by'      => 'required|integer|exists:users,id',
        ]);

        Prospecto::whereIn('id', $data['prospecto_ids'])
            ->update([
                'created_by' => $data['created_by'],
                'updated_by' => auth()->id(),
            ]);

        return response()->json(['message' => 'Prospectos reasignados correctamente']);
    }
}
