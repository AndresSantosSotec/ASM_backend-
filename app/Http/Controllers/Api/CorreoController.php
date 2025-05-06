<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\CorreoMailable;

class CorreoController extends Controller
{
    public function enviar(Request $request)
    {
        // Validación para permitir múltiples correos en "para" y opcionalmente "bcc"
        $data = $request->validate([
            'para'    => 'required|string', // Cadena de correos separados por coma
            'asunto'  => 'required|string',
            'mensaje' => 'required|string',
            'bcc'     => 'nullable|string' // Cadena de correos separados por coma opcional
        ]);

        // Convertir la cadena de destinatarios en un arreglo
        $destinatarios = array_filter(array_map('trim', explode(',', $data['para'])));
        
        if (empty($destinatarios)) {
            return response()->json(['message' => 'Debe proporcionar al menos un correo válido.'], 422);
        }
        
        // Procesar BCC si existe
        $data['bcc'] = isset($data['bcc'])
            ? array_filter(array_map('trim', explode(',', $data['bcc'])))
            : [];

        // Enviar correo a los destinatarios (se aplicarán BCC a través del mailable)
        Mail::to($destinatarios)->send(new CorreoMailable($data));

        return response()->json(['message' => 'Correo enviado exitosamente.'], 200);
    }
}