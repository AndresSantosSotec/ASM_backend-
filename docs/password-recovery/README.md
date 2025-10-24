# 🔐 Documentación - Sistema de Recuperación de Contraseña

## 📚 Índice de Documentación

Esta carpeta contiene toda la documentación del **Sistema de Recuperación de Contraseña** implementado para ASMProlink Backend.

---

## 📂 Estructura de Archivos

```
docs/password-recovery/
│
├── README.md (este archivo)
│
├── 01-DOCUMENTACION-COMPLETA.md
│   └── Documentación técnica detallada (850+ líneas)
│
├── 02-GUIA-RAPIDA.md
│   └── Guía rápida para usuarios y desarrolladores
│
├── 03-RESUMEN-IMPLEMENTACION.md
│   └── Resumen de la implementación y checklist
│
├── 04-INDICE-ARCHIVOS.md
│   └── Índice de todos los archivos del sistema
│
└── tests/
    ├── postman-collection.json
    │   └── Colección de Postman para testing
    │
    └── TEST-CASES.md
        └── Casos de prueba detallados

```

---

## 🎯 Guía de Lectura Recomendada

### Para Nuevos Desarrolladores:
1. **Primero:** `README.md` (este archivo) - Visión general
2. **Segundo:** `02-GUIA-RAPIDA.md` - Conceptos básicos y testing rápido
3. **Tercero:** `01-DOCUMENTACION-COMPLETA.md` - Profundizar en detalles técnicos
4. **Cuarto:** `tests/TEST-CASES.md` - Entender los casos de prueba

### Para Administradores/Usuarios:
1. `02-GUIA-RAPIDA.md` - Sección "Para Usuarios"
2. `01-DOCUMENTACION-COMPLETA.md` - Sección "Casos de Uso"

### Para Testing/QA:
1. `tests/TEST-CASES.md` - Casos de prueba completos
2. `tests/postman-collection.json` - Importar en Postman
3. `01-DOCUMENTACION-COMPLETA.md` - Sección "Testing"

### Para Mantenimiento:
1. `03-RESUMEN-IMPLEMENTACION.md` - Checklist de implementación
2. `04-INDICE-ARCHIVOS.md` - Ubicación de todos los archivos
3. `01-DOCUMENTACION-COMPLETA.md` - Sección "Troubleshooting"

---

## 🔍 Búsqueda Rápida por Tema

### 📊 Base de Datos
- **Migración:** Ver `01-DOCUMENTACION-COMPLETA.md` → "Base de Datos"
- **Modelo:** Ver `04-INDICE-ARCHIVOS.md` → "Modelos"
- **Queries:** Ver `01-DOCUMENTACION-COMPLETA.md` → "Estadísticas y Métricas"

### 🔐 Seguridad
- **Rate Limiting:** Ver `01-DOCUMENTACION-COMPLETA.md` → "Seguridad Implementada"
- **Generación de Contraseñas:** Ver `01-DOCUMENTACION-COMPLETA.md` → "Seguridad Implementada" → Punto 3
- **Prevención de Enumeración:** Ver `01-DOCUMENTACION-COMPLETA.md` → "Seguridad Implementada" → Punto 2

### 📧 Sistema de Emails
- **Configuración SMTP:** Ver `01-DOCUMENTACION-COMPLETA.md` → "Configuración SMTP"
- **Template HTML:** Ver `04-INDICE-ARCHIVOS.md` → "Vistas"
- **Determinación de Destinatario:** Ver `01-DOCUMENTACION-COMPLETA.md` → "Sistema de Emails"

### 🚀 API Endpoint
- **Request/Response:** Ver `01-DOCUMENTACION-COMPLETA.md` → "Uso del Endpoint"
- **Testing:** Ver `tests/TEST-CASES.md`
- **Postman:** Importar `tests/postman-collection.json`

### 🛠️ Troubleshooting
- **Problemas Comunes:** Ver `01-DOCUMENTACION-COMPLETA.md` → "Troubleshooting"
- **Email No Llega:** Ver `02-GUIA-RAPIDA.md` → "Problemas Comunes"
- **Rate Limiting:** Ver `01-DOCUMENTACION-COMPLETA.md` → "Troubleshooting" → Problema 2

### 📝 Logging
- **Logs Laravel:** Ver `01-DOCUMENTACION-COMPLETA.md` → "Logging y Auditoría"
- **Logs Base de Datos:** Ver `01-DOCUMENTACION-COMPLETA.md` → "Logging y Auditoría" → "Logs en Base de Datos"
- **Queries de Auditoría:** Ver `02-GUIA-RAPIDA.md` → "Para Administradores"

---

## 🎓 Resumen Funcional

### ¿Qué hace este sistema?

Permite a los usuarios recuperar el acceso a su cuenta cuando olvidan su contraseña, mediante:

1. **Solicitud por Email:** El usuario ingresa su correo electrónico
2. **Generación Automática:** El sistema genera una contraseña temporal segura (8 caracteres)
3. **Envío por Correo:** Se envía la contraseña al email correcto según el rol
4. **Logging Completo:** Todos los intentos se registran para auditoría
5. **Seguridad:** Rate limiting (1/hora) y sin enumeración de emails

### Características Principales:

✅ **Endpoint Público:** `/api/password/recover` (sin autenticación)  
✅ **Rate Limiting:** 1 solicitud por hora por IP  
✅ **Contraseñas Seguras:** 8 caracteres (mayúsculas, minúsculas, números, especiales)  
✅ **Email Inteligente:** Determina automáticamente el destinatario según rol  
✅ **Logging Completo:** Base de datos + Laravel logs  
✅ **Seguridad Total:** Prevención de enumeración, hashing bcrypt, transacciones DB  

---

## 🧪 Testing Rápido

### 1. Importar Postman Collection

```bash
# Archivo ubicado en:
docs/password-recovery/tests/postman-collection.json
```

**Pasos:**
1. Abrir Postman
2. Click "Import"
3. Seleccionar `postman-collection.json`
4. Configurar variable `base_url` = `http://localhost:8000`

### 2. Ejecutar Request

```http
POST http://localhost:8000/api/password/recover
Content-Type: application/json

{
  "email": "usuario@ejemplo.com"
}
```

### 3. Verificar Logs

```bash
# Ver logs en tiempo real
tail -f storage/logs/laravel.log | grep "PASSWORD RECOVERY"
```

### 4. Verificar Base de Datos

```sql
SELECT * FROM password_reset_logs ORDER BY created_at DESC LIMIT 10;
```

---

## 📊 Archivos del Sistema

### Backend (Laravel)

| Archivo | Ubicación | Descripción |
|---------|-----------|-------------|
| **Migración** | `database/migrations/2025_10_24_135345_create_password_reset_logs_table.php` | Tabla de logs |
| **Modelo** | `app/Models/PasswordResetLog.php` | Eloquent Model |
| **Controlador** | `app/Http/Controllers/Api/PasswordRecoveryController.php` | Lógica principal |
| **Mailable** | `app/Mail/TemporaryPasswordMail.php` | Email handler |
| **Vista** | `resources/views/emails/temporary-password.blade.php` | Template HTML |
| **Ruta** | `routes/api.php` | Endpoint público |

**Detalles completos en:** `04-INDICE-ARCHIVOS.md`

---

## 🔄 Flujo Completo del Sistema

```
┌─────────────────────────────────────────────────────────────┐
│  1. Usuario solicita recuperación                          │
│     POST /api/password/recover                              │
│     { "email": "usuario@ejemplo.com" }                      │
└─────────────────┬───────────────────────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────────────────────┐
│  2. Validación y Rate Limiting                              │
│     • Email válido?                                         │
│     • Rate limit OK? (1/hora)                               │
└─────────────────┬───────────────────────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────────────────────┐
│  3. Búsqueda de Usuario                                     │
│     • Buscar user por email                                 │
│     • Si no existe → Respuesta genérica (seguridad)         │
└─────────────────┬───────────────────────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────────────────────┐
│  4. Determinar Email Destino                                │
│     • Si rol = Estudiante/Prospecto:                        │
│       → Buscar prospecto.correo_electronico                 │
│     • Otros roles:                                          │
│       → Usar user.email                                     │
└─────────────────┬───────────────────────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────────────────────┐
│  5. Generar Contraseña Temporal                             │
│     • 8 caracteres                                          │
│     • Al menos 1 mayúscula                                  │
│     • Al menos 1 minúscula                                  │
│     • Al menos 1 número                                     │
│     • Al menos 1 carácter especial                          │
│     • Mezcla aleatoria                                      │
└─────────────────┬───────────────────────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────────────────────┐
│  6. Actualizar Base de Datos                                │
│     • Hash contraseña (bcrypt)                              │
│     • Actualizar user.password                              │
│     • Transacción DB                                        │
└─────────────────┬───────────────────────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────────────────────┐
│  7. Enviar Email                                            │
│     • Template HTML profesional                             │
│     • Contraseña temporal visible                           │
│     • Instrucciones de seguridad                            │
└─────────────────┬───────────────────────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────────────────────┐
│  8. Registrar Log                                           │
│     • Guardar en password_reset_logs                        │
│     • Registrar: user_id, email, IP, status                 │
│     • Log Laravel (con emojis 🔐 📧 ✅)                      │
└─────────────────┬───────────────────────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────────────────────┐
│  9. Respuesta al Cliente                                    │
│     HTTP 200 OK                                             │
│     {                                                       │
│       "success": true,                                      │
│       "message": "Si el correo está registrado..."          │
│     }                                                       │
└─────────────────────────────────────────────────────────────┘
```

**Diagrama completo en:** `01-DOCUMENTACION-COMPLETA.md` → "Flujo Completo del Sistema"

---

## 📞 Soporte y Contacto

### Para Desarrolladores:
- Ver documentación técnica completa
- Revisar casos de prueba
- Consultar troubleshooting

### Para Administradores:
- Revisar logs de auditoría
- Ejecutar queries de estadísticas
- Verificar intentos de recuperación

### Para Usuarios:
- Seguir guía rápida
- Contactar al administrador si hay problemas

---

## 🔗 Enlaces Rápidos

| Documento | Descripción | Para |
|-----------|-------------|------|
| [01-DOCUMENTACION-COMPLETA.md](./01-DOCUMENTACION-COMPLETA.md) | Documentación técnica detallada | Desarrolladores |
| [02-GUIA-RAPIDA.md](./02-GUIA-RAPIDA.md) | Guía rápida de uso y testing | Todos |
| [03-RESUMEN-IMPLEMENTACION.md](./03-RESUMEN-IMPLEMENTACION.md) | Resumen y checklist | Dev/Admin |
| [04-INDICE-ARCHIVOS.md](./04-INDICE-ARCHIVOS.md) | Índice de archivos del sistema | Desarrolladores |
| [tests/TEST-CASES.md](./tests/TEST-CASES.md) | Casos de prueba detallados | QA/Testing |
| [tests/postman-collection.json](./tests/postman-collection.json) | Colección de Postman | Testing |

---

## 📅 Información del Sistema

**Sistema:** ASMProlink Backend  
**Framework:** Laravel 10.x  
**Base de Datos:** PostgreSQL  
**Autenticación:** Laravel Sanctum  
**Fecha de Implementación:** 24 de Octubre, 2025  
**Estado:** ✅ Completo y Funcional  
**Versión:** 1.0.0  

---

## ✅ Estado de la Implementación

- [x] Base de datos (migración ejecutada)
- [x] Modelo Eloquent (con relaciones)
- [x] Controlador (5 métodos, 321 líneas)
- [x] Mailable (email handler)
- [x] Template HTML (diseño profesional)
- [x] Ruta API (con rate limiting)
- [x] Logging completo (BD + Laravel)
- [x] Seguridad implementada
- [x] Documentación completa
- [x] Casos de prueba
- [x] Colección Postman

**Total de Líneas de Código:** ~900  
**Total de Líneas de Documentación:** ~2,000  
**Total de Archivos Creados:** 11  

---

## 🎉 Conclusión

El **Sistema de Recuperación de Contraseña** está completamente implementado, documentado y listo para usar en producción.

**Características destacadas:**
- ✅ Seguro y robusto
- ✅ Fácil de usar
- ✅ Completamente documentado
- ✅ Con casos de prueba
- ✅ Logging completo para auditoría

Para comenzar, revisa la **Guía Rápida** en `02-GUIA-RAPIDA.md`.

---

**© 2025 ASMProlink. Todos los derechos reservados.**
