<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class EffectivePermissionsService
{
    /**
     * Retorna un mapa:
     * [
     *   "/finanzas/conciliacion" => ["view"=>true,"create"=>true,"edit"=>false,"delete"=>false,"export"=>true],
     *   ...
     * ]
     */
    public function forUser(User $user): array
    {
        // 1) Obtener los moduleview_ids asignados al usuario desde userpermissions
        // IMPORTANTE: Ahora usa la tabla 'permisos' (user permissions) en lugar de 'permissions' (role permissions)
        $userPermissions = DB::table('userpermissions as up')
            ->join('permisos as p', 'p.id', '=', 'up.permission_id')
            ->where('up.user_id', $user->id)
            ->where('p.action', 'view')
            ->whereNotNull('p.moduleview_id')
            ->pluck('p.moduleview_id')
            ->unique()
            ->values();

        if ($userPermissions->isEmpty()) {
            return [];
        }

        // 2) Obtener las view_paths de esos moduleviews
        $userViews = DB::table('moduleviews')
            ->whereIn('id', $userPermissions)
            ->pluck('view_path', 'id');

        if ($userViews->isEmpty()) {
            return [];
        }

        // 3) Acciones permitidas por el ROL del usuario en esas moduleviews
        $roleId = $user->role_id ?? null;

        if (!$roleId) {
            // Sin rol, solo retorna las vistas sin acciones
            return $userViews->mapWithKeys(function ($viewPath) {
                return [$viewPath => $this->emptyActionSet()];
            })->toArray();
        }

        // 4) Obtener permisos del rol para esos moduleviews
        // IMPORTANTE: Aquí sí usa 'permissions' porque son permisos de ROL
        $rows = DB::table('rolepermissions as rp')
            ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
            ->join('moduleviews as mv', 'mv.id', '=', 'p.moduleview_id')
            ->where('rp.role_id', $roleId)
            ->whereIn('p.moduleview_id', $userPermissions->toArray())
            ->select('mv.view_path', 'p.action')
            ->get();

        // 5) Consolidar por view_path → {view,create,edit,delete,export}
        $result = [];
        foreach ($userViews as $moduleviewId => $viewPath) {
            $result[$viewPath] = $this->emptyActionSet();
        }

        foreach ($rows as $r) {
            if (isset($result[$r->view_path])) {
                $action = $r->action;
                if (isset($result[$r->view_path][$action])) {
                    $result[$r->view_path][$action] = true;
                }
            }
        }

        // 6) Reglas de negocio: Si no tiene "view", bloquea todo en esa vista
        foreach ($result as $vp => $set) {
            if (!$set['view']) {
                $result[$vp] = $this->emptyActionSet(); // sin ver, no haces nada
            }
        }

        return $result;
    }

    private function emptyActionSet(): array
    {
        return [
            'view'   => false,
            'create' => false,
            'edit'   => false,
            'delete' => false,
            'export' => false,
        ];
    }
}
