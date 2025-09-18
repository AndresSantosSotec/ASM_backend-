# Seeders del Sistema Blue Atlas

Este documento describe los seeders creados para el sistema Blue Atlas Backend.

## Seeders Disponibles

### 1. ModulesSeeder
**Archivo:** `ModulesSeeder.php`
**Descripción:** Crea los módulos principales del sistema.

**Módulos incluidos:**
- Prospectos Y Asesores (ID: 2)
- Inscripción (ID: 3)
- Académico (ID: 4)
- Docentes (ID: 5)
- Estudiantes (ID: 6)
- Finanzas y Pagos (ID: 7)
- Administración (ID: 8)
- Seguridad (ID: 9)

### 2. ModuleViewsSeeder
**Archivo:** `ModuleViewsSeeder.php`
**Descripción:** Crea todas las vistas/pantallas asociadas a cada módulo.

**Total de vistas:** 65 vistas distribuidas entre todos los módulos.

### 3. RolesSeeder
**Archivo:** `RolesSeeder.php`
**Descripción:** Crea los roles del sistema.

**Roles incluidos:**
- Administrador (ID: 1)
- Docente (ID: 2)
- Estudiante (ID: 3)
- Administrativo (ID: 4)
- Finanzas (ID: 5)
- Seguridad (ID: 6)
- Asesor (ID: 7)
- Roltest (ID: 9)
- Marketing (ID: 10)
- **SuperAdmin (ID: 11)** - Nuevo rol con acceso completo

### 4. BasicPermissionsSeeder
**Archivo:** `BasicPermissionsSeeder.php`
**Descripción:** Crea permisos básicos del sistema si no existen.

**Permisos incluidos:**
- Dashboard principal
- Gestión de usuarios (ver, crear, editar, eliminar)
- Gestión de roles
- Gestión de permisos
- Configuración del sistema
- Permiso especial de SuperAdmin

### 5. SuperAdminUserSeeder
**Archivo:** `SuperAdminUserSeeder.php`
**Descripción:** Crea el usuario SuperAdmin con todos los permisos del sistema.

**Credenciales del SuperAdmin:**
- **Email:** superadmin@blueatlas.com
- **Password:** SuperAdmin123!
- **Username:** superadmin
- **Carnet:** SUPERADMIN001
- **Rol:** SuperAdmin (ID: 11)
- **Permisos:** Todos los permisos disponibles con scope 'global'

## Cómo Ejecutar los Seeders

### Ejecutar todos los seeders
```bash
php artisan db:seed
```

### Ejecutar seeders específicos
```bash
# Solo módulos
php artisan db:seed --class=ModulesSeeder

# Solo vistas de módulos
php artisan db:seed --class=ModuleViewsSeeder

# Solo roles
php artisan db:seed --class=RolesSeeder

# Solo permisos básicos
php artisan db:seed --class=BasicPermissionsSeeder

# Solo usuario SuperAdmin
php artisan db:seed --class=SuperAdminUserSeeder

# Verificar resultados
php artisan db:seed --class=VerifySeederResults
```

### Ejecutar en orden específico
```bash
php artisan db:seed --class=ModulesSeeder
php artisan db:seed --class=ModuleViewsSeeder
php artisan db:seed --class=RolesSeeder
php artisan db:seed --class=BasicPermissionsSeeder
php artisan db:seed --class=SuperAdminUserSeeder
php artisan db:seed --class=VerifySeederResults
```

## Tablas Afectadas

Los seeders insertan/actualizan datos en las siguientes tablas:

1. **modules** - Módulos del sistema
2. **moduleviews** - Vistas/pantallas de cada módulo
3. **roles** - Roles de usuario
4. **permissions** - Permisos básicos del sistema
5. **users** - Usuario SuperAdmin
6. **userroles** - Asignación de rol SuperAdmin al usuario
7. **userpermissions** - Asignación de todos los permisos al usuario SuperAdmin

## Notas Importantes

1. **Seguridad:** El usuario SuperAdmin se crea con una contraseña por defecto. Se recomienda cambiarla después del primer login.

2. **Permisos:** El SuperAdmin obtiene automáticamente todos los permisos habilitados (`is_enabled = true`) con scope 'global'.

3. **IDs Fijos:** Los seeders usan IDs específicos para mantener consistencia con los datos existentes.

4. **UpdateOrCreate:** Todos los seeders usan `updateOrCreate()` para evitar duplicados y permitir re-ejecución.

## Verificación Post-Seeding

Después de ejecutar los seeders, puedes verificar que todo se creó correctamente:

```sql
-- Verificar módulos
SELECT * FROM modules ORDER BY id;

-- Verificar vistas de módulos
SELECT * FROM moduleviews ORDER BY module_id, order_num;

-- Verificar roles
SELECT * FROM roles ORDER BY id;

-- Verificar usuario SuperAdmin
SELECT * FROM users WHERE email = 'superadmin@blueatlas.com';

-- Verificar asignación de rol
SELECT ur.*, r.name as role_name 
FROM userroles ur 
JOIN roles r ON ur.role_id = r.id 
JOIN users u ON ur.user_id = u.id 
WHERE u.email = 'superadmin@blueatlas.com';

-- Verificar permisos asignados
SELECT COUNT(*) as total_permissions 
FROM userpermissions up 
JOIN users u ON up.user_id = u.id 
WHERE u.email = 'superadmin@blueatlas.com';
```

## Troubleshooting

### Error: "Class not found"
Asegúrate de que los archivos de seeder estén en la carpeta correcta y ejecuta:
```bash
composer dump-autoload
```

### Error: "Foreign key constraint"
Ejecuta los seeders en el orden correcto:
1. ModulesSeeder (primero)
2. ModuleViewsSeeder (segundo)
3. RolesSeeder (tercero)
4. SuperAdminUserSeeder (último)

### Error: "Permission not found"
Asegúrate de que la tabla `permissions` tenga datos antes de ejecutar `SuperAdminUserSeeder`.
