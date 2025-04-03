<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Modules;
use App\Models\ModulesViews;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
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
            'menu' => 'required|string|max:255',
            'submenu' => 'nullable|string|max:255',
            'view_path' => 'required|string|max:255|unique:moduleviews,view_path,NULL,id,module_id,'.$moduleId,
            'status' => 'boolean',
            'order_num' => 'required|integer'
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
    
        $view = $module->views()->create($validator->validated());
    
        // Incrementamos el contador de vistas en el módulo
        $module->increment('view_count');
    
        return response()->json([
            'success' => true,
            'data' => $view,
            'message' => 'Vista creada exitosamente'
        ], 201);
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
            'menu' => 'required|string|max:255',
            'submenu' => 'nullable|string|max:255',
            'view_path' => [
                'required',
                'string',
                'max:255',
                Rule::unique('moduleviews')->ignore($viewId)->where(function ($query) use ($moduleId) {
                    return $query->where('module_id', $moduleId);
                })
            ],
            'status' => 'boolean',
            'order_num' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $view->update($validator->validated());

        return response()->json([
            'success' => true,
            'data' => $view,
            'message' => 'Vista actualizada exitosamente'
        ]);
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