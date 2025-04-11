<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\welcomeEmail;

class emailtest extends Controller
{
    public function sendWelcomeEmail()
    {
        $toEmail ='mlpdbz300@gmail.com';
        $subject = 'Bienvenido a nuestro servicio';
        $message = 'Hola, gracias por registrarte.';
        Mail::to($toEmail)->send(new welcomeEmail($message, $subject));
        
        $respose =Mail::to($toEmail)->send(new welcomeEmail($message, $subject));
        if ($respose) {
            return response()->json(['message' => 'Correo enviado con éxito']);
        } else {
            return response()->json(['message' => 'Error al enviar el correo'], 500);
        }
        

    }
}
