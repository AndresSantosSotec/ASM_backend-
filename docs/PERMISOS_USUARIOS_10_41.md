# ✅ PERMISOS ASIGNADOS - USUARIOS 10 Y 41

## 📊 Resumen Ejecutivo

**Fecha:** 24 de octubre de 2025  
**Acción:** Asignación de todos los permisos disponibles  
**Usuarios afectados:** 2  
**Total permisos por usuario:** 62 moduleviews

---

## 👥 Usuarios

| ID | Username | Email | Permisos |
|----|----------|-------|----------|
| 10 | PabloAdmin | admin@blueatlas.com | 62 vistas |
| 41 | i.american | mlpdbz300@gmail.com | 62 vistas |

---

## 🔒 Configuración de Scope

**Valor asignado:** `'self'`

**Valores permitidos por la BD:**
- ✅ `'global'` - Acceso global
- ✅ `'group'` - Acceso por grupo
- ✅ `'self'` - Acceso individual (usado actualmente)
- ❌ `'full'` - **NO VÁLIDO** (causa error de constraint)

---

## 📦 Módulos con Acceso

Ambos usuarios tienen acceso a **TODAS** las vistas de los siguientes módulos:

### 1️⃣ Prospectos Y Asesores (9 vistas)
- Gestión de Prospectos
- Captura de Prospectos
- Leads Asignados
- Panel de Seguimiento
- Importar Leads
- Correos
- Calendario
- Admin General
- Gestión de Leads

### 2️⃣ Inscripción (6 vistas)
- Ficha de Inscripción
- Revisión de Fichas
- Firma Digital
- Validación de Documentos
- Periodos de Inscripción
- Flujos de Aprobación

### 3️⃣ Académico (8 vistas)
- Programas Académicos
- Gestión de Usuarios
- Programación de Cursos
- Asignación de Cursos
- Estatus Académico
- Ranking Académico
- Moodle
- Migrar Estudiantes

### 4️⃣ Docentes (10 vistas)
- Portal Docente
- Mis Cursos
- Alumnos
- Material Didáctico
- Mensajería e Invitaciones
- Medallero e Insignias
- Mi Aprendizaje
- Calendario
- Notificaciones
- Certificaciones

### 5️⃣ Estudiantes (9 vistas)
- Dashboard Estudiantil
- Documentos
- Gestión de Pagos
- Ranking Estudiantil
- Calendario Académico
- Notificaciones
- Mi Perfil
- Estado de Cuenta
- Chat docente

### 6️⃣ Finanzas y Pagos (7 vistas)
- Dashboard Financiero
- Estado de Cuenta
- Gestión de Pagos
- Conciliación Bancaria
- Seguimiento de Cobros
- Reportes Financieros
- Configuración

### 7️⃣ Administración (6 vistas)
- Dashboard Administrativo
- Programación de Cursos
- Reportes de Matrícula
- Reporte de Ingresos
- Plantillas y Mailing
- Configuración General

### 8️⃣ Seguridad (7 vistas)
- Gestión de usuarios
- Asignación de permisos
- Logs de auditoría
- Políticas de seguridad
- Dashboard de Seguridad
- Autenticación 2FA
- Accesos
- Auditoría

---

## 🔧 Comandos Útiles

### Ver permisos de un usuario
```sql
SELECT 
    mv.menu,
    mv.submenu,
    mv.view_path,
    up.scope
FROM userpermissions up
JOIN moduleviews mv ON mv.id = up.moduleview_id
WHERE up.user_id = 10  -- o 41
ORDER BY mv.order_num;
```

### Agregar un permiso específico
```sql
INSERT INTO userpermissions (user_id, moduleview_id, assigned_at, scope)
VALUES (10, 29, NOW(), 'self');
```

### Quitar un permiso específico
```sql
DELETE FROM userpermissions 
WHERE user_id = 10 AND moduleview_id = 29;
```

### Cambiar scope de todos los permisos de un usuario
```sql
UPDATE userpermissions 
SET scope = 'global'  -- o 'group' o 'self'
WHERE user_id = 10;
```

### Limpiar todos los permisos de un usuario
```sql
DELETE FROM userpermissions WHERE user_id = 10;
```

---

## ⚠️ Error Común RESUELTO

**Error anterior:**
```
ERROR: el nuevo registro para la relación «userpermissions» viola la restricción «check» «userpermissions_scope_check»
Detail: La fila que falla contiene (..., full, ...)
```

**Causa:** Intentar usar `scope = 'full'` (no permitido)

**Solución:** Usar solo valores válidos: `'global'`, `'group'`, o `'self'`

---

## ✅ Verificación

Ejecutar para verificar:
```bash
php show_user_permissions_detail.php
```

O en SQL:
```sql
SELECT user_id, COUNT(*) as total
FROM userpermissions
WHERE user_id IN (10, 41)
GROUP BY user_id;
```

**Resultado esperado:**
- Usuario 10: 62 permisos
- Usuario 41: 62 permisos

---

## 📝 Archivos Generados

- `assign_all_permissions_users_10_41.sql` - Script SQL manual
- `assign_all_permissions_to_users.php` - Script PHP ejecutado
- `show_user_permissions_detail.php` - Script de verificación
- `PERMISOS_USUARIOS_10_41_RESUMEN.sql` - Queries de consulta
- `check_scope_constraint.php` - Verificación de constraints

---

**Estado:** ✅ COMPLETADO EXITOSAMENTE  
**Fecha:** 2025-10-24 23:54
