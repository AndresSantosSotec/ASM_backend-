<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Role;

class RolController extends Controller
{
    /**
     * Mostrar todos los roles.
     */
    public function index()
    {
        $roles = Role::all();
        return response()->json($roles);
    }

    /**
     * Mostrar un rol específico.
     */
    public function show($id)
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json(['error' => 'Rol no encontrado'], 404);
        }

        return response()->json($role);
    }

    /**
     * Crear un nuevo rol.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_system'   => 'required|boolean',
            'user_count'  => 'nullable|integer',
            // se cambia de "required" a "nullable"
            'type'        => 'nullable|string|max:100',
        ]);

        $role = Role::create($validatedData);

        return response()->json($role, 201);
    }

    /**
     * Actualizar un rol existente.
     */
    public function update(Request $request, $id)
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json(['error' => 'Rol no encontrado'], 404);
        }

        $validatedData = $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'is_system'   => 'sometimes|required|boolean',
            'user_count'  => 'nullable|integer',
            // se cambia de "required" a "nullable"
            'type'        => 'sometimes|nullable|string|max:100',
        ]);

        $role->update($validatedData);

        return response()->json($role);
    }

    /**
     * Eliminar un rol.
     */
    public function destroy($id)
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json(['error' => 'Rol no encontrado'], 404);
        }

        $role->delete();

        return response()->json(['message' => 'Rol eliminado correctamente']);
    }
}
