<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Permission;
use App\Models\Moduleview;

class PermissionController extends Controller
{
    /**
     * Create a new permission tied to a module view.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'moduleview_id' => 'required|exists:moduleviews,id',
            'action' => 'required|in:view,create,edit,delete,export',
            'description' => 'nullable|string',
        ]);

        $moduleview = Moduleview::findOrFail($validated['moduleview_id']);
        $name = $validated['action'].':'.$moduleview->view_path;

        if (Permission::where('name', $name)->exists()) {
            return response()->json([
                'message' => 'Permission already exists',
            ], 409);
        }

        $permission = Permission::create([
            'action' => $validated['action'],
            'moduleview_id' => $moduleview->id,
            'name' => $name,
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json($permission, 201);
    }
}
