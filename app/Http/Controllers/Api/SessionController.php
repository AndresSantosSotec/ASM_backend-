<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Session;
use Carbon\Carbon;

class SessionController extends Controller
{
    /**
     * Lista las sesiones del usuario autenticado.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        // Obtiene todas las sesiones del usuario autenticado
        $sessions = $user->sessions()->get();

        // Transforma los datos para adaptarlos a la estructura del frontend.
        $data = $sessions->map(function ($session) use ($user) {
            return [
                'id'          => $session->id,
                'usuario'     => $user->full_name, // Accesor que retorna el nombre completo
                'email'       => $user->email,
                'rol'         => $user->rol,       // Accesor que retorna el nombre del rol
                'ip'          => $session->ip_address,
                'inicio'      => $session->created_at, // Fecha/hora de inicio de la sesión
                'duracion'    => $session->duration,   // Puedes calcular o almacenar la duración
                'dispositivo' => $session->device_type,  // Ejemplo: 'Windows', 'MacOS', etc.
                'tipo'        => $this->getSessionType($session), // 'Desktop', 'Mobile' o 'Tablet'
                'activa'      => $session->is_active,
            ];
        });

        return response()->json(['sessions' => $data], 200);
    }

    /**
     * Cierra (desactiva) una sesión en particular.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function closeSession(Request $request, $id)
    {
        $user = $request->user();
        $session = Session::find($id);
        if (!$session || $session->user_id != $user->id) {
            return response()->json(['error' => 'Sesión no encontrada o acceso no autorizado'], 404);
        }

        $session->is_active = false;
        $session->last_activity = Carbon::now();
        $session->save();

        return response()->json(['message' => 'Sesión cerrada correctamente'], 200);
    }

    /**
     * Cierra (desactiva) todas las sesiones del usuario autenticado.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function closeAllSessions(Request $request)
    {
        $user = $request->user();
        $affected = Session::where('user_id', $user->id)
                           ->update(['is_active' => false, 'last_activity' => Carbon::now()]);

        return response()->json(['message' => 'Todas las sesiones cerradas', 'affected' => $affected], 200);
    }

    /**
     * Determina el tipo de sesión basado en el dispositivo.
     *
     * @param Session $session
     * @return string
     */
    private function getSessionType(Session $session)
    {
        if ($session->device_type) {
            return ucfirst($session->device_type);
        }
        return 'Desktop';
    }
}
