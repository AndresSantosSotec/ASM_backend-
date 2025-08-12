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
        // 1) Vistas (moduleviews) asignadas al usuario
        $userViews = DB::table('userpermissions as up')
            ->join('moduleviews as mv', 'mv.id', '=', 'up.permission_id')
            ->where('up.user_id', $user->id)
            ->select('mv.view_path')
            ->pluck('mv.view_path')
            ->unique()
            ->values();

        if ($userViews->isEmpty()) {
            return [];
        }

        // 2) Acciones permitidas por el ROL del usuario en esas view_paths
        // rolepermissions → permissions (filtrando is_enabled y haciendo match por route_path)
        $roleId = $user->role_id ?? null;

        if (!$roleId) {
            // Sin rol, no hay acciones
            return $userViews->mapWithKeys(function ($vp) {
                return [$vp => $this->emptyActionSet()];
            })->toArray();
        }

        $rows = DB::table('rolepermissions as rp')
            ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('rp.role_id', $roleId)
            ->where('p.is_enabled', true)
            ->whereIn('p.route_path', $userViews)
            ->select('p.route_path', 'p.action')
            ->get();

        // 3) Consolidar por view_path → {view,create,edit,delete,export}
        $result = [];
        foreach ($userViews as $vp) {
            $result[$vp] = $this->emptyActionSet();
        }

        foreach ($rows as $r) {
            if (isset($result[$r->route_path]) && isset($result[$r->action])) {
                $result[$r->route_path][$r->action] = true;
            }
        }

        // Reglas de negocio opcionales:
        // - Si no tiene "view", bloquea todo en esa vista
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
