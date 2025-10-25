# 🔧 Fix Frontend: Permisos de Usuario

**Fecha:** 2025-10-24  
**Problema:** El frontend no cargaba correctamente los permisos de usuario porque buscaba `permission.module_view.id` en la estructura de respuesta.

---

## 📋 Problema Detectado

### Código Anterior (INCORRECTO)
```typescript
const moduleViewIds = rows
  .map((row) => {
    return (
      row?.permission?.module_view?.id ??
      row?.permission?.moduleView?.id ??
      row?.permission?.module_view_id ??
      null
    );
  })
  .filter((id: any) => typeof id === "number");
```

**Error:** Intentaba acceder a una relación `permission` que ya **no existe** en la tabla `userpermissions`.

---

## ✅ Solución Implementada

### Código Nuevo (CORRECTO)
```typescript
const moduleViewIds = rows
  .map((row) => row?.moduleview_id)
  .filter((id: any) => typeof id === "number");

console.log('📊 Permisos cargados:', moduleViewIds); // Debug
```

**Razón:** Después de la migración, `userpermissions` tiene **directamente** la columna `moduleview_id`.

---

## 📊 Estructura de Respuesta del Backend

### GET `/api/userpermissions?user_id=10`

**Response:**
```json
{
  "success": true,
  "message": "Permisos cargados correctamente.",
  "data": [
    {
      "id": 1,
      "user_id": 10,
      "moduleview_id": 1,        // ✅ Campo directo
      "assigned_at": "2025-10-24 12:00:00",
      "scope": "self",
      "module_view": {            // ✅ Relación eager loading (opcional)
        "id": 1,
        "module_id": 1,
        "menu": "Dashboard",
        "submenu": null,
        "view_path": "/dashboard",
        "status": true,
        "order_num": 1,
        "module": {
          "id": 1,
          "name": "Administrativo",
          "description": "Módulo administrativo",
          "status": true
        }
      }
    },
    // ... más permisos
  ]
}
```

---

## 🔄 Cambios en el Frontend

### 1. Extracción de IDs (Líneas 130-138)

**Antes:**
```typescript
const moduleViewIds = rows
  .map((row) => {
    return (
      row?.permission?.module_view?.id ??     // ❌ Ya no existe
      row?.permission?.moduleView?.id ??      // ❌ Ya no existe
      row?.permission?.module_view_id ??      // ❌ Ya no existe
      null
    );
  })
```

**Después:**
```typescript
const moduleViewIds = rows
  .map((row) => row?.moduleview_id)           // ✅ Acceso directo
  .filter((id: any) => typeof id === "number");

console.log('📊 Permisos cargados:', moduleViewIds);
```

### 2. Payload de Guardado (Línea 186)

**Sin cambios** - Ya estaba correcto:
```typescript
const payload = {
  user_id: Number(selectedUsuario),
  permissions: selectedPermisos,  // Array de moduleview_id
};
```

---

## 🎯 Endpoints Relacionados

### 1. Obtener Módulos
```
GET /api/modules
```

**Response:**
```json
[
  {
    "id": 1,
    "name": "Administrativo",
    "description": "Módulo administrativo",
    "status": true,
    "view_count": 12
  }
]
```

### 2. Obtener Vistas de un Módulo
```
GET /api/modules/:id/views
```

**Response:**
```json
[
  {
    "id": 1,
    "module_id": 1,
    "menu": "Dashboard",
    "submenu": null,
    "view_path": "/dashboard",
    "status": true,
    "order_num": 1
  }
]
```

### 3. Obtener Permisos de Usuario
```
GET /api/userpermissions?user_id=10
```

**Response:** Ver estructura completa arriba.

### 4. Guardar Permisos de Usuario
```
POST /api/userpermissions
```

**Request:**
```json
{
  "user_id": 10,
  "permissions": [1, 2, 3, 5, 8, 13]  // Array de moduleview_id
}
```

**Response:**
```json
{
  "success": true,
  "message": "Permisos actualizados correctamente.",
  "data": {
    "total_assigned": 6,
    "scope": "self"
  }
}
```

---

## 📝 Validaciones Backend

### UserPermisosController::store()

```php
$validator = Validator::make($request->all(), [
    'user_id' => 'required|exists:users,id',
    'permissions' => 'required|array',
    'permissions.*' => 'exists:moduleviews,id',
]);

// Scope se asigna automáticamente como 'self'
// No se requiere validación de permissions.action='view' porque ya no existe esa relación
```

---

## 🧪 Cómo Probar

### Paso 1: Seleccionar Usuario
1. Abrir componente `PermisosVistasTab`
2. Buscar usuario (ej: "PabloAdmin")
3. Seleccionar del dropdown

### Paso 2: Verificar Checkboxes
1. Abrir consola del navegador
2. Ver mensaje: `📊 Permisos cargados: [1, 2, 3, 5, 8, ...]`
3. Verificar que los checkboxes estén marcados correctamente

### Paso 3: Modificar Permisos
1. Marcar/desmarcar vistas
2. Click en "Guardar Permisos"
3. Verificar mensaje de éxito

### Paso 4: Verificar en Base de Datos
```sql
SELECT 
    up.user_id,
    u.username,
    up.moduleview_id,
    mv.menu,
    mv.submenu,
    up.scope
FROM userpermissions up
JOIN users u ON u.id = up.user_id
JOIN moduleviews mv ON mv.id = up.moduleview_id
WHERE up.user_id = 10
ORDER BY mv.order_num;
```

---

## 🔍 Debugging

Si los checkboxes no se marcan:

1. **Verificar en Consola:**
   ```javascript
   console.log('📊 Permisos cargados:', moduleViewIds);
   ```
   Debe mostrar array de números: `[1, 2, 3, 5, 8, 13, ...]`

2. **Verificar Response del Backend:**
   ```javascript
   console.log('API Response:', response.data);
   ```
   Debe tener `data` con array de objetos que tengan `moduleview_id`

3. **Verificar Estado del Componente:**
   ```javascript
   console.log('Selected Permisos:', selectedPermisos);
   ```
   Debe coincidir con los IDs cargados

---

## 📚 Documentación Relacionada

- `PERMISSIONS_SEPARATION_COMPLETE.md` - Separación de lógica de permisos
- `PERMISOS_USUARIOS_10_41.md` - Permisos asignados a usuarios de prueba
- `SESSION_SUMMARY_2025_10_24.md` - Resumen completo de cambios

---

## ✅ Checklist de Implementación

- [x] Actualizar extracción de `moduleview_id` en frontend
- [x] Agregar console.log para debugging
- [x] Documentar estructura de respuesta del backend
- [x] Documentar endpoints relacionados
- [x] Crear guía de troubleshooting
- [ ] Probar en entorno de desarrollo
- [ ] Verificar con múltiples usuarios
- [ ] Eliminar console.log antes de producción

---

**Autor:** GitHub Copilot  
**Última actualización:** 2025-10-24
