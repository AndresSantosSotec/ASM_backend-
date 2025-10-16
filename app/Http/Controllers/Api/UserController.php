<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserPermisos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
//use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromCollection;
use App\Exports\UserExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Prospecto; // ← AGREGAR IMPORT
use Illuminate\Support\Facades\DB; // ← AGREGAR IMPORT
use App\Services\RolePermissionService; // ← AGREGAR IMPORT
use Illuminate\Support\Facades\Log; // ← AGREGAR IMPORT


class UserController extends Controller
{
    protected $rolePermissionService;

    public function __construct(RolePermissionService $rolePermissionService)
    {
        $this->rolePermissionService = $rolePermissionService;
    }
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

        try {
            DB::beginTransaction();

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

            // Recargar el usuario con su relación de rol para asignación de permisos
            $user->load('userRole');

            // Asignar permisos automáticamente según el rol
            $permissionAssigned = $this->rolePermissionService->assignPermissionsToUser($user);
            
            if (!$permissionAssigned) {
                // Log error but don't fail the user creation
                Log::error("Failed to assign permissions to user {$user->id} with role {$validatedData['rol']}");
                
                // Still commit the transaction as user and role were created successfully
                DB::commit();
                
                return response()->json([
                    'user' => $user,
                    'permissions_assigned' => false,
                    'message' => 'Usuario creado exitosamente pero falló la asignación de permisos',
                    'warning' => 'Los permisos deben ser asignados manualmente'
                ], 201);
            }

            DB::commit();

            return response()->json([
                'user' => $user,
                'permissions_assigned' => $permissionAssigned,
                'message' => 'Usuario creado exitosamente' . ($permissionAssigned ? ' con permisos asignados' : ' pero falló la asignación de permisos')
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Failed to create user: " . $e->getMessage());
            
            return response()->json([
                'error' => 'Error al crear el usuario',
                'message' => $e->getMessage()
            ], 500);
        }
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

        try {
            DB::beginTransaction();

            if (isset($validatedData['password'])) {
                $validatedData['password_hash'] = Hash::make($validatedData['password']);
                unset($validatedData['password']);
            }

            // Actualizar datos básicos del usuario
            $user->update($validatedData);

            $roleChanged = false;
            $oldRoleId = null;
            $newRoleId = null;

            // Si se envía el campo 'rol', se actualiza la asignación y se ajusta el contador
            if (isset($validatedData['rol'])) {
                $newRoleId = $validatedData['rol'];
                $currentUserRole = $user->userRole;

                if ($currentUserRole) {
                    $oldRoleId = $currentUserRole->role_id;
                    
                    if ($currentUserRole->role_id != $newRoleId) {
                        $roleChanged = true;
                        
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
                    $roleChanged = true;
                    
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

            // Si el rol cambió, actualizar permisos automáticamente
            $permissionsUpdated = false;
            if ($roleChanged) {
                // Recargar el usuario con su nueva relación de rol
                $user->load('userRole');
                
                $permissionsUpdated = $this->rolePermissionService->updateUserPermissionsOnRoleChange(
                    $user, 
                    $oldRoleId ?? 0, 
                    $newRoleId
                );
                
                if (!$permissionsUpdated) {
                    Log::warning("Failed to update permissions for user {$user->id} after role change from {$oldRoleId} to {$newRoleId}");
                }
            }

            DB::commit();

            return response()->json([
                'user' => $user,
                'role_changed' => $roleChanged,
                'permissions_updated' => $permissionsUpdated,
                'message' => 'Usuario actualizado exitosamente' . 
                           ($roleChanged ? ' con cambio de rol' : '') . 
                           ($permissionsUpdated ? ' y permisos actualizados' : '')
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Failed to update user {$id}: " . $e->getMessage());
            
            return response()->json([
                'error' => 'Error al actualizar el usuario',
                'message' => $e->getMessage()
            ], 500);
        }
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

    /**
     * Get user's effective permissions (from both role and direct assignments)
     */
    public function getPermissions(Request $request, $id)
    {
        $user = User::with(['userRole.role', 'roles'])->findOrFail($id);
        
        // Get direct user permissions
        $directPermissions = UserPermisos::with('permission.moduleView')
            ->where('user_id', $id)
            ->get()
            ->map(function ($up) {
                return [
                    'id' => $up->permission_id,
                    'name' => $up->permission->name ?? '',
                    'action' => $up->permission->action ?? '',
                    'moduleview_id' => $up->permission->moduleview_id ?? null,
                    'view_path' => $up->permission->moduleView->view_path ?? '',
                    'menu' => $up->permission->moduleView->menu ?? '',
                    'submenu' => $up->permission->moduleView->submenu ?? '',
                    'source' => 'direct',
                    'scope' => $up->scope,
                ];
            });
        
        // Get role-based permissions
        $rolePermissions = collect();
        foreach ($user->roles as $role) {
            $perms = $role->permissions()->with('moduleView')->get()->map(function ($p) use ($role) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'action' => $p->action,
                    'moduleview_id' => $p->moduleview_id,
                    'view_path' => $p->moduleView->view_path ?? '',
                    'menu' => $p->moduleView->menu ?? '',
                    'submenu' => $p->moduleView->submenu ?? '',
                    'source' => 'role',
                    'role_name' => $role->name,
                ];
            });
            $rolePermissions = $rolePermissions->merge($perms);
        }
        
        // Combine and deduplicate
        $allPermissions = $directPermissions->merge($rolePermissions)
            ->unique('id')
            ->values();
        
        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->userRole && $user->userRole->role ? $user->userRole->role->name : ''
            ],
            'permissions' => $allPermissions,
            'total' => $allPermissions->count()
        ]);
    }

    /**
     * Manually assign permissions to a user (for debugging/fixing issues)
     */
    public function assignPermissions(Request $request, $userId)
    {
        try {
            $user = User::with('userRole.role')->find($userId);
            
            if (!$user) {
                return response()->json(['error' => 'Usuario no encontrado'], 404);
            }

            if (!$user->userRole) {
                return response()->json(['error' => 'Usuario no tiene rol asignado'], 400);
            }

            $permissionAssigned = $this->rolePermissionService->assignPermissionsToUser($user);
            
            return response()->json([
                'user_id' => $userId,
                'role_id' => $user->userRole->role_id,
                'role_name' => $user->userRole->role ? $user->userRole->role->name : 'Unknown',
                'permissions_assigned' => $permissionAssigned,
                'message' => $permissionAssigned 
                    ? 'Permisos asignados correctamente' 
                    : 'Error al asignar permisos'
            ]);

        } catch (\Exception $e) {
            Log::error("Error manually assigning permissions to user {$userId}: " . $e->getMessage());
            
            return response()->json([
                'error' => 'Error al asignar permisos',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
