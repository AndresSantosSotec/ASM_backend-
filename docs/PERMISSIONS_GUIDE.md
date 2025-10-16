# Sistema de Roles y Permisos - Guía de Solución

## Problemas Resueltos

### 1. Error 403 - Acceso no autorizado
**Causa**: Los permisos no se estaban creando correctamente con el formato esperado por `PermissionService`.

**Solución**: 
- El campo `name` en la tabla `permissions` ahora se genera automáticamente en formato `action:view_path`
- Ejemplo: `view:/dashboard`, `create:/prospectos`, `edit:/usuarios`

### 2. Error: "moduleview_id X no tiene permiso 'view' configurado"
**Causa**: Los permisos no existían en la base de datos para las vistas seleccionadas.

**Solución**: 
- `UserPermisosController` ahora crea automáticamente permisos faltantes
- `RolePermissionController` también crea permisos automáticamente al asignar a roles

## Comandos Artisan Nuevos

### Sincronizar Permisos para ModuleViews
```bash
# Crear permisos 'view' para todas las moduleviews
php artisan permissions:sync

# Crear todos los tipos de permisos (view, create, edit, delete, export)
php artisan permissions:sync --action=all

# Forzar actualización de permisos existentes
php artisan permissions:sync --action=all --force
```

### Corregir Nombres de Permisos Existentes
```bash
# Ver qué permisos necesitan corrección (sin hacer cambios)
php artisan permissions:fix-names --dry-run

# Aplicar las correcciones
php artisan permissions:fix-names
```

## API Endpoints Nuevos

### Obtener Permisos de un Usuario
```
GET /api/users/{id}/permissions
```

**Respuesta:**
```json
{
  "success": true,
  "user": {
    "id": 123,
    "username": "usuario",
    "email": "usuario@example.com",
    "role": "Administrador"
  },
  "permissions": [
    {
      "id": 1,
      "name": "view:/dashboard",
      "action": "view",
      "moduleview_id": 1,
      "view_path": "/dashboard",
      "menu": "Inicio",
      "submenu": "Dashboard",
      "source": "direct",
      "scope": "self"
    }
  ],
  "total": 1
}
```

## Estructura de Permisos

### Tabla: permissions
- `id`: ID único del permiso
- `moduleview_id`: ID de la vista del módulo
- `action`: Tipo de acción (view, create, edit, delete, export)
- `name`: Nombre único en formato `action:view_path` (generado automáticamente)
- `description`: Descripción opcional

### Relaciones
- Un `permission` pertenece a un `moduleview`
- Un `permission` puede estar asignado a múltiples `users` (tabla pivot: `userpermissions`)
- Un `permission` puede estar asignado a múltiples `roles` (tabla pivot: `rolepermissions`)

## Flujo de Asignación de Permisos

### 1. Asignar Permisos a Usuario
```
POST /api/userpermissions
{
  "user_id": 123,
  "permissions": [1, 2, 3] // IDs de moduleviews
}
```

**Comportamiento:**
1. Valida que el usuario exista
2. Valida que las moduleviews existan
3. Busca permisos con action='view' para cada moduleview
4. Si falta algún permiso, lo crea automáticamente
5. Limpia permisos previos del usuario
6. Asigna los nuevos permisos

### 2. Asignar Permisos a Rol
```
PUT /api/roles/{role_id}/permissions
{
  "permissions": [
    {
      "moduleview_id": 1,
      "actions": ["view", "create", "edit"]
    },
    {
      "moduleview_id": 2,
      "actions": ["view"]
    }
  ]
}
```

**Comportamiento:**
1. Valida que el rol exista
2. Para cada moduleview y acción:
   - Busca el permiso existente
   - Si no existe, lo crea automáticamente
3. Sincroniza permisos con el rol (elimina anteriores, asigna nuevos)

## Verificación de Permisos

### Middleware: EnsureHasPermission
```php
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth:sanctum', 'permission:view,/dashboard']);
```

**Funcionamiento:**
1. Obtiene el usuario autenticado
2. Construye el nombre del permiso: `action:viewPath`
3. Verifica si el usuario tiene el permiso (directo o por rol)
4. Permite o deniega el acceso (403)

## Solución a Problemas Comunes

### Problema: Usuario no puede ver una vista aunque tiene el rol correcto
**Diagnóstico:**
```bash
# 1. Verificar permisos del usuario
curl -X GET "http://localhost:8000/api/users/123/permissions" \
  -H "Authorization: Bearer YOUR_TOKEN"

# 2. Verificar que existan los permisos para la moduleview
php artisan tinker
>>> \App\Models\Permisos::where('moduleview_id', 1)->get();
```

**Solución:**
```bash
# Crear permisos faltantes
php artisan permissions:sync --action=view

# O asignar manualmente
POST /api/userpermissions
{
  "user_id": 123,
  "permissions": [1, 2, 3]
}
```

### Problema: Error al crear permiso manualmente
**Causa**: El campo `name` no se proporciona o tiene formato incorrecto

**Solución**: Usar el endpoint correcto que genera el nombre automáticamente:
```
POST /api/permissions
{
  "moduleview_id": 1,
  "action": "view",
  "description": "Permiso para ver dashboard"
}
```

## Mejores Prácticas

1. **Siempre usar el comando de sincronización después de agregar nuevas moduleviews**
   ```bash
   php artisan permissions:sync --action=all
   ```

2. **Verificar permisos de usuario antes de reportar problemas**
   ```
   GET /api/users/{id}/permissions
   ```

3. **No modificar directamente el campo `name` en la base de datos**
   - El campo se genera automáticamente
   - Formato: `action:view_path`

4. **Usar el endpoint de asignación de permisos por moduleview_id, no por permission_id**
   ```json
   {
     "user_id": 123,
     "permissions": [1, 2, 3] // moduleview_ids
   }
   ```

## Migración de Datos Existentes

Si tienes permisos existentes con nombres incorrectos:

```bash
# 1. Verificar el estado actual del sistema
php tests/verify-permissions.php

# 2. Corregir nombres de permisos existentes
php artisan permissions:fix-names --dry-run  # Ver cambios primero
php artisan permissions:fix-names            # Aplicar cambios

# 3. Crear permisos faltantes
php artisan permissions:sync --action=all

# 4. Verificar que todo esté correcto
php tests/verify-permissions.php
```

## Debugging

### Ver todos los permisos en el sistema
```bash
php artisan tinker
>>> \App\Models\Permisos::with('moduleView')->get();
```

### Ver permisos de un usuario específico
```bash
php artisan tinker
>>> $user = \App\Models\User::find(123);
>>> \App\Models\UserPermisos::with('permission')->where('user_id', $user->id)->get();
```

### Ver permisos de un rol
```bash
php artisan tinker
>>> $role = \App\Models\Role::find(1);
>>> $role->permissions()->with('moduleView')->get();
```

## Logs Útiles

Los siguientes eventos se registran en `storage/logs/laravel.log`:

- Creación automática de permisos
- Asignación de permisos a usuarios
- Errores de validación
- Permisos faltantes

Buscar por:
```
grep "UserPermisos.store" storage/logs/laravel.log
grep "missing view permissions" storage/logs/laravel.log
grep "Auto-created" storage/logs/laravel.log
```
