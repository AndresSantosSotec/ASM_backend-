<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Obtener todos los usuarios.
     */
    public function index()
    {
        $users = User::all(); // Obtiene todos los usuarios
        return response()->json($users);
    }

    /**
     * Ver un usuario específico.
     */
    public function show($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        return response()->json($user);
    }

    /**
     * Crear un nuevo usuario.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'username' => 'required|string|max:50|unique:users',
            'email' => 'required|string|email|max:100|unique:users',
            'password' => 'required|string|min:8',
            'first_name' => 'nullable|string|max:50',
            'last_name' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'email_verified' => 'boolean',
            'mfa_enabled' => 'boolean',
        ]);

        $validatedData['password_hash'] = Hash::make($validatedData['password']); // Encripta la contraseña
        unset($validatedData['password']); // Elimina el campo `password` del array

        $user = User::create($validatedData);

        return response()->json($user, 201); // Devuelve el usuario creado con código 201
    }

    /**
     * Actualizar un usuario existente.
     */
    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        $validatedData = $request->validate([
            'username' => 'sometimes|string|max:50|unique:users,username,' . $id,
            'email' => 'sometimes|string|email|max:100|unique:users,email,' . $id,
            'password' => 'nullable|string|min:8',
            'first_name' => 'nullable|string|max:50',
            'last_name' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'email_verified' => 'boolean',
            'mfa_enabled' => 'boolean',
        ]);

        if (isset($validatedData['password'])) {
            $validatedData['password_hash'] = Hash::make($validatedData['password']); // Encripta la contraseña
            unset($validatedData['password']); // Elimina el campo `password` del array
        }

        $user->update($validatedData);

        return response()->json($user);
    }

    /**
     * Eliminar un usuario (Soft Delete).
     */
    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        $user->delete(); // Realiza un soft delete
        return response()->json(['message' => 'Usuario eliminado correctamente']);
    }

    /**
     * Restaurar un usuario eliminado (Soft Delete).
     */
    public function restore($id)
    {
        $user = User::withTrashed()->find($id);

        if (!$user) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        $user->restore(); // Restaura el usuario eliminado
        return response()->json(['message' => 'Usuario restaurado correctamente']);
    }
}