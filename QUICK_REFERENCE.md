# 🚀 Tarjeta de Referencia Rápida - Sistema de Permisos

## 📋 Comandos Esenciales

### Diagnóstico
```bash
php tests/verify-permissions.php
```

### Corrección
```bash
# Ver cambios sin aplicar
php artisan permissions:fix-names --dry-run

# Aplicar correcciones
php artisan permissions:fix-names
```

### Sincronización
```bash
# Solo permisos 'view'
php artisan permissions:sync

# Todos los permisos
php artisan permissions:sync --action=all

# Forzar actualización
php artisan permissions:sync --action=all --force
```

### Debugging API
```bash
# Ver permisos de usuario
GET /api/users/{id}/permissions
Authorization: Bearer {token}
```

---

## 🔍 Diagnóstico Rápido

### ¿Usuario ve 403?
```bash
# 1. Verificar permisos del usuario
curl GET http://api/users/123/permissions

# 2. Verificar formato de permisos
php artisan tinker
>>> \App\Models\Permisos::where('moduleview_id', X)->get()
```

### ¿Permiso faltante?
```bash
# Crear automáticamente
php artisan permissions:sync --action=view
```

### ¿Nombre incorrecto?
```bash
# Corregir
php artisan permissions:fix-names
```

---

## 📝 Formato Correcto

### Nombres de Permisos
```
✅ CORRECTO:
view:/dashboard
create:/usuarios
edit:/prospectos
delete:/programas
export:/reportes

❌ INCORRECTO:
view-dashboard
/dashboard-view
dashboard:view
cualquier otro formato
```

---

## 🔧 Asignación de Permisos

### A Usuario
```bash
POST /api/userpermissions
{
  "user_id": 123,
  "permissions": [1, 2, 3]  // IDs de moduleviews
}
```

### A Rol
```bash
PUT /api/roles/{role_id}/permissions
{
  "permissions": [
    {
      "moduleview_id": 1,
      "actions": ["view", "create"]
    }
  ]
}
```

---

## 🚨 Solución de Problemas

| Problema | Comando |
|----------|---------|
| Error 403 | `php artisan permissions:fix-names` |
| Permiso faltante | `php artisan permissions:sync` |
| Nombre incorrecto | `php artisan permissions:fix-names` |
| Verificar sistema | `php tests/verify-permissions.php` |

---

## 📊 Verificación Post-Despliegue

```bash
# 1. Verificar permisos
php tests/verify-permissions.php

# 2. Ver logs
tail -f storage/logs/laravel.log | grep -i permission

# 3. Probar acceso
# Acceder a diferentes vistas con usuarios de prueba

# 4. Verificar auto-creación
grep "Auto-created" storage/logs/laravel.log
```

---

## 🔄 Flujo de Despliegue

```bash
# 1. Backup
pg_dump -U user -d db > backup.sql

# 2. Código
git pull origin copilot/fix-role-permissions-errors

# 3. Verificar
php tests/verify-permissions.php

# 4. Corregir
php artisan permissions:fix-names

# 5. Sincronizar
php artisan permissions:sync --action=all

# 6. Verificar
php tests/verify-permissions.php

# 7. Limpiar cache
php artisan cache:clear
```

---

## 📚 Documentación

- `RESUMEN_CAMBIOS.md` - Explicación completa
- `DEPLOYMENT_GUIDE.md` - Guía de despliegue
- `docs/PERMISSIONS_GUIDE.md` - Manual de uso
- `ESTADISTICAS.md` - Métricas

---

## ⚡ Atajos

```bash
# Alias útiles (agregar a .bashrc o .zshrc)
alias perm-verify='php tests/verify-permissions.php'
alias perm-fix='php artisan permissions:fix-names'
alias perm-sync='php artisan permissions:sync --action=all'
alias perm-logs='tail -f storage/logs/laravel.log | grep -i permission'
```

---

## 🎯 Recordatorios

✓ Siempre hacer backup antes de cambios  
✓ Probar en staging primero  
✓ Verificar después de desplegar  
✓ Monitorear logs por 24-48h  
✓ Documentar cualquier issue  

---

**Versión:** 1.0  
**Fecha:** $(date +%Y-%m-%d)  
**Branch:** copilot/fix-role-permissions-errors

---

## 🆘 Ayuda Rápida

```bash
# ¿Olvidaste un comando?
php artisan list | grep permission

# ¿Necesitas ayuda?
php artisan permissions:sync --help
php artisan permissions:fix-names --help

# ¿Necesitas más info?
cat RESUMEN_CAMBIOS.md
cat DEPLOYMENT_GUIDE.md
```
