# Guía de Migración - Separación de Permisos por Usuario y Rol

## 🎯 Objetivo

Esta guía explica cómo aplicar las migraciones que separan completamente el sistema de permisos por usuario del sistema de permisos por rol.

## 📋 Resumen de Cambios

### Antes (Sistema Mezclado)
- Una sola tabla `permissions` compartida entre roles y usuarios
- Modelo `Permisos` apuntaba a tabla `permissions`
- Modelo `Permission` apuntaba a tabla `permissions`
- Confusión y mezcla entre ambos sistemas

### Después (Sistemas Separados)
- Tabla `permissions` - Solo para permisos de **ROL**
- Tabla `permisos` - Solo para permisos de **USUARIO**
- Modelo `Permission` → tabla `permissions` (roles)
- Modelo `Permisos` → tabla `permisos` (usuarios)
- Separación completa y clara

## 🚀 Pasos de Migración

### 1. Backup de Base de Datos

**⚠️ CRÍTICO**: Siempre hacer backup antes de migrar.

```bash
# PostgreSQL
pg_dump -U asm_prod_user -d ASMProd > backup_antes_migracion_$(date +%Y%m%d_%H%M%S).sql

# MySQL (si aplica)
mysqldump -u usuario -p base_de_datos > backup_antes_migracion_$(date +%Y%m%d_%H%M%S).sql
```

### 2. Verificar Estado Actual

Verifica cuántos permisos de usuario existen actualmente:

```sql
-- Ver cuántos permisos de usuario existen
SELECT COUNT(*) as total_user_permissions 
FROM userpermissions;

-- Ver permisos únicos referenciados por usuarios
SELECT COUNT(DISTINCT permission_id) as unique_permissions_used
FROM userpermissions;

-- Ver si existen permisos con moduleview_id en permissions
SELECT COUNT(*) as permissions_with_moduleview
FROM permissions 
WHERE moduleview_id IS NOT NULL;
```

### 3. Aplicar las Migraciones

```bash
# Ejecutar migraciones
php artisan migrate

# Si hay errores, revisar los logs
tail -f storage/logs/laravel.log
```

**Las migraciones aplicadas son:**

1. **2025_10_17_000000_create_permisos_table.php**
   - Crea la nueva tabla `permisos`
   - Estructura idéntica a `permissions` pero separada
   - Incluye foreign keys a `moduleviews`

2. **2025_10_17_000001_migrate_user_permissions_to_permisos.php**
   - Copia permisos de `permissions` a `permisos` solo si están siendo usados por usuarios
   - Actualiza `userpermissions` para apuntar a los nuevos IDs en `permisos`
   - Mantiene un mapeo entre IDs antiguos y nuevos

### 4. Verificar Migración Exitosa

```sql
-- Verificar que la tabla permisos fue creada
SELECT COUNT(*) FROM permisos;

-- Verificar que userpermissions ahora apunta a permisos
SELECT up.id, up.user_id, up.permission_id, p.name, p.moduleview_id
FROM userpermissions up
JOIN permisos p ON p.id = up.permission_id
LIMIT 10;

-- Verificar que no hay referencias rotas
SELECT COUNT(*) as broken_references
FROM userpermissions up
LEFT JOIN permisos p ON p.id = up.permission_id
WHERE p.id IS NULL;
```

### 5. Sincronizar Permisos con Vistas

Después de la migración, sincroniza los permisos para todas las vistas:

```bash
# Crear permisos 'view' para todas las moduleviews
php artisan permissions:sync --action=view

# O crear todos los tipos de permisos
php artisan permissions:sync --action=all
```

### 6. Corregir Nombres de Permisos (si es necesario)

```bash
# Revisar sin cambiar (dry-run)
php artisan permissions:fix-names --dry-run

# Aplicar correcciones
php artisan permissions:fix-names
```

## 🔍 Verificación Post-Migración

### Test Manual 1: Verificar Permisos de Usuario

```bash
curl -X GET "http://localhost/api/userpermissions?user_id=1" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

Debe devolver:
```json
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
          "menu": "...",
          "submenu": "..."
        }
      }
    }
  ]
}
```

### Test Manual 2: Asignar Permisos

```bash
curl -X POST "http://localhost/api/userpermissions" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 1,
    "permissions": [1, 2, 3]
  }'
```

### Test Manual 3: Verificar Permisos de Rol

```bash
curl -X GET "http://localhost/api/roles/1/permissions" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

Debe devolver lista de permisos del rol sin errores.

## ⚠️ Problemas Comunes y Soluciones

### Error: "Column moduleview_id does not exist"

**Causa**: La migración no se aplicó correctamente o hay caché.

**Solución**:
```bash
php artisan config:clear
php artisan cache:clear
php artisan migrate:status
# Si la migración no aparece, aplicarla manualmente
php artisan migrate --path=database/migrations/2025_10_17_000000_create_permisos_table.php
```

### Error: "Duplicate key value violates unique constraint"

**Causa**: Hay permisos duplicados con el mismo nombre.

**Solución**:
```sql
-- Encontrar duplicados
SELECT name, COUNT(*) 
FROM permisos 
GROUP BY name 
HAVING COUNT(*) > 1;

-- Eliminar duplicados (conservar el de menor ID)
DELETE FROM permisos 
WHERE id NOT IN (
  SELECT MIN(id) 
  FROM permisos 
  GROUP BY name
);
```

### Error: "Foreign key constraint fails"

**Causa**: Referencias a moduleviews que no existen.

**Solución**:
```sql
-- Encontrar permisos huérfanos
SELECT p.id, p.name, p.moduleview_id
FROM permisos p
LEFT JOIN moduleviews mv ON mv.id = p.moduleview_id
WHERE p.moduleview_id IS NOT NULL 
AND mv.id IS NULL;

-- Eliminar permisos huérfanos
DELETE FROM permisos
WHERE moduleview_id IS NOT NULL
AND moduleview_id NOT IN (SELECT id FROM moduleviews);
```

## 🔄 Rollback (Si es Necesario)

Si necesitas revertir la migración:

```bash
# Revertir última migración
php artisan migrate:rollback --step=2

# Restaurar desde backup
psql -U asm_prod_user -d ASMProd < backup_antes_migracion_FECHA.sql
```

**⚠️ Nota**: El rollback de la migración de datos no está implementado porque requeriría mantener el mapeo de IDs. Si necesitas rollback, restaura desde el backup.

## 📊 Estadísticas Esperadas

Después de la migración, deberías ver:

```sql
-- Cantidad de permisos de usuario
SELECT COUNT(*) as total_permisos_usuario FROM permisos;

-- Cantidad de permisos de rol
SELECT COUNT(*) as total_permisos_rol FROM permissions;

-- Usuarios con permisos
SELECT COUNT(DISTINCT user_id) as usuarios_con_permisos 
FROM userpermissions;

-- Vistas con permisos
SELECT COUNT(DISTINCT moduleview_id) as vistas_con_permisos 
FROM permisos 
WHERE moduleview_id IS NOT NULL;
```

## 📝 Checklist de Migración

- [ ] Backup de base de datos realizado
- [ ] Estado actual verificado (permisos existentes contados)
- [ ] Migraciones aplicadas sin errores
- [ ] Tabla `permisos` creada correctamente
- [ ] Datos migrados de `permissions` a `permisos`
- [ ] `userpermissions` actualizados con nuevos IDs
- [ ] Verificación: No hay referencias rotas
- [ ] Permisos sincronizados con `php artisan permissions:sync`
- [ ] Nombres corregidos con `php artisan permissions:fix-names`
- [ ] Tests manuales ejecutados
- [ ] Usuarios pueden ver sus permisos
- [ ] Asignación de permisos funciona
- [ ] Permisos de rol no afectados
- [ ] Cache limpiado
- [ ] Documentación actualizada

## 🎓 Próximos Pasos

Una vez completada la migración:

1. **Actualizar Frontend**: Asegúrate que el frontend sigue usando los mismos endpoints
2. **Monitorear Logs**: Revisa logs por 24-48 horas para detectar problemas
3. **Comunicar Cambios**: Informa al equipo sobre la nueva estructura
4. **Actualizar Documentación**: Añade esta separación a la documentación del proyecto

## 📚 Referencias

- [SEPARACION_PERMISOS.md](SEPARACION_PERMISOS.md) - Documentación completa de la arquitectura
- [README_PERMISSIONS_FIX.md](README_PERMISSIONS_FIX.md) - Fix anterior de permisos
- Migraciones: `database/migrations/2025_10_17_*`

## 🤝 Soporte

Si encuentras problemas:

1. Revisa los logs: `tail -f storage/logs/laravel.log`
2. Verifica las consultas SQL ejecutadas
3. Consulta la documentación en `SEPARACION_PERMISOS.md`
4. Contacta al equipo de desarrollo

---

**Última actualización**: 2025-10-17  
**Versión**: 1.0.0  
**Estado**: ✅ Listo para Aplicar
