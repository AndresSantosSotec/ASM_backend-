<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // Si la solicitud es para la API o espera una respuesta JSON,
        // devolver null para que el middleware lance un error 401 en vez de redirigir.
        return $request->expectsJson() || $request->is('api/*') ? null : route('login');
    }
    
}
