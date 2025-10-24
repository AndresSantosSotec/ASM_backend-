<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperación de Contraseña</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            background-color: #007bff;
            color: #ffffff;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
            margin: -30px -30px 20px -30px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            padding: 20px 0;
        }
        .password-box {
            background-color: #f8f9fa;
            border: 2px solid #007bff;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        .password-box .label {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 10px;
        }
        .password-box .password {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
            letter-spacing: 2px;
            font-family: 'Courier New', monospace;
        }
        .warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .warning strong {
            color: #856404;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
            font-size: 12px;
            color: #6c757d;
            text-align: center;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background-color: #007bff;
            color: #ffffff;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
        .info-box {
            background-color: #e7f3ff;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Recuperación de Contraseña</h1>
        </div>

        <div class="content">
            <p><strong>Hola, {{ $userName }}</strong></p>

            @if($carnet)
                <div class="info-box">
                    <strong>Carnet:</strong> {{ $carnet }}
                </div>
            @endif

            <p>Hemos recibido una solicitud para recuperar tu contraseña en el Sistema ASMProlink.</p>

            <p>Tu nueva contraseña temporal es:</p>

            <div class="password-box">
                <div class="label">CONTRASEÑA TEMPORAL</div>
                <div class="password">{{ $temporaryPassword }}</div>
            </div>

            <div class="warning">
                <strong>⚠️ IMPORTANTE - SEGURIDAD:</strong>
                <ul style="margin: 10px 0 0 0; padding-left: 20px;">
                    <li>Esta contraseña es <strong>temporal</strong></li>
                    <li>Por favor, <strong>cámbiala inmediatamente</strong> después de iniciar sesión</li>
                    <li>No compartas esta contraseña con nadie</li>
                    <li>Si no solicitaste este cambio, contacta al administrador de inmediato</li>
                </ul>
            </div>

            <div class="info-box">
                <strong>Pasos a seguir:</strong>
                <ol style="margin: 10px 0 0 0; padding-left: 20px;">
                    <li>Inicia sesión en el sistema con tu carnet/email y esta contraseña temporal</li>
                    <li>Ve a tu perfil de usuario</li>
                    <li>Cambia tu contraseña por una nueva y segura</li>
                </ol>
            </div>

            <p style="margin-top: 30px;">Si tienes algún problema, no dudes en contactar al soporte técnico.</p>
        </div>

        <div class="footer">
            <p><strong>Sistema ASMProlink</strong></p>
            <p>Este es un correo automático, por favor no respondas a este mensaje.</p>
            <p style="margin-top: 10px; font-size: 10px; color: #999;">
                © {{ date('Y') }} ASMProlink. Todos los derechos reservados.
            </p>
        </div>
    </div>
</body>
</html>
