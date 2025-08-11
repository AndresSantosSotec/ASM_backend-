<?php

namespace App\Services;

use App\Models\User;

class PermissionService
{
    public function canDo(User $user, string $action, string $viewPath): bool
    {
        $target = $action.':'.$viewPath;
        $roles = $user->roles()->with('permissions')->get();

        foreach ($roles as $role) {
            foreach ($role->permissions as $permission) {
                if ($this->matches($permission->name, $target)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function matches(string $permissionName, string $target): bool
    {
        [$permAction, $permPath] = explode(':', $permissionName, 2);
        [$targetAction, $targetPath] = explode(':', $target, 2);
        if ($permAction !== $targetAction) {
            return false;
        }

        if (str_ends_with($permPath, '%')) {
            $prefix = rtrim($permPath, '%');
            return str_starts_with($targetPath, $prefix);
        }

        return $permPath === $targetPath;
    }
}
