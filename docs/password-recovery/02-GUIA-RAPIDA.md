# 🚀 Guía Rápida - Recuperación de Contraseña

## 📋 Para Usuarios

### ¿Olvidaste tu contraseña?

**Paso 1:** Ve a la pantalla de login del sistema

**Paso 2:** Haz clic en "¿Olvidaste tu contraseña?"

**Paso 3:** Ingresa tu correo electrónico registrado

**Paso 4:** Revisa tu bandeja de entrada

- **Si eres Estudiante:** Revisa tu correo institucional (el que registraste en el la tabla prospectos)
- **Si eres Admin/Asesor:** Revisa el email de tu cuenta de usuario

**Paso 5:** Busca el email con asunto "Recuperación de contraseña - Sistema ASMProlink"

**Paso 6:** Copia la contraseña temporal (8 caracteres)

**Paso 7:** Inicia sesión con la contraseña temporal

**Paso 8:** **MUY IMPORTANTE:** Cambia tu contraseña inmediatamente
- Ve a tu perfil de usuario
- Selecciona "Cambiar contraseña"
- Ingresa una contraseña nueva y segura

---

## 🔧 Para Desarrolladores

### Testing Rápido

**1. Probar con Postman:**

```http
POST http://localhost:8000/api/password/recover
Content-Type: application/json

{
  "email": "tu_email@ejemplo.com"
}
```

**Respuesta esperada (200):**
```json
{
  "success": true,
  "message": "Si el correo electrónico está registrado, recibirás un email con tu nueva contraseña temporal."
}
```

**2. Verificar logs:**
```bash
tail -f storage/logs/laravel.log | grep "PASSWORD RECOVERY"
```

**3. Verificar base de datos:**
```sql
SELECT * FROM password_reset_logs ORDER BY created_at DESC LIMIT 10;
```

---

## 🎯 Endpoint en Producción

**URL:** `https://tu-dominio.com/api/password/recover`

**Método:** POST

**Rate Limit:** 1 solicitud por hora por IP

**Sin autenticación requerida** ✅

---

## 🔐 Seguridad

### ✅ Lo que SÍ hace el sistema:
- Genera contraseñas seguras de 8 caracteres
- Envía email automáticamente
- Registra todos los intentos (auditoría)
- Limita a 1 solicitud por hora por IP
- No revela si el email existe o no

### ❌ Lo que NO hace el sistema:
- No permite múltiples solicitudes rápidas
- No muestra contraseñas en logs
- No revela información de usuarios
- No permite ataques de fuerza bruta

---

## 📧 ¿A dónde se envía el email?

| Rol del Usuario | Email Destino |
|-----------------|---------------|
| Estudiante | Correo del prospecto (correo_electronico) |
| Prospecto | Correo del prospecto (correo_electronico) |
| Administrador | Email del usuario (user.email) |
| Asesor | Email del usuario (user.email) |
| Otros | Email del usuario (user.email) |

---

## 🚨 Problemas Comunes

### No recibo el email

**Causas:**
1. Email ingresado no está registrado
2. El email está en spam/correo no deseado
3. Servidor SMTP tiene problemas

**Soluciones:**
1. Verifica que el email sea el correcto
2. Revisa carpeta de spam
3. Contacta al administrador del sistema

### Rate Limit - "Too Many Requests"

**Causa:** Ya solicitaste recuperación en la última hora

**Solución:** Espera 1 hora antes de intentar nuevamente

### Contraseña temporal no funciona

**Causa:** La contraseña fue cambiada o ha expirado

**Solución:** Solicita una nueva recuperación de contraseña

---

## 📊 Para Administradores

### Ver intentos de recuperación recientes:

```sql
SELECT 
    u.email,
    prl.email_destino,
    prl.status,
    prl.ip_address,
    prl.created_at
FROM password_reset_logs prl
INNER JOIN users u ON u.id = prl.user_id
WHERE prl.created_at >= NOW() - INTERVAL '7 days'
ORDER BY prl.created_at DESC;
```

### Ver intentos fallidos:

```sql
SELECT 
    email_destino,
    status,
    notes,
    created_at
FROM password_reset_logs
WHERE status = 'failed'
ORDER BY created_at DESC
LIMIT 20;
```

### Ver actividad sospechosa (muchos intentos desde misma IP):

```sql
SELECT 
    ip_address,
    COUNT(*) as intentos,
    MAX(created_at) as ultimo_intento
FROM password_reset_logs
WHERE created_at >= NOW() - INTERVAL '24 hours'
GROUP BY ip_address
HAVING COUNT(*) > 3
ORDER BY intentos DESC;
```

---

## 📝 Notas Técnicas

- **Contraseña temporal:** 8 caracteres (mayúsculas, minúsculas, números, especiales)
- **Algoritmo de hash:** bcrypt (por defecto en Laravel)
- **Almacenamiento de logs:** PostgreSQL (`password_reset_logs`)
- **Template del email:** Blade (`resources/views/emails/temporary-password.blade.php`)
- **Rate limiting:** Cache de Laravel (Redis/File)

---

## 🔄 Flujo Visual Simplificado

```
Usuario ingresa email
        ↓
Sistema valida formato
        ↓
Rate limit OK?
        ↓
Usuario existe?
        ↓
Genera contraseña temporal (8 chars)
        ↓
Actualiza password en BD (hash)
        ↓
Envía email con contraseña
        ↓
Registra intento en logs
        ↓
Respuesta genérica al usuario
```

---

## 📞 Contacto de Soporte

Si tienes problemas con la recuperación de contraseña:

1. **Estudiantes:** Contacta a tu asesor académico
2. **Personal:** Contacta al administrador del sistema
3. **Administradores:** Revisa logs y BD

---

**Última actualización:** 24 de Octubre, 2025  
**Versión del sistema:** 1.0.0  
**Estado:** ✅ Implementado y funcional
