<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Session;
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
        $user->last_login = Carbon::now();
        $user->save();

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

        return response()->json([
            'token' => $token,
            'user'  => $user,
        ]);
    }

    /**
     * Cierra la sesión del usuario (logout).
     */
    public function logout(Request $request)
    {
        // Obtiene el usuario autenticado a partir del token actual
        $user = $request->user();

        if ($user) {
            // Revocar el token actual de Laravel Sanctum
            $user->currentAccessToken()->delete();

            // Marcar todas las sesiones del usuario como inactivas
            Session::where('user_id', $user->id)
                ->update(['is_active' => false]);

            return response()->json(['message' => 'Sesión cerrada correctamente'], 200);
        }

        return response()->json(['error' => 'Usuario no autenticado'], 401);
    }
}
