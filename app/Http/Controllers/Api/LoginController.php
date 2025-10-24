<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Session;
use App\Models\UserPermisos;
use App\Models\ModulesViews;
use Carbon\Carbon;

class LoginController extends Controller
{
    /**
     * Autenticación del usuario.
     */
    public function login(Request $request)
    {
        // Validar los campos de entrada
        $validatedData = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        // Buscar al usuario por correo electrónico
        $user = User::where('email', $validatedData['email'])->first();

        // Verificar que exista y que la contraseña sea correcta
        if (!$user || !Hash::check($validatedData['password'], $user->password_hash)) {
            return response()->json(['error' => 'Credenciales inválidas'], 401);
        }

        // Verificar si el usuario está activo
        if (!$user->is_active) {
            return response()->json(['error' => 'Esta cuenta está inactiva.'], 403);
        }

        // Actualizar la última conexión del usuario
        $user->update(['last_login' => Carbon::now()]);

        // Registrar la sesión en la tabla 'sessions'
        $session = Session::create([
            'user_id'       => $user->id,
            'token_hash'    => hash('sha256', Str::random(40)),
            'ip_address'    => $request->ip(),
            'user_agent'    => $request->userAgent(),
            'created_at'    => Carbon::now(),
            'last_activity' => Carbon::now(),
            'is_active'     => true,
        ]);

        // Generar token de acceso usando Laravel Sanctum
        $token = $user->createToken('authToken')->plainTextToken;

        // Obtener permisos asignados al usuario (ahora usa moduleView)
        $permissions = UserPermisos::with('moduleView')
            ->where('user_id', $user->id)
            ->get();

        // Obtener únicamente las vistas de módulos que el usuario tiene asignadas.
        $allowedViews = ModulesViews::whereIn('id', function($query) use ($user) {
                $query->select('moduleview_id')
                      ->from('userpermissions')
                      ->where('user_id', $user->id);
            })
            ->with('module')
            ->where('status', 1)
            ->orderBy('order_num')
            ->get();

        // Retornar la respuesta con toda la información necesaria
        return response()->json([
            'user_id'     => $user->id,
            'name'        => $user->name,
            'token'        => $token,
            'user'         => $user,
            'permissions'  => $permissions,
            'session'      => [
                'id'            => $session->id,
                'token_hash'    => $session->token_hash,
                'ip_address'    => $session->ip_address,
                'user_agent'    => $session->user_agent,
                'created_at'    => $session->created_at,
                'last_activity' => $session->last_activity,
            ],
            'allowedViews' => $allowedViews,
            'success'      => true,
            'message'      => 'Autenticación exitosa',
        ]);
    }

    /**
     * Cierra la sesión del usuario (logout).
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user) {
            $user->currentAccessToken()->delete();
            Session::where('user_id', $user->id)
                ->update(['is_active' => false]);

            return response()->json(['message' => 'Sesión cerrada correctamente'], 200);
        }

        return response()->json(['error' => 'Usuario no autenticado'], 401);
    }
}
