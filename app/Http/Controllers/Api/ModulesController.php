<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Modules;
use Illuminate\Http\Request;

class ModulesController extends Controller
{
    public function index()
    {
        $modules = Modules::all();
        return response()->json($modules);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'required|boolean',
            'view_count'  => 'nullable|integer',
            'icon'        => 'nullable|string|max:255', // valida si se envía con este nombre
            'order_num'   => 'nullable|integer',
        ]);

        // Si el front-end envía el campo "icono", se mapea a "icon"
        if ($request->has('icono')) {
            $validated['icon'] = $request->input('icono');
        }

        $module = Modules::create($validated);
        return response()->json($module, 201);
    }

    public function show($id)
    {
        $module = Modules::find($id);

        if (!$module) {
            return response()->json(['message' => 'Módulo no encontrado'], 404);
        }

        return response()->json($module);
    }

    public function update(Request $request, $id)
    {
        $module = Modules::find($id);

        if (!$module) {
            return response()->json(['message' => 'Módulo no encontrado'], 404);
        }

        $validated = $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'sometimes|required|boolean',
            'view_count'  => 'nullable|integer',
            'icon'        => 'nullable|string|max:255',
            'order_num'   => 'nullable|integer',
        ]);

        if ($request->has('icono')) {
            $validated['icon'] = $request->input('icono');
        }

        $module->update($validated);

        return response()->json($module);
    }

    public function destroy($id)
    {
        $module = Modules::find($id);
    
        if (!$module) {
            return response()->json(['message' => 'Módulo no encontrado'], 404);
        }
    
        // Eliminar primero las vistas asociadas
        $module->views()->delete();
        
        // Luego eliminar el módulo
        $module->delete();
    
        return response()->json(['message' => 'Módulo eliminado correctamente']);
    }
}
