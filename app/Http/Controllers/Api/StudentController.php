<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentResource;
use App\Models\Prospecto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\SendCredentialsMail;
use App\Services\WhatsAppService;

class StudentController extends Controller
{
    public function show($id)
    {
        $prospecto = Prospecto::with([
            'programas.programa',
            'inscripciones.course',
            'gpaHist',
            'achievements'
        ])->findOrFail($id);

        return new StudentResource($prospecto);
    }

    public function sendCredentials(Request $request, $id)
    {
        $data = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $student = Prospecto::findOrFail($id);

        Mail::to($student->correo_electronico)
            ->send(new SendCredentialsMail($student, $data));

        try {
            app(WhatsAppService::class)->sendMessage(
                $student->telefono,
                "Usuario: {$data['username']}\nContraseña: {$data['password']}"
            );
        } catch (\Exception $e) {
            Log::error('WhatsApp send failed', ['error' => $e->getMessage()]);
        }

        return response()->json(['message' => 'Credenciales enviadas']);
    }
}
