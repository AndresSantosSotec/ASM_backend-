# Sistema de Asignación Automática de Permisos por Rol

## Descripción

Este sistema automatiza la asignación de permisos a usuarios basándose en sus roles asignados. Implementa la lógica de negocio específica para cada rol según los módulos del sistema.

## Estructura Implementada

### 1. Tabla de Configuración (`role_permissions_config`)

```sql
CREATE TABLE role_permissions_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    action ENUM('view', 'create', 'edit', 'delete', 'export') DEFAULT 'view',
    scope ENUM('global', 'group', 'self') DEFAULT 'self',
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE KEY unique_role_permission_action (role_id, permission_id, action)
);
```

### 2. Servicio de Gestión (`RolePermissionService`)

Proporciona métodos para:
- Asignar permisos automáticamente a usuarios
- Actualizar permisos cuando cambia el rol
- Configurar permisos para roles
- Operaciones masivas por rol

### 3. Mapeo de Roles y Permisos

| Rol ID | Nombre | Módulo | Permisos |
|--------|--------|--------|----------|
| 1 | Administrador | Todos | Todos los permisos |
| 2 | Docente | Portal Docente (5) | 34-43 |
| 3 | Estudiante | Estudiante (6) | 44-51, 81 |
| 4 | Administrativo | Administrativo (8) | 59-64 |
| 5 | Finanzas | Finanzas (7) | 52-58 |
| 6 | Seguridad | Seguridad (9) | 65, 67-69, 82-85 |
| 7 | Asesor | Prospectos (2) | 1-5, 8-9, 12 |

## Uso

### Automático

El sistema funciona automáticamente cuando:

1. **Se crea un nuevo usuario** - Los permisos se asignan según su rol
2. **Se actualiza el rol de un usuario** - Los permisos se actualizan automáticamente

### Manual

#### Comandos de Consola

```bash
# Sincronizar permisos para todos los usuarios
php artisan permissions:sync-roles

# Sincronizar permisos para un rol específico
php artisan permissions:sync-roles --role=2

# Sincronizar permisos para un usuario específico
php artisan permissions:sync-roles --user=123

# Ver qué se haría sin ejecutar cambios
php artisan permissions:sync-roles --dry-run
```

#### Servicio Programático

```php
use App\Services\RolePermissionService;

$service = new RolePermissionService();

// Asignar permisos a un usuario
$service->assignPermissionsToUser($user);

// Actualizar permisos por cambio de rol
$service->updateUserPermissionsOnRoleChange($user, $oldRoleId, $newRoleId);

// Operación masiva por rol
$service->bulkAssignPermissionsByRole($roleId);
```

### Ejecutar Migraciones y Seeders

```bash
# Ejecutar la migración
php artisan migrate

# Poblar la configuración de permisos
php artisan db:seed --class=RolePermissionsConfigSeeder
```

## API Changes

### UserController::store()

**Respuesta Exitosa:**
```json
{
    "user": { /* datos del usuario */ },
    "permissions_assigned": true,
    "message": "Usuario creado exitosamente con permisos asignados"
}
```

**Respuesta con Error en Permisos:**
```json
{
    "user": { /* datos del usuario */ },
    "permissions_assigned": false,
    "message": "Usuario creado exitosamente pero falló la asignación de permisos"
}
```

### UserController::update()

**Respuesta con Cambio de Rol:**
```json
{
    "user": { /* datos del usuario */ },
    "role_changed": true,
    "permissions_updated": true,
    "message": "Usuario actualizado exitosamente con cambio de rol y permisos actualizados"
}
```

## Características Técnicas

### Transacciones de Base de Datos
- Todas las operaciones usan transacciones para garantizar consistencia
- Rollback automático en caso de error

### Manejo de Errores
- Logging detallado de errores y operaciones
- Respuestas JSON apropiadas con códigos de estado HTTP

### Rendimiento
- Operaciones masivas optimizadas con chunks
- Índices en la tabla de configuración para consultas rápidas

### Compatibilidad
- Mantiene la funcionalidad existente del UserController
- No afecta la estructura actual de permisos
- Compatible con el sistema de módulos existente

## Testing

Se incluyen tests unitarios para validar:
- Asignación correcta de permisos por rol
- Actualización de permisos en cambio de rol
- Mapeo correcto de permisos predeterminados

```bash
# Ejecutar tests
php artisan test tests/Unit/RolePermissionServiceTest.php
```

## Logs

El sistema registra:
- Asignaciones exitosas de permisos
- Errores en asignación de permisos
- Cambios de rol y actualizaciones
- Operaciones masivas

Ubicación: `storage/logs/laravel.log`