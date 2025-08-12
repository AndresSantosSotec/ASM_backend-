<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Permisos;
use App\Models\ModulesViews;

class PermissionController extends Controller
{
    /**
     * Create a new permission tied to a module view.
     * Payload:
     * {
     *   "moduleview_id": 12,
     *   "action": "view", // view|create|edit|delete|export
     *   "description": "opcional"
     * }
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'moduleview_id' => 'required|exists:moduleviews,id',
            'action'        => 'required|in:view,create,edit,delete,export',
            'description'   => 'nullable|string',
        ]);

        $mv = ModulesViews::findOrFail($validated['moduleview_id']);

        // Unicidad lógica: action + route_path
        $exists = Permisos::where('action', $validated['action'])
            ->where('route_path', $mv->view_path)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Permission already exists for this route_path and action',
            ], 409);
        }

        $permission = Permisos::create([
            'module'      => $mv->menu,
            'section'     => $mv->submenu,
            'resource'    => basename($mv->view_path),
            'action'      => $validated['action'],
            'effect'      => 'allow',
            'description' => $validated['description'] ?? null,
            'route_path'  => $mv->view_path,
            'file_name'   => null,
            'object_id'   => null,
            'is_enabled'  => true,
            'level'       => $validated['action'], // si tu CHECK lo exige
        ]);

        return response()->json($permission, 201);
    }
}
