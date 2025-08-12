<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Moduleview;
use Illuminate\Http\Request;

class RolePermissionController extends Controller
{
    /**
     * List permissions grouped by module for the given role.
     */
    public function index(Role $role)
    {
        $moduleviews = Moduleview::with('permissions')->orderBy('menu')->orderBy('submenu')->get();
        $assigned = $role->permissions->pluck('id')->toArray();

        $data = $moduleviews->map(function ($mv) use ($assigned) {
            $perms = [
                'view' => false,
                'create' => false,
                'edit' => false,
                'delete' => false,
                'export' => false,
            ];

            foreach ($mv->permissions as $p) {
                if (in_array($p->id, $assigned)) {
                    $perms[$p->action] = true;
                }
            }

            return [
                'moduleview_id' => $mv->id,
                'menu' => $mv->menu,
                'submenu' => $mv->submenu,
                'view_path' => $mv->view_path,
                'permissions' => $perms,
            ];
        });

        return response()->json($data);
    }

    /**
     * Update permissions for the given role.
     */
    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'permissions' => 'array',
            'permissions.*.moduleview_id' => 'required|exists:moduleviews,id',
            'permissions.*.actions' => 'array',
            'permissions.*.actions.*' => 'in:view,create,edit,delete,export',
        ]);

        $permissionIds = [];
        foreach ($validated['permissions'] ?? [] as $perm) {
            $moduleviewId = $perm['moduleview_id'];
            $actions = $perm['actions'] ?? [];

            $ids = Permission::where('moduleview_id', $moduleviewId)
                ->whereIn('action', $actions)
                ->pluck('id')
                ->toArray();

            $permissionIds = array_merge($permissionIds, $ids);
        }

        $role->permissions()->sync($permissionIds);

        return response()->json(['message' => 'Permisos actualizados']);
    }
}
