# Sistema de Permisos - Separación entre Permisos por Usuario y Permisos por Rol

## 📋 Resumen

Este documento describe la arquitectura completamente separada de dos sistemas de permisos independientes en el backend ASM.

## 🔒 Dos Sistemas Independientes

### 1. Permisos por Rol (NO TOCAR)

**Propósito**: Define qué acciones puede realizar un rol sobre cada módulo/vista.

**Tablas**:
- `roles` - Define los roles del sistema
- `permissions` - Almacena permisos de acción (view, create, edit, delete, export)
- `rolepermissions` - Tabla pivote que relaciona roles con permissions
- `roleusers` - Tabla pivote que relaciona usuarios con roles

**Modelos**:
- `App\Models\Role`
- `App\Models\Permission` (⚠️ con doble "s")
- `App\Models\RolePermissions` (si existe)
- `App\Models\RoleUsers` (si existe)

**Controladores**:
- `App\Http\Controllers\Api\RolePermissionController` - Gestiona permisos de roles

**Relaciones**:
```
Role (N) ←→ (N) Permission (via rolepermissions)
Permission (N) ←→ (1) ModuleView
```

**⚠️ IMPORTANTE**: Esta parte del sistema NO debe modificarse ni mezclarse con permisos de usuario.

---

### 2. Permisos por Usuario (Sistema Actual)

**Propósito**: Define qué vistas específicas puede acceder cada usuario individualmente.

**Tablas**:
- `users` - Usuarios del sistema
- `permisos` - Almacena permisos individuales (principalmente acción 'view')
- `userpermissions` - Tabla pivote que relaciona usuarios con permisos
- `modules` - Agrupa las vistas
- `moduleviews` - Define las vistas/pantallas del sistema

**Modelos**:
- `App\Models\UserPermisos` - Representa la relación user-permission
- `App\Models\Permisos` (⚠️ con una sola "s") - Representa un permiso individual
- `App\Models\ModulesViews` - Representa las vistas del sistema
- `App\Models\Modules` - Representa los módulos

**Controladores**:
- `App\Http\Controllers\Api\UserPermisosController` - Gestiona permisos por usuario
- `App\Http\Controllers\Api\PermissionController` - Crea permisos en tabla permisos

**Comandos**:
- `php artisan permissions:sync` - Sincroniza permisos con moduleviews
- `php artisan permissions:fix-names` - Corrige nombres de permisos

**Relaciones**:
```
User (1) ←→ (N) UserPermisos
UserPermisos (N) ←→ (1) Permisos
Permisos (N) ←→ (1) ModulesViews
ModulesViews (N) ←→ (1) Modules
```

---

## 📊 Comparación de Tablas

| Característica | Permisos por Rol | Permisos por Usuario |
|---------------|------------------|----------------------|
| Tabla principal | `permissions` | `permisos` |
| Tabla pivote | `rolepermissions` | `userpermissions` |
| Modelo principal | `Permission` | `Permisos` |
| Acciones soportadas | view, create, edit, delete, export | view (principalmente) |
| Scope | Rol completo | Usuario individual |
| Modificable | ❌ NO | ✅ SÍ |

---

## 🔗 Esquema de Base de Datos

### Permisos por Rol (permissions)
```sql
CREATE TABLE permissions (
    id SERIAL PRIMARY KEY,
    action VARCHAR CHECK (action IN ('view','create','edit','delete','export')),
    moduleview_id INTEGER REFERENCES moduleviews(id),
    name VARCHAR UNIQUE,
    description VARCHAR,
    is_enabled BOOLEAN DEFAULT true,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE TABLE rolepermissions (
    id SERIAL PRIMARY KEY,
    role_id INTEGER REFERENCES roles(id),
    permission_id INTEGER REFERENCES permissions(id),
    scope VARCHAR CHECK (scope IN ('global','group','self')),
    assigned_at TIMESTAMP
);
```

### Permisos por Usuario (permisos)
```sql
CREATE TABLE permisos (
    id SERIAL PRIMARY KEY,
    moduleview_id INTEGER REFERENCES moduleviews(id),
    action VARCHAR DEFAULT 'view',
    name VARCHAR UNIQUE,
    description VARCHAR,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE TABLE userpermissions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    permission_id INTEGER REFERENCES permisos(id),
    assigned_at TIMESTAMP,
    scope VARCHAR CHECK (scope IN ('global','group','self'))
);
```

---

## 🚀 API Endpoints

### Permisos por Rol (NO MODIFICAR)
```
GET    /api/roles/{role}/permissions    - Listar permisos de un rol
PUT    /api/roles/{role}/permissions    - Actualizar permisos de un rol
```

### Permisos por Usuario
```
GET    /api/userpermissions?user_id={id}  - Listar permisos de un usuario
POST   /api/userpermissions                - Asignar permisos a un usuario
PUT    /api/userpermissions/{id}           - Actualizar un permiso
DELETE /api/userpermissions/{id}           - Eliminar un permiso
POST   /api/permissions                    - Crear un nuevo permiso
```

---

## 📝 Ejemplos de Uso

### Obtener permisos de un usuario
```bash
GET /api/userpermissions?user_id=3

Response:
{
  "success": true,
  "data": [
    {
      "id": 1,
      "permission": {
        "id": 15,
        "action": "view",
        "module_view": {
          "id": 10,
          "menu": "Gestión de Pagos",
          "submenu": "Conciliación Bancaria",
          "module": {
            "id": 3,
            "name": "Finanzas"
          }
        }
      }
    }
  ]
}
```

### Asignar permisos a un usuario
```bash
POST /api/userpermissions

Body:
{
  "user_id": 3,
  "permissions": [10, 11, 12]  // IDs de moduleviews
}

Response:
{
  "success": true,
  "message": "Permisos actualizados correctamente.",
  "data": [...]
}
```

---

## ⚙️ Migraciones Aplicadas

1. **2025_10_17_000000_create_permisos_table.php**
   - Crea la tabla `permisos` para permisos de usuario
   - Incluye foreign key a `moduleviews`

2. **2025_10_17_000001_migrate_user_permissions_to_permisos.php**
   - Migra datos existentes de `permissions` a `permisos`
   - Actualiza referencias en `userpermissions`

---

## 🔍 Verificación de Separación

### Checklist
- [x] Tabla `permisos` creada y separada de `permissions`
- [x] Modelo `Permisos` usa tabla `permisos`
- [x] Modelo `Permission` usa tabla `permissions`
- [x] `Role.permissions()` usa modelo `Permission`
- [x] `UserPermisos.permission()` usa modelo `Permisos`
- [x] `RolePermissionController` usa `Permission`
- [x] `UserPermisosController` usa `Permisos`
- [x] Migraciones para separar datos

### Comandos de Verificación
```bash
# Verificar que permisos tabla existe
php artisan tinker
>>> DB::select('SELECT COUNT(*) FROM permisos');

# Verificar que permissions tabla existe
>>> DB::select('SELECT COUNT(*) FROM permissions');

# Verificar relaciones
>>> App\Models\Permisos::first()->moduleView;
>>> App\Models\Permission::first()->moduleview;
```

---

## 🚨 Reglas Críticas

### ❌ PROHIBIDO
1. Usar `Permission` (doble s) en lógica de permisos de usuario
2. Usar `Permisos` (una s) en lógica de permisos de rol
3. Mezclar consultas entre `permissions` y `permisos`
4. Modificar tabla `permissions` o `rolepermissions`
5. Hacer JOIN entre ambos sistemas

### ✅ PERMITIDO
1. Usar `Permisos` para permisos de usuario
2. Usar `Permission` para permisos de rol
3. Mantener ambos sistemas completamente independientes
4. Cada sistema tiene sus propios controladores y servicios

---

## 📚 Referencias

- **Código**: Ver modelos en `app/Models/`
- **Controladores**: Ver en `app/Http/Controllers/Api/`
- **Migraciones**: Ver en `database/migrations/`
- **Tests**: Ver en `tests/Feature/`

---

## 🤝 Contribuciones

Si necesitas modificar el sistema de permisos:

1. **Permisos de Usuario**: Modifica `Permisos`, `UserPermisos`, `UserPermisosController`
2. **Permisos de Rol**: ⚠️ Consultar con el equipo antes de modificar

---

**Última actualización**: 2025-10-17
**Versión**: 1.0.0
**Estado**: ✅ Separación Completa Implementada
