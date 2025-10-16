<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserPermisos;
use App\Models\Permisos;
use App\Models\ModulesViews;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class UserPermisosController extends Controller
{
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
     * Espera:
     * {
     *   "user_id": 123,
     *   "permissions": [ <moduleview_id>, <moduleview_id>, ... ]
     * }
     */
    public function store(Request $request)
    {
        Log::info('UserPermisos.store payload', ['payload' => $request->all()]);

        // Normaliza "permissions" a un array plano de enteros únicos
        $incoming = $request->all();
        $permissions = $incoming['permissions'] ?? [];
        if (is_array($permissions)) {
            $permissions = array_values(array_filter(array_map(function ($v) {
                if (is_array($v) && isset($v['id'])) return (int) $v['id'];
                return is_numeric($v) ? (int) $v : null;
            }, $permissions), fn($v) => !is_null($v)));
            $permissions = array_values(array_unique($permissions));
        } else {
            $permissions = [];
        }
        $request->merge(['permissions' => $permissions]);

        // Valida entrada
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

        $userId = (int) $request->input('user_id');
        $moduleviewIds = $request->input('permissions', []);

        // Mapea TODOS los moduleview_id -> permission_id
        // Busca permisos directamente por moduleview_id en lugar de JOIN
        $permMap = DB::table('permissions')
            ->whereIn('moduleview_id', $moduleviewIds)
            ->where('action', '=', 'view')
            ->pluck('id', 'moduleview_id')
            ->toArray();

        // Verificar si faltan permisos 'view' para algunos moduleviews
        $missingMvIds = array_values(array_diff($moduleviewIds, array_keys($permMap)));
        
        // Si faltan permisos, intentar crearlos automáticamente
        if (!empty($missingMvIds)) {
            Log::info('UserPermisos.store creating missing view permissions', [
                'missing_moduleview_ids' => $missingMvIds
            ]);
            
            $createdPerms = [];
            foreach ($missingMvIds as $mvId) {
                $moduleView = ModulesViews::find($mvId);
                if ($moduleView) {
                    try {
                        $permName = 'view:' . $moduleView->view_path;
                        
                        // Verificar si ya existe un permiso con este nombre
                        $existingPerm = Permisos::where('name', $permName)->first();
                        
                        if (!$existingPerm) {
                            $perm = Permisos::create([
                                'moduleview_id' => $mvId,
                                'action' => 'view',
                                'name' => $permName,
                                'description' => 'Auto-created view permission for ' . $moduleView->submenu
                            ]);
                            $permMap[$mvId] = $perm->id;
                            $createdPerms[] = $mvId;
                        } else {
                            $permMap[$mvId] = $existingPerm->id;
                            $createdPerms[] = $mvId;
                        }
                    } catch (\Exception $e) {
                        Log::error('UserPermisos.store failed to create permission', [
                            'moduleview_id' => $mvId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
            
            // Re-verificar permisos faltantes después del intento de creación
            $missingMvIds = array_values(array_diff($missingMvIds, $createdPerms));
            
            if (!empty($missingMvIds)) {
                Log::warning('UserPermisos.store still missing view permissions after auto-creation', [
                    'missing_moduleview_ids' => $missingMvIds
                ]);
                return response()->json([
                    'success' => false,
                    'message' => "No se pudieron crear permisos 'view' para algunas vistas seleccionadas.",
                    'errors'  => [
                        'permissions' => array_map(
                            fn($id) => "moduleview_id {$id} no tiene permiso 'view' y no se pudo crear automáticamente.",
                            $missingMvIds
                        )
                    ]
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            // Bloquea filas actuales del usuario para evitar carreras
            DB::table((new UserPermisos)->getTable())
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->get();

            // Limpia previos e inserta nuevos
            DB::table((new UserPermisos)->getTable())->where('user_id', $userId)->delete();

            $now = now();
            $rows = [];
            foreach ($moduleviewIds as $mvId) {
                $rows[] = [
                    'user_id'       => $userId,
                    'permission_id' => $permMap[$mvId],
                    'assigned_at'   => $now,
                    'scope'         => 'self'
                ];
            }
            if (!empty($rows)) {
                DB::table((new UserPermisos)->getTable())->insert($rows);
            }

            DB::commit();

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

        $userPermiso = \App\Models\UserPermisos::findOrFail($id);
        $userPermiso->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Permiso actualizado correctamente.',
            'data'    => $userPermiso
        ], 200);
    }

    public function destroy($id)
    {
        $userPermiso = \App\Models\UserPermisos::findOrFail($id);
        $userPermiso->delete();

        return response()->json([
            'success' => true,
            'message' => 'Permiso eliminado correctamente.'
        ], 200);
    }
}
