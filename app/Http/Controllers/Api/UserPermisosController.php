<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserPermisos;
use Illuminate\Support\Facades\DB;

class UserPermisosController extends Controller
{
    /**
     * Lista los permisos asignados a un usuario.
     *
     * Se espera recibir el parámetro 'user_id' por query.
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
        
        $permissions = UserPermisos::with('permission')
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
     *
     * Se espera recibir en el request:
     * - user_id: identificador del usuario.
     * - permissions: arreglo con los id de las vistas (moduleviews) a asignar.
     *
     * Se eliminan los permisos previos y se insertan los nuevos de forma masiva.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id'       => 'required|exists:users,id',
            'permissions'   => 'required|array',
            'permissions.*' => 'exists:moduleviews,id'
        ]);
    
        DB::beginTransaction();
        try {
            // Elimina todos los permisos previos para el usuario
            UserPermisos::where('user_id', $validated['user_id'])->delete();
            
            // Preparamos la inserción masiva
            $now = now();
            $data = [];
            foreach ($validated['permissions'] as $permission_id) {
                $data[] = [
                    'user_id'       => $validated['user_id'],
                    'permission_id' => $permission_id,
                    'assigned_at'   => $now,
                    'scope'         => 'self' // Valor permitido según la restricción CHECK
                ];
            }
            
            // Inserción en lote
            UserPermisos::insert($data);
            DB::commit();
            
            // Cargar los permisos asignados para actualizar la interfaz del frontend
            $updatedPermissions = UserPermisos::with('permission')
                ->where('user_id', $validated['user_id'])
                ->get();
            
            return response()->json([
                'success' => true,
                'message' => 'Permisos actualizados correctamente.',
                'data' => $updatedPermissions
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al asignar los permisos.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualiza un permiso específico del usuario (por ejemplo, para modificar el 'scope').
     *
     * @param int $id Id del registro de permiso.
     */
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

    /**
     * Elimina un permiso asignado al usuario.
     *
     * @param int $id Id del registro de permiso.
     */
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
