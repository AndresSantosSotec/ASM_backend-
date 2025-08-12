<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserPermisos;
use App\Models\ModulesViews;
use App\Models\Permisos;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class UserPermisosController extends Controller
{
    /**
     * Lista los permisos asignados a un usuario.
     */
    public function index(Request $request)
    {
        $user_id = $request->query('user_id');
        if (!$user_id) {
            return response()->json([
                'success' => false,
                'message' => 'El parámetro user_id es requerido.'
            ], 400);
        }

        $permissions = UserPermisos::with('permission.moduleView.module')
            ->where('user_id', $user_id)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Permisos cargados correctamente.',
            'data' => $permissions
        ], 200);
    }

    /**
     * Asigna o actualiza los permisos de un usuario.
     * Espera:
     * {
     *   "user_id": 123,
     *   "permissions": [ <moduleview_id>, <moduleview_id>, ... ]
     * }
     */
    public function store(Request $request)
    {
        // Loguea el payload crudo para depurar 422
        Log::info('UserPermisos.store payload', ['payload' => $request->all()]);

        // Asegura que "permissions" sea un array plano de enteros
        $incoming = $request->all();
        $permissions = $incoming['permissions'] ?? [];
        if (is_array($permissions)) {
            $permissions = array_values(array_filter(array_map(function ($v) {
                // Soporta objetos {id: X}, strings "X"
                if (is_array($v) && isset($v['id'])) return (int)$v['id'];
                return is_numeric($v) ? (int)$v : null;
            }, $permissions), function ($v) {
                return !is_null($v);
            }));
        } else {
            $permissions = [];
        }
        $request->merge([
            'permissions' => $permissions
        ]);

        // Valida
        $validator = Validator::make($request->all(), [
            'user_id'       => 'required|exists:users,id',
            'permissions'   => 'required|array',
            'permissions.*' => 'exists:moduleviews,id'
        ]);

        if ($validator->fails()) {
            Log::warning('UserPermisos.store validation failed', [
                'errors' => $validator->errors()->toArray()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors'  => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $userId = (int) $request->input('user_id');
            $moduleviewIds = $request->input('permissions', []);

            // Limpia previos
            UserPermisos::where('user_id', $userId)->delete();

            // Mapea cada moduleview_id → permission.id (action='view')
            $now = now();
            $rows = [];

            foreach ($moduleviewIds as $mvId) {
                $mv = ModulesViews::find($mvId);
                if (!$mv) {
                    // Safety: debería estar cubierto por la validación exists
                    continue;
                }

                $permId = Permisos::query()
                    ->where('route_path', $mv->view_path)
                    ->where('action', 'view') // aquí defines qué action representa “acceso a la vista”
                    ->where('is_enabled', true)
                    ->value('id');

                if (!$permId) {
                    // Si no existe el permiso 'view' para esa vista, puedes:
                    // (a) saltarlo, (b) crearlo, o (c) disparar 422
                    Log::warning('No existe permiso view para la vista', [
                        'moduleview_id' => $mvId,
                        'view_path' => $mv->view_path
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => "No existe permiso 'view' para la vista seleccionada.",
                        'errors'  => ['permissions' => ["moduleview_id {$mvId} no tiene permiso 'view' configurado en permissions."]]
                    ], 422);
                }

                $rows[] = [
                    'user_id'       => $userId,
                    'permission_id' => $permId,  // <<<<< guarda permissions.id
                    'assigned_at'   => $now,
                    'scope'         => 'self'
                ];
            }

            if (!empty($rows)) {
                UserPermisos::insert($rows);
            }

            DB::commit();

            // Eager load correcto
            $updated = UserPermisos::with('permission.moduleView.module')
                ->where('user_id', $userId)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Permisos actualizados correctamente.',
                'data'    => $updated
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('UserPermisos.store exception', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al asignar los permisos.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'scope' => 'nullable|string'
        ]);

        $userPermiso = UserPermisos::findOrFail($id);
        $userPermiso->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Permiso actualizado correctamente.',
            'data'    => $userPermiso
        ], 200);
    }

    public function destroy($id)
    {
        $userPermiso = UserPermisos::findOrFail($id);
        $userPermiso->delete();

        return response()->json([
            'success' => true,
            'message' => 'Permiso eliminado correctamente.'
        ], 200);
    }
}
