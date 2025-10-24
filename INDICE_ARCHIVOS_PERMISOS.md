# Índice de Archivos - Separación de Permisos

## 📋 Resumen

Este documento lista todos los archivos modificados o creados como parte de la separación completa entre permisos por usuario y permisos por rol.

---

## 🗂️ Archivos por Categoría

### 1. Migraciones (2 archivos nuevos)

#### `database/migrations/2025_10_17_000000_create_permisos_table.php`
**Propósito**: Crea la nueva tabla `permisos` para almacenar permisos de usuario.

**Funciones**:
- Crea tabla `permisos` con estructura similar a `permissions` pero separada
- Incluye columnas: `id`, `moduleview_id`, `action`, `name`, `description`, `timestamps`
- Agrega foreign key a `moduleviews`
- Agrega índices para performance

**Cuándo ejecutar**: Primera vez, antes de la migración de datos

---

#### `database/migrations/2025_10_17_000001_migrate_user_permissions_to_permisos.php`
**Propósito**: Migra datos existentes de `permissions` a `permisos`.

**Funciones**:
- Identifica permisos usados por usuarios (desde `userpermissions`)
- Copia esos permisos de `permissions` a `permisos`
- Actualiza `userpermissions` para apuntar a nuevos IDs en `permisos`
- Mantiene mapeo entre IDs antiguos y nuevos

**Cuándo ejecutar**: Después de crear tabla `permisos`, una sola vez

---

### 2. Modelos (4 archivos modificados)

#### `app/Models/Permisos.php`
**Cambio Principal**: `protected $table = 'permisos';` (antes era `'permissions'`)

**Propósito**: Modelo para permisos de USUARIO (individual).

**Relaciones**:
- `users()` - Usuarios que tienen este permiso
- `moduleView()` - Vista asociada al permiso

**Uso**: UserPermisosController, RolePermissionService, comandos artisan

---

#### `app/Models/Permission.php`
**Cambio Principal**: Eliminada relación `users()` (solo para roles ahora)

**Propósito**: Modelo para permisos de ROL (acciones).

**Relaciones**:
- `roles()` - Roles que tienen este permiso
- `moduleview()` - Vista asociada al permiso

**Uso**: RolePermissionController, EffectivePermissionsService

---

#### `app/Models/Role.php`
**Cambio Principal**: `permissions()` ahora usa `Permission::class` en lugar de `Permisos::class`

**Propósito**: Modelo de roles del sistema.

**Impacto**: Ahora roles usan correctamente la tabla `permissions` en lugar de mezclar con `permisos`

---

#### `app/Models/ModulesViews.php`
**Cambio Principal**: Agregada relación `rolePermissions()` adicional a `permissions()`

**Propósito**: Modelo de vistas/pantallas del sistema.

**Relaciones**:
- `rolePermissions()` - Permisos de ROL (tabla `permissions`)
- `permissions()` - Permisos de USUARIO (tabla `permisos`)
- `module()` - Módulo al que pertenece

**Impacto**: Ahora puede acceder a ambos tipos de permisos de forma separada

---

### 3. Controladores (2 archivos modificados)

#### `app/Http/Controllers/Api/UserPermisosController.php`
**Cambios Principales**:
- Línea 85: `DB::table('permisos')` en lugar de `DB::table('permissions')`
- Usa modelo `Permisos` correctamente

**Propósito**: Gestiona permisos por USUARIO.

**Endpoints**:
- `GET /api/userpermissions?user_id={id}` - Lista permisos del usuario
- `POST /api/userpermissions` - Asigna vistas al usuario
- `PUT /api/userpermissions/{id}` - Actualiza un permiso
- `DELETE /api/userpermissions/{id}` - Elimina un permiso

**Impacto**: Ya no mezcla con tabla `permissions`, usa exclusivamente `permisos`

---

#### `app/Http/Controllers/Api/RolePermissionController.php`
**Cambios Principales**:
- Usa `Permission::class` en lugar de `Permisos::class`
- Usa relación `rolePermissions()` en lugar de `permissions()`
- Consulta tabla `permissions` en lugar de `permisos`

**Propósito**: Gestiona permisos por ROL.

**Endpoints**:
- `GET /api/roles/{role}/permissions` - Lista permisos del rol
- `PUT /api/roles/{role}/permissions` - Actualiza permisos del rol

**Impacto**: Ya no mezcla con tabla `permisos`, usa exclusivamente `permissions`

---

### 4. Servicios (2 archivos modificados)

#### `app/Services/EffectivePermissionsService.php`
**Cambios Principales**:
- Línea 21: `join('permisos as p', ...)` para permisos de usuario
- Línea 54: `join('permissions as p', ...)` para permisos de rol
- Comentarios explicando qué tabla se usa y por qué

**Propósito**: Combina permisos de usuario (qué vistas) con permisos de rol (qué acciones).

**Lógica**:
1. Obtiene vistas del usuario desde `permisos` (action='view')
2. Obtiene acciones del rol desde `permissions` (create, edit, delete, export)
3. Combina ambos en un mapa de permisos efectivos

**Impacto**: Separación correcta entre ambas fuentes de permisos

---

#### `app/Services/RolePermissionService.php`
**Cambios Principales**:
- Línea 146: `DB::table('permisos')` en lugar de `'permissions'`
- Línea 197: `DB::table('permisos')` en lugar de `'permissions'`
- Línea 240: `DB::table('permisos')` en lugar de `'permissions'`
- Línea 274: `DB::table('permisos')` en lugar de `'permissions'`

**Propósito**: Asigna permisos a usuarios basándose en su rol.

**Lógica**: Cuando un usuario se le asigna un rol, este servicio crea registros en `userpermissions` apuntando a `permisos`.

**Impacto**: Ahora usa correctamente tabla `permisos` para permisos de usuario

---

### 5. Documentación (4 archivos nuevos)

#### `SEPARACION_PERMISOS.md`
**Propósito**: Documentación técnica completa de la arquitectura.

**Contenido**:
- Descripción de ambos sistemas
- Comparación de tablas
- Esquema de base de datos
- API endpoints
- Ejemplos de uso
- Reglas de separación
- Comandos de verificación

**Audiencia**: Desarrolladores, arquitectos

**Cuándo leer**: Para entender la arquitectura completa

---

#### `GUIA_MIGRACION_PERMISOS.md`
**Propósito**: Guía paso a paso para aplicar las migraciones.

**Contenido**:
- Pasos de migración detallados
- Comandos de verificación
- Solución de problemas comunes
- Checklist de migración
- Comandos de rollback

**Audiencia**: DevOps, administradores de sistemas

**Cuándo leer**: Antes de aplicar migraciones en cualquier entorno

---

#### `RESUMEN_SEPARACION_PERMISOS.md`
**Propósito**: Resumen ejecutivo de todos los cambios.

**Contenido**:
- Problema original
- Solución implementada
- Comparativa antes/después
- Lista de archivos modificados
- Verificación de cumplimiento
- Próximos pasos

**Audiencia**: Project managers, líderes técnicos, todo el equipo

**Cuándo leer**: Para obtener una visión general rápida

---

#### `DIAGRAMA_ARQUITECTURA_PERMISOS.md`
**Propósito**: Diagramas visuales de la arquitectura.

**Contenido**:
- Diagrama de permisos por usuario
- Diagrama de permisos por rol
- Diagrama de permisos efectivos
- Flujos de datos
- Ejemplos de tablas
- Casos de uso ilustrados

**Audiencia**: Todo el equipo (visual y fácil de entender)

**Cuándo leer**: Para visualizar la arquitectura y flujos

---

### 6. Scripts de Verificación (1 archivo nuevo)

#### `verify-permission-separation.php`
**Propósito**: Script ejecutable para verificar que la separación se realizó correctamente.

**Funciones**:
- Verifica existencia de tablas `permisos` y `permissions`
- Verifica estructura de columnas
- Verifica relaciones (foreign keys)
- Detecta referencias rotas
- Detecta duplicados
- Genera estadísticas
- Genera reporte con ✓, ⚠️ y ✗

**Cómo ejecutar**: `php verify-permission-separation.php`

**Cuándo ejecutar**:
- Después de aplicar migraciones
- Después de sincronizar permisos
- Antes de desplegar en producción
- Cuando haya dudas sobre la integridad

---

## 📊 Resumen de Cambios por Tipo

| Tipo | Creados | Modificados | Total |
|------|---------|-------------|-------|
| Migraciones | 2 | 0 | 2 |
| Modelos | 0 | 4 | 4 |
| Controladores | 0 | 2 | 2 |
| Servicios | 0 | 2 | 2 |
| Documentación | 4 | 0 | 4 |
| Scripts | 1 | 0 | 1 |
| **TOTAL** | **7** | **8** | **15** |

---

## 🔄 Orden de Lectura Recomendado

### Para Desarrolladores
1. `RESUMEN_SEPARACION_PERMISOS.md` - Visión general
2. `DIAGRAMA_ARQUITECTURA_PERMISOS.md` - Visualización
3. `SEPARACION_PERMISOS.md` - Detalles técnicos
4. Revisar modelos modificados
5. Revisar controladores modificados

### Para DevOps
1. `RESUMEN_SEPARACION_PERMISOS.md` - Contexto
2. `GUIA_MIGRACION_PERMISOS.md` - Pasos específicos
3. Revisar migraciones
4. Ejecutar `verify-permission-separation.php`

### Para Project Managers
1. `RESUMEN_SEPARACION_PERMISOS.md` - Completo
2. `DIAGRAMA_ARQUITECTURA_PERMISOS.md` - Si necesita visualizar

---

## 🚀 Flujo de Implementación

```
1. LEER DOCUMENTACIÓN
   ├─ RESUMEN_SEPARACION_PERMISOS.md
   └─ GUIA_MIGRACION_PERMISOS.md

2. HACER BACKUP
   └─ pg_dump ... > backup.sql

3. APLICAR MIGRACIONES
   ├─ 2025_10_17_000000_create_permisos_table.php
   └─ 2025_10_17_000001_migrate_user_permissions_to_permisos.php

4. VERIFICAR
   └─ php verify-permission-separation.php

5. SINCRONIZAR
   └─ php artisan permissions:sync --action=view

6. PROBAR
   ├─ GET /api/userpermissions?user_id=1
   ├─ POST /api/userpermissions
   └─ GET /api/roles/1/permissions

7. MONITOREAR
   └─ tail -f storage/logs/laravel.log
```

---

## 📞 Recursos de Ayuda

| Necesito... | Ver archivo... |
|------------|---------------|
| Entender el problema | `RESUMEN_SEPARACION_PERMISOS.md` |
| Ver la arquitectura | `DIAGRAMA_ARQUITECTURA_PERMISOS.md` |
| Aplicar cambios | `GUIA_MIGRACION_PERMISOS.md` |
| Detalles técnicos | `SEPARACION_PERMISOS.md` |
| Verificar correctitud | `verify-permission-separation.php` |
| Ver código de migraciones | `database/migrations/2025_10_17_*` |
| Ver modelos actualizados | `app/Models/` |
| Ver controladores actualizados | `app/Http/Controllers/Api/` |

---

## ✅ Checklist de Uso

- [ ] Leí `RESUMEN_SEPARACION_PERMISOS.md`
- [ ] Entendí los diagramas en `DIAGRAMA_ARQUITECTURA_PERMISOS.md`
- [ ] Leí la guía en `GUIA_MIGRACION_PERMISOS.md`
- [ ] Hice backup de la base de datos
- [ ] Apliqué las migraciones
- [ ] Ejecuté el script de verificación
- [ ] Sincronicé los permisos con artisan
- [ ] Probé los endpoints de API
- [ ] Verifiqué los logs
- [ ] Todo funciona correctamente ✓

---

**Fecha**: 2025-10-17  
**Versión**: 1.0.0  
**Branch**: `copilot/separate-user-and-role-permissions`  
**Estado**: ✅ Completo
