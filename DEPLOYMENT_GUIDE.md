# Fix para Sistema de Roles y Permisos

## Resumen de Problemas y Soluciones

### Problema Principal
El sistema de permisos presentaba errores 403 ("Acceso no autorizado") porque:

1. **Formato incorrecto del campo `name`**: Los permisos se creaban sin el formato esperado por `PermissionService`
2. **Permisos faltantes**: No se creaban automáticamente permisos 'view' para moduleviews
3. **Búsqueda incorrecta**: UserPermisosController usaba un JOIN complejo en lugar de buscar directamente por `moduleview_id`

### Solución Implementada

#### 1. Auto-generación de campo `name`
Los modelos `Permission` y `Permisos` ahora generan automáticamente el campo `name` en formato `action:view_path`:
- ✓ `view:/dashboard`
- ✓ `create:/usuarios`
- ✓ `edit:/prospectos`

#### 2. Creación automática de permisos
Los permisos faltantes se crean automáticamente en:
- ModulesViewsController al crear nueva vista
- UserPermisosController al asignar permisos a usuario
- RolePermissionController al asignar permisos a rol

#### 3. Búsqueda optimizada
UserPermisosController ahora busca permisos directamente por `moduleview_id` en lugar de JOIN complejo.

## Archivos Modificados

### Controladores (5 archivos)
```
app/Http/Controllers/Api/
├── PermissionController.php          [MODIFICADO] - Formato correcto de nombres
├── UserPermisosController.php        [MODIFICADO] - Auto-creación de permisos
├── RolePermissionController.php      [MODIFICADO] - Auto-creación de permisos
├── UserController.php                [MODIFICADO] - Endpoint de debugging
└── ModulesViewsController.php        [MODIFICADO] - Auto-creación al crear vista
```

### Modelos (2 archivos)
```
app/Models/
├── Permission.php                    [MODIFICADO] - Método boot() para auto-generar name
└── Permisos.php                      [MODIFICADO] - Método boot() para auto-generar name
```

### Nuevos Archivos (4 archivos)
```
app/Console/Commands/
├── SyncModuleViewPermissions.php     [NUEVO] - Comando para sincronizar permisos
└── FixPermissionNames.php            [NUEVO] - Comando para corregir nombres

tests/
└── verify-permissions.php            [NUEVO] - Script de verificación

docs/
└── PERMISSIONS_GUIDE.md              [NUEVO] - Guía completa del sistema
```

### Rutas
```
routes/
└── api.php                           [MODIFICADO] - Nuevo endpoint /users/{id}/permissions
```

## Pasos para Desplegar

### 1. Backup de Base de Datos
```bash
# PostgreSQL
pg_dump -U asm_prod_user -d ASMProd > backup_before_permission_fix_$(date +%Y%m%d_%H%M%S).sql
```

### 2. Actualizar Código
```bash
cd /path/to/ASM_backend-
git checkout copilot/fix-role-permissions-errors
git pull origin copilot/fix-role-permissions-errors
```

### 3. Verificar Estado Actual
```bash
# Verificar permisos actuales
php tests/verify-permissions.php
```

### 4. Corregir Permisos Existentes
```bash
# Ver qué cambios se harían (sin aplicar)
php artisan permissions:fix-names --dry-run

# Aplicar correcciones
php artisan permissions:fix-names
```

### 5. Crear Permisos Faltantes
```bash
# Crear permisos 'view' para todas las moduleviews
php artisan permissions:sync --action=view

# O crear todos los tipos de permisos
php artisan permissions:sync --action=all
```

### 6. Verificar Resultado
```bash
# Ejecutar script de verificación nuevamente
php tests/verify-permissions.php
```

### 7. Reiniciar Servicios (si aplica)
```bash
# Si usas queue workers
php artisan queue:restart

# Si usas cache
php artisan cache:clear
php artisan config:clear
```

## Pruebas Funcionales

### Probar Asignación de Permisos
```bash
# Usando curl o Postman
POST http://tu-dominio/api/userpermissions
Authorization: Bearer {token}
Content-Type: application/json

{
  "user_id": 123,
  "permissions": [1, 2, 3]  // IDs de moduleviews
}
```

Respuesta esperada:
```json
{
  "success": true,
  "message": "Permisos actualizados correctamente.",
  "data": [...]
}
```

### Verificar Permisos de Usuario
```bash
GET http://tu-dominio/api/users/{id}/permissions
Authorization: Bearer {token}
```

## Rollback (Si es Necesario)

### Opción 1: Revertir Código
```bash
git checkout main  # o la rama anterior
```

### Opción 2: Restaurar Base de Datos
```bash
# PostgreSQL
psql -U asm_prod_user -d ASMProd < backup_before_permission_fix_YYYYMMDD_HHMMSS.sql
```

## Monitoreo Post-Despliegue

### 1. Revisar Logs
```bash
tail -f storage/logs/laravel.log | grep -i "permission\|403"
```

### 2. Verificar Errores 403
- Acceder a diferentes vistas del sistema
- Verificar que los usuarios puedan ver las páginas asignadas
- No debe haber errores 403 para usuarios con permisos correctos

### 3. Verificar Auto-Creación
```bash
# Crear una nueva moduleview y verificar que se cree el permiso
# Ver logs para confirmar:
grep "Auto-created view permission" storage/logs/laravel.log
```

## Troubleshooting

### Error: "moduleview_id X no tiene permiso 'view'"
**Solución:**
```bash
php artisan permissions:sync --action=view
```

### Error: Permiso existe pero no funciona
**Diagnóstico:**
```bash
# Ver permisos del usuario
curl http://tu-dominio/api/users/{id}/permissions

# Verificar formato del nombre
php artisan tinker
>>> \App\Models\Permisos::find(X)
```

**Solución:**
```bash
php artisan permissions:fix-names
```

### Error: Usuario tiene permiso pero sigue viendo 403
**Posibles causas:**
1. Cache de permisos (solución: `php artisan cache:clear`)
2. Token expirado (solución: relogin)
3. Middleware usando path incorrecto

## Comandos Útiles de Debugging

```bash
# Ver todos los permisos
php artisan tinker
>>> \App\Models\Permisos::with('moduleView')->get()

# Ver permisos de un usuario específico
>>> $user = \App\Models\User::find(123);
>>> \App\Models\UserPermisos::with('permission')->where('user_id', $user->id)->get()

# Ver permisos de un rol
>>> $role = \App\Models\Role::find(1);
>>> $role->permissions()->with('moduleView')->get()
```

## Contacto y Soporte

Si encuentras problemas durante el despliegue:
1. Revisar logs en `storage/logs/laravel.log`
2. Ejecutar `php tests/verify-permissions.php` para diagnóstico
3. Consultar `docs/PERMISSIONS_GUIDE.md` para más detalles

## Notas Importantes

⚠️ **Antes de desplegar en producción:**
- Hacer backup de la base de datos
- Probar en ambiente de desarrollo/staging primero
- Notificar a usuarios de mantenimiento si es necesario

✓ **Después del despliegue:**
- Verificar que los usuarios puedan acceder a sus vistas asignadas
- Monitorear logs por 24-48 horas
- Documentar cualquier issue encontrado

## Mejoras Futuras (Opcional)

1. **Cache de permisos**: Implementar cache para mejorar rendimiento
2. **Permisos granulares**: Extender sistema para permisos a nivel de campo
3. **Auditoria**: Registrar cambios en asignación de permisos
4. **Interface web**: Panel para gestionar permisos fácilmente

---

**Fecha de última actualización:** $(date +%Y-%m-%d)  
**Versión:** 1.0.0  
**Branch:** copilot/fix-role-permissions-errors
