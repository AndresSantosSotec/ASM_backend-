# 🔐 API de Recuperación de Contraseñas

## 📋 Descripción

Sistema de recuperación de contraseñas que genera una contraseña temporal de 8 caracteres y la envía por correo electrónico al usuario.

---

## 🚀 Endpoint

### **POST** `/api/auth/password/recover`

Solicita la recuperación de contraseña para un usuario registrado.

---

## 📥 Request

### **Headers**
```http
Content-Type: application/json
Accept: application/json
```

### **Body** (JSON)
```json
{
  "email": "usuario@ejemplo.com"
}
```

### **Parámetros**

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `email` | string | ✅ Sí | Email del usuario registrado en el sistema (máx. 100 caracteres) |

---

## 📤 Response

### **✅ Éxito (200 OK)**

```json
{
  "success": true,
  "message": "Si el correo electrónico está registrado, recibirás un email con tu nueva contraseña temporal."
}
```

**⚠️ Importante:** La respuesta es **siempre la misma** independientemente de si el email existe o no (medida de seguridad para prevenir enumeración de usuarios).

### **❌ Error de Validación (422 Unprocessable Entity)**

```json
{
  "success": false,
  "message": "El correo electrónico proporcionado no es válido.",
  "errors": {
    "email": [
      "El campo email es obligatorio.",
      "El email debe ser una dirección de correo válida."
    ]
  }
}
```

### **❌ Error del Servidor (500 Internal Server Error)**

```json
{
  "success": false,
  "message": "Ocurrió un error al procesar la solicitud. Por favor, inténtalo de nuevo."
}
```

---

## 🔄 Flujo del Sistema

```
1. Usuario envía email
        ↓
2. Sistema valida formato del email
        ↓
3. Busca usuario en base de datos
        ↓
4. ¿Usuario existe?
   ├─ NO → Responde mensaje genérico (sin enviar email)
   └─ SÍ → Continúa...
        ↓
5. Determina email destino según rol:
   ├─ Estudiante/Prospecto → correo_electronico de tabla prospectos
   └─ Otros roles → email de tabla users
        ↓
6. Genera contraseña temporal segura (8 caracteres)
        ↓
7. Actualiza contraseña en base de datos
        ↓
8. Envía email con contraseña temporal
        ↓
9. Registra log del intento
        ↓
10. Responde mensaje genérico al usuario
```

---

## 📧 Email Enviado

El usuario recibirá un correo electrónico con:

- **Asunto:** "Recuperación de contraseña - Sistema ASMProlink"
- **Contenido:**
  - Nombre del usuario
  - Contraseña temporal (8 caracteres)
  - Carnet (si aplica)
  - Instrucciones para cambiar la contraseña

**Ejemplo del email:**
```
Hola Juan Pérez,

Hemos recibido una solicitud para restablecer tu contraseña.

Tu nueva contraseña temporal es: K7m@pqRt

Carnet: ASM2023001

Por favor, inicia sesión con esta contraseña y cámbiala 
inmediatamente por una de tu preferencia.

Si no solicitaste este cambio, por favor contacta al 
administrador del sistema.

Saludos,
Equipo ASMProlink
```

---

## 🔑 Características de la Contraseña Temporal

| Característica | Detalle |
|----------------|---------|
| **Longitud** | 8 caracteres exactos |
| **Mayúsculas** | Al menos 1 (A-Z) |
| **Minúsculas** | Al menos 1 (a-z) |
| **Números** | Al menos 1 (0-9) |
| **Caracteres especiales** | Al menos 1 (!@#$%&\*) |
| **Aleatoriedad** | Completamente aleatoria y mezclada |

**Ejemplos de contraseñas generadas:**
- `K7m@pqRt`
- `B3n!aDfX`
- `M9k$wPzL`
- `p2T@nKm5`

---

## 📮 Email Destino según Rol

| Rol del Usuario | Email Destino | Tabla de Origen |
|----------------|---------------|-----------------|
| **Estudiante** | `correo_electronico` | `prospectos` |
| **Prospecto** | `correo_electronico` | `prospectos` |
| **Administrador** | `email` | `users` |
| **Facilitador** | `email` | `users` |
| **Otros roles** | `email` | `users` |

### **Ejemplo:**
```
Usuario en tabla users:
- email: juan@sistema.com
- carnet: ASM2023001
- rol: Estudiante

Prospecto en tabla prospectos:
- carnet: ASM2023001
- correo_electronico: juan.personal@gmail.com

→ Email se envía a: juan.personal@gmail.com ✅
  (NO a juan@sistema.com)
```

---

## 💻 Ejemplos de Consumo

### **JavaScript (Fetch)**

```javascript
async function recuperarContrasena(email) {
  try {
    const response = await fetch('http://localhost:8000/api/auth/password/recover', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({ email })
    });

    const data = await response.json();

    if (data.success) {
      alert(data.message);
    } else {
      alert('Error: ' + data.message);
    }
  } catch (error) {
    console.error('Error:', error);
    alert('Error de conexión con el servidor');
  }
}

// Uso
recuperarContrasena('juan@sistema.com');
```

### **JavaScript (Axios)**

```javascript
import axios from 'axios';

async function recuperarContrasena(email) {
  try {
    const response = await axios.post('http://localhost:8000/api/auth/password/recover', {
      email: email
    }, {
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      }
    });

    if (response.data.success) {
      console.log('✅ Solicitud exitosa:', response.data.message);
    }
  } catch (error) {
    if (error.response) {
      console.error('❌ Error:', error.response.data.message);
    } else {
      console.error('❌ Error de red:', error.message);
    }
  }
}
```

### **cURL**

```bash
curl -X POST http://localhost:8000/api/auth/password/recover \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "juan@sistema.com"
  }'
```

### **Postman**

```
Method: POST
URL: http://localhost:8000/api/auth/password/recover

Headers:
  Content-Type: application/json
  Accept: application/json

Body (raw JSON):
{
  "email": "juan@sistema.com"
}
```

### **PHP (cURL)**

```php
<?php
$email = 'juan@sistema.com';

$ch = curl_init('http://localhost:8000/api/auth/password/recover');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'email' => $email
]));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);

if ($httpCode === 200 && $data['success']) {
    echo "✅ " . $data['message'];
} else {
    echo "❌ " . $data['message'];
}
?>
```

### **Python (requests)**

```python
import requests

def recuperar_contrasena(email):
    url = 'http://localhost:8000/api/auth/password/recover'
    headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
    data = {
        'email': email
    }
    
    try:
        response = requests.post(url, json=data, headers=headers)
        result = response.json()
        
        if result.get('success'):
            print(f"✅ {result['message']}")
        else:
            print(f"❌ {result['message']}")
    except Exception as e:
        print(f"❌ Error: {str(e)}")

# Uso
recuperar_contrasena('juan@sistema.com')
```

---

## 📝 Logs del Sistema

Cada intento de recuperación se registra en la tabla `password_reset_logs`:

```sql
SELECT * FROM password_reset_logs WHERE user_id = 42;
```

**Resultado:**
```
id: 1
user_id: 42
email_destino: juan.personal@gmail.com
ip_address: 192.168.1.100
user_agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)...
status: success
reset_method: temporary_password
notes: Contraseña temporal enviada exitosamente
created_at: 2025-10-24 17:30:00
updated_at: 2025-10-24 17:30:00
```

### **Valores de `status`:**
- `success` - Contraseña enviada exitosamente
- `failed` - Error al procesar la solicitud

---

## 🔒 Medidas de Seguridad

| Medida | Descripción |
|--------|-------------|
| **Respuesta genérica** | No revela si el email existe o no |
| **Prevención de enumeración** | Imposible saber qué emails están registrados |
| **Contraseña fuerte** | 8 caracteres con mayúsculas, minúsculas, números y especiales |
| **Encriptación bcrypt** | Las contraseñas se guardan encriptadas |
| **Logging completo** | Se registra IP, user agent y timestamp |
| **Transacciones** | Rollback automático en caso de error |

---

## ⚠️ Casos Especiales

### **1. Email no existe**
```json
POST { "email": "noexiste@email.com" }

Response:
{
  "success": true,
  "message": "Si el correo electrónico está registrado..."
}
```
✅ Responde éxito pero NO envía email (seguridad)

### **2. Prospecto sin correo_electronico**
```
User: juan@sistema.com (Estudiante)
Prospecto: carnet=ASM2023001, correo_electronico=NULL

→ Fallback: usa user.email
→ Email enviado a: juan@sistema.com
```

### **3. Email inválido**
```json
POST { "email": "email-invalido" }

Response (422):
{
  "success": false,
  "message": "El correo electrónico proporcionado no es válido.",
  "errors": {
    "email": ["El email debe ser una dirección de correo válida."]
  }
}
```

---

## 🧪 Testing

### **Prueba Manual**

1. Ejecutar el servidor:
```bash
php artisan serve
```

2. Probar con cURL:
```bash
curl -X POST http://localhost:8000/api/auth/password/recover \
  -H "Content-Type: application/json" \
  -d '{"email": "admin@sistema.com"}'
```

3. Verificar email recibido

4. Consultar logs:
```sql
SELECT * FROM password_reset_logs ORDER BY created_at DESC LIMIT 5;
```

### **Verificar en Laravel Log**

```bash
tail -f storage/logs/laravel.log | grep "PASSWORD RECOVERY"
```

**Salida esperada:**
```
[2025-10-24 17:30:00] 🔐 [PASSWORD RECOVERY] Solicitud recibida
[2025-10-24 17:30:01] 🔍 [PASSWORD RECOVERY] Determinando email destino
[2025-10-24 17:30:02] 🔑 [PASSWORD RECOVERY] Contraseña temporal generada
[2025-10-24 17:30:03] 💾 [PASSWORD RECOVERY] Contraseña actualizada en BD
[2025-10-24 17:30:04] 📧 [PASSWORD RECOVERY] Email enviado exitosamente
[2025-10-24 17:30:05] ✅ [PASSWORD RECOVERY] Proceso completado exitosamente
```

---

## 📚 Archivos Relacionados

| Archivo | Descripción |
|---------|-------------|
| `app/Http/Controllers/Api/PasswordRecoveryController.php` | Controlador principal |
| `app/Mail/TemporaryPasswordMail.php` | Clase del email |
| `resources/views/emails/temporary-password.blade.php` | Template del email |
| `app/Models/PasswordResetLog.php` | Modelo de logs |
| `routes/api.php` | Definición de la ruta |

---

## 🆘 Soporte

Si tienes problemas:

1. **Verificar logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

2. **Verificar configuración de email:**
   ```bash
   php artisan tinker
   >>> config('mail.mailers.smtp')
   ```

3. **Probar envío de email:**
   ```bash
   php artisan tinker
   >>> Mail::raw('Test', fn($m) => $m->to('test@email.com')->subject('Test'));
   ```

---

## 📞 Contacto

Para dudas o problemas, contacta al equipo de desarrollo de ASMProlink.

---

**Versión:** 1.0.0  
**Última actualización:** 24 de Octubre, 2025
