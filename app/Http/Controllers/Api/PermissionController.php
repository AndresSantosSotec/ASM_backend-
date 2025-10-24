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

        // Unicidad lógica: nombre único action:view_path
        $permissionName = $validated['action'] . ':' . $mv->view_path;
        $exists = Permisos::where('name', $permissionName)->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Permission already exists for this view and action',
            ], 409);
        }

        $permission = Permisos::create([
            'moduleview_id' => $validated['moduleview_id'],
            'action'        => $validated['action'],
            'name'          => $validated['action'] . ':' . $mv->view_path,
            'description'   => $validated['description'] ?? null,
        ]);

        return response()->json($permission, 201);
    }
}
