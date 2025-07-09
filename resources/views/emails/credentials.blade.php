<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Credenciales de Acceso</title>
</head>
<body>
    <p>Hola {{ $student->nombre_completo }},</p>
    <p>Estas son tus credenciales de acceso:</p>
    <ul>
        <li><strong>Usuario:</strong> {{ $credentials['username'] }}</li>
        <li><strong>Contraseña:</strong> {{ $credentials['password'] }}</li>
    </ul>
    <p>Te recomendamos cambiar la contraseña luego de iniciar sesión.</p>
</body>
</html>
