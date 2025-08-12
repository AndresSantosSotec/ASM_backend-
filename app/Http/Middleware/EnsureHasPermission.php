<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\PermissionService;

class EnsureHasPermission
{
    public function __construct(private PermissionService $service)
    {
    }

    public function handle(Request $request, Closure $next, string $action, ?string $viewPath = null)
    {
        $viewPath = $viewPath ?? '/' . ltrim($request->path(), '/');
        $user = Auth::user();

        if (!$user || !$this->service->canDo($user, $action, $viewPath)) {
            abort(403);
        }

        return $next($request);
    }
}
