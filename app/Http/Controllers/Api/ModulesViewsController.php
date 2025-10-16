<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Modules;
use App\Models\ModulesViews;
use App\Models\Permisos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ModulesViewsController extends Controller
{
    /**
     * Display a listing of the module views.
     *
     * @param  int  $moduleId
     * @return \Illuminate\Http\JsonResponse
     */
    public function index($moduleId)
    {
        $module = Modules::find($moduleId);

        if (!$module) {
            return response()->json([
                'success' => false,
                'message' => 'Módulo no encontrado'
            ], 404);
        }

        $views = $module->views()
            ->orderBy('order_num')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $views
        ]);
    }

    /**
     * Store a newly created module view.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $moduleId
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, $moduleId)
    {
        $module = Modules::find($moduleId);
    
        if (!$module) {
            return response()->json([
                'success' => false,
                'message' => 'Módulo no encontrado'
            ], 404);
        }
    
        $validator = Validator::make($request->all(), [
            'menu'      => 'required|string|max:255',
            'submenu'   => 'nullable|string|max:255',
            'view_path' => 'required|string|max:255|unique:moduleviews,view_path,NULL,id,module_id,'.$moduleId,
            'status'    => 'boolean',
            'order_num' => 'required|integer',
            'icon'      => 'nullable|string|max:255'
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }
    
        DB::beginTransaction();
        try {
            $validatedData = $validator->validated();
            // Si el front-end envía "icono" en lugar de "icon", lo mapeamos
            if ($request->has('icono')) {
                $validatedData['icon'] = $request->input('icono');
            }
        
            $view = $module->views()->create($validatedData);
        
            // Incrementamos el contador de vistas en el módulo
            $module->increment('view_count');
            
            // Auto-crear permiso 'view' para esta nueva moduleview
            try {
                $permissionName = 'view:' . $view->view_path;
                
                // Verificar si ya existe
                $existingPerm = Permisos::where('name', $permissionName)->first();
                
                if (!$existingPerm) {
                    $permission = Permisos::create([
                        'moduleview_id' => $view->id,
                        'action' => 'view',
                        'name' => $permissionName,
                        'description' => 'Auto-created view permission for ' . ($view->submenu ?: $view->menu)
                    ]);
                    
                    Log::info("Auto-created view permission for moduleview", [
                        'moduleview_id' => $view->id,
                        'permission_id' => $permission->id,
                        'name' => $permissionName
                    ]);
                }
            } catch (\Exception $e) {
                // Log pero no falla la transacción - el permiso se puede crear después
                Log::warning("Failed to auto-create view permission for moduleview", [
                    'moduleview_id' => $view->id,
                    'error' => $e->getMessage()
                ]);
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'data'    => $view,
                'message' => 'Vista creada exitosamente'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to create moduleview", ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la vista: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Display the specified module view.
     *
     * @param  int  $moduleId
     * @param  int  $viewId
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($moduleId, $viewId)
    {
        $view = ModulesViews::where('module_id', $moduleId)
            ->find($viewId);

        if (!$view) {
            return response()->json([
                'success' => false,
                'message' => 'Vista no encontrada'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $view
        ]);
    }
    
    /**
     * Update the specified module view.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $moduleId
     * @param  int  $viewId
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $moduleId, $viewId)
    {
        $view = ModulesViews::where('module_id', $moduleId)
            ->find($viewId);

        if (!$view) {
            return response()->json([
                'success' => false,
                'message' => 'Vista no encontrada'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'menu'      => 'required|string|max:255',
            'submenu'   => 'nullable|string|max:255',
            'view_path' => [
                'required',
                'string',
                'max:255',
                Rule::unique('moduleviews')->ignore($viewId)->where(function ($query) use ($moduleId) {
                    return $query->where('module_id', $moduleId);
                })
            ],
            'status'    => 'boolean',
            'order_num' => 'required|integer',
            'icon'      => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }
        
        DB::beginTransaction();
        try {
            $validatedData = $validator->validated();
            if ($request->has('icono')) {
                $validatedData['icon'] = $request->input('icono');
            }
            
            $oldViewPath = $view->view_path;
            $newViewPath = $validatedData['view_path'];

            $view->update($validatedData);
            
            // Si cambió el view_path, actualizar los permisos relacionados
            if ($oldViewPath !== $newViewPath) {
                Log::info("View path changed, updating permissions", [
                    'moduleview_id' => $viewId,
                    'old_path' => $oldViewPath,
                    'new_path' => $newViewPath
                ]);
                
                // Actualizar todos los permisos de esta moduleview
                $permissions = Permisos::where('moduleview_id', $viewId)->get();
                foreach ($permissions as $permission) {
                    $newName = $permission->action . ':' . $newViewPath;
                    $permission->update(['name' => $newName]);
                    
                    Log::info("Updated permission name", [
                        'permission_id' => $permission->id,
                        'old_name' => $permission->action . ':' . $oldViewPath,
                        'new_name' => $newName
                    ]);
                }
            }
            
            DB::commit();

            return response()->json([
                'success' => true,
                'data'    => $view,
                'message' => 'Vista actualizada exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to update moduleview", [
                'moduleview_id' => $viewId,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la vista: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Remove the specified module view.
     *
     * @param  int  $moduleId
     * @param  int  $viewId
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($moduleId, $viewId)
    {
        $view = ModulesViews::where('module_id', $moduleId)
            ->find($viewId);

        if (!$view) {
            return response()->json([
                'success' => false,
                'message' => 'Vista no encontrada'
            ], 404);
        }

        $view->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vista eliminada exitosamente'
        ]);
    }
    
    /**
     * Update the order of module views.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $moduleId
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateOrder(Request $request, $moduleId)
    {
        $module = Modules::find($moduleId);

        if (!$module) {
            return response()->json([
                'success' => false,
                'message' => 'Módulo no encontrado'
            ], 404);
        }

        $request->validate([
            'order' => 'required|array',
            'order.*.id' => 'required|exists:moduleviews,id,module_id,'.$moduleId,
            'order.*.order_num' => 'required|integer'
        ]);

        foreach ($request->order as $item) {
            ModulesViews::where('id', $item['id'])
                ->where('module_id', $moduleId)
                ->update(['order_num' => $item['order_num']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Orden de vistas actualizado'
        ]);
    }
}
