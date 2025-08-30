<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
//use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromCollection;
use App\Exports\UserExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Prospecto; // ← AGREGAR IMPORT
use Illuminate\Support\Facades\DB; // ← AGREGAR IMPORT


class UserController extends Controller
{
    /**
     * Obtener todos los usuarios con su rol asignado.
     */
    public function index()
    {
        // Cargar la relación userRole.role (además de que en el futuro podrías incluir 'sessions' o 'auditLogs' si lo requieres)
        $users = User::with('userRole.role')->get();

        // Agregar un atributo "rol" a cada usuario usando setAttribute
        $users->transform(function ($user) {
            $user->setAttribute('rol', $user->userRole && $user->userRole->role
                ? $user->userRole->role->name
                : "");
            return $user;
        });

        return response()->json($users);
    }

    public function export()
    {
        return Excel::download(new UserExport, 'usuarios.xlsx');
    }

    /**
     * Ver un usuario específico con su rol asignado.
     */
    public function show($id)
    {
        $user = User::with('userRole.role')->find($id);

        if (!$user) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        // Agregar el atributo "rol" al usuario
        $user->setAttribute('rol', $user->userRole && $user->userRole->role
            ? $user->userRole->role->name
            : "");

        return response()->json($user);
    }

    /**
     * Crear un nuevo usuario.
     */
    public function store(Request $request)
    {
        // Validar los datos de entrada, incluyendo el id del rol
        $validatedData = $request->validate([
            'username'       => 'required|string|max:50|unique:users',
            'email'          => 'required|string|email|max:100|unique:users',
            'password'       => 'required|string|min:8',
            'first_name'     => 'nullable|string|max:50',
            'last_name'      => 'nullable|string|max:50',
            'carnet'         => 'nullable|string|max:20', // 🔥 AGREGADO
            'is_active'      => 'boolean',
            'email_verified' => 'boolean',
            'mfa_enabled'    => 'boolean',
            'rol'            => 'required|integer|exists:roles,id', // Validación del rol
        ]);

        if (!isset($validatedData['is_active'])) {
            $validatedData['is_active'] = true;
        }

        // Encriptar la contraseña y quitar el campo original
        $validatedData['password_hash'] = Hash::make($validatedData['password']);
        unset($validatedData['password']);

        // Crear el usuario
        $user = User::create($validatedData);

        // Insertar la asignación en la tabla userroles
        \App\Models\UserRole::create([
            'user_id'     => $user->id,
            'role_id'     => $validatedData['rol'],
            'assigned_at' => now(),
            'expires_at'  => null,
        ]);

        // Actualizar el contador de usuarios en la tabla roles
        $role = \App\Models\Role::find($validatedData['rol']);
        if ($role) {
            $role->increment('user_count');
        }

        return response()->json($user, 201);
    }

    /**
     * Actualizar un usuario existente.
     */
    public function update(Request $request, $id)
    {
        $user = User::with('userRole')->find($id);

        if (!$user) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        // Permitir actualizar el rol también
        $validatedData = $request->validate([
            'username'       => 'sometimes|string|max:50|unique:users,username,' . $id,
            'email'          => 'sometimes|string|email|max:100|unique:users,email,' . $id,
            'password'       => 'nullable|string|min:8',
            'first_name'     => 'nullable|string|max:50',
            'last_name'      => 'nullable|string|max:50',
            'carnet'         => 'nullable|string|max:20', // 🔥 AGREGADO
            'is_active'      => 'boolean',
            'email_verified' => 'boolean',
            'mfa_enabled'    => 'boolean',
            'rol'            => 'sometimes|integer|exists:roles,id',
        ]);

        if (isset($validatedData['password'])) {
            $validatedData['password_hash'] = Hash::make($validatedData['password']);
            unset($validatedData['password']);
        }

        // Actualizar datos básicos del usuario
        $user->update($validatedData);

        // Si se envía el campo 'rol', se actualiza la asignación y se ajusta el contador
        if (isset($validatedData['rol'])) {
            $newRoleId = $validatedData['rol'];
            $currentUserRole = $user->userRole;

            if ($currentUserRole) {
                if ($currentUserRole->role_id != $newRoleId) {
                    // Decrementar el contador del rol anterior
                    $oldRole = \App\Models\Role::find($currentUserRole->role_id);
                    if ($oldRole) {
                        $oldRole->decrement('user_count');
                    }
                    // Actualizar la asignación con el nuevo rol
                    $currentUserRole->update(['role_id' => $newRoleId]);
                    // Incrementar el contador del nuevo rol
                    $newRole = \App\Models\Role::find($newRoleId);
                    if ($newRole) {
                        $newRole->increment('user_count');
                    }
                }
            } else {
                // Si no existe asignación, crearla
                \App\Models\UserRole::create([
                    'user_id'     => $user->id,
                    'role_id'     => $newRoleId,
                    'assigned_at' => now(),
                    'expires_at'  => null,
                ]);
                // Incrementar el contador del rol
                $role = \App\Models\Role::find($newRoleId);
                if ($role) {
                    $role->increment('user_count');
                }
            }
        }

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

        $user->delete();
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

        $user->restore();
        return response()->json(['message' => 'Usuario restaurado correctamente']);
    }

    /**
     * Función para activación y desactivación masiva de cuentas.
     */
    public function bulkUpdate(Request $request)
    {
        $validatedData = $request->validate([
            'user_ids'   => 'required|array',
            'user_ids.*' => 'integer|exists:users,id',
            'is_active'  => 'required|boolean',
        ]);

        // Actualizamos los usuarios
        $updated = User::whereIn('id', $validatedData['user_ids'])
            ->update(['is_active' => $validatedData['is_active']]);

        return response()->json([
            'message'       => 'Usuarios actualizados correctamente',
            'updated_count' => $updated,
        ]);
    }

    public function getUsersByRole(Request $request, $roleId)
    {
        // Obtener usuarios cuyo rol sea el indicado
        $users = User::with('userRole.role')
            ->whereHas('userRole', function ($query) use ($roleId) {
                $query->where('role_id', $roleId);
            })
            ->get();

        // Agregar el atributo 'rol' a cada usuario
        $users->transform(function ($user) {
            $user->setAttribute('rol', $user->userRole && $user->userRole->role
                ? $user->userRole->role->name
                : "");
            return $user;
        });

        return response()->json($users);
    }
}
