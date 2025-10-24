# 🎯 SEPARACIÓN DE LÓGICAS: Permisos por Usuario vs Permisos por Rol

**Fecha:** 24 de octubre de 2025  
**Problema:** Confusión entre permisos por usuario y permisos por rol

---

## 📊 ANTES (Lógica mezclada)

```
users → userpermissions.permission_id → permissions → moduleviews
                      ❌ INCORRECTO
```

**Problemas:**
- `userpermissions` usaba `permission_id` (tabla de permisos de ROL)
- Requería que cada `moduleview` tuviera un permiso 'view' en `permissions`
- Error: "moduleview_id 29 no tiene permiso 'view' configurado"
- Lógica compleja e innecesaria para asignación directa a usuarios

---

## ✅ DESPUÉS (Lógicas separadas)

### 1️⃣ Permisos por USUARIO (simplificado)
```
users → userpermissions.moduleview_id → moduleviews
                    ✅ DIRECTO
```

**Características:**
- Usuario puede acceder a Vista X (sí/no)
- Sin actions (view/create/edit/delete)
- Relación directa y simple

### 2️⃣ Permisos por ROL (complejo, para roles)
```
roles → permissions (action, effect, route_path, etc.)
              ↓
          moduleviews (via route_path)
```

**Características:**
- Permisos granulares con actions
- Effects (allow/deny)
- Level, resource, etc.
- Para sistema de roles futuro

---

## 🔧 CAMBIOS REALIZADOS

### Base de Datos

#### 1. Migración de `userpermissions`
```sql
-- Agregar columna
ALTER TABLE userpermissions ADD COLUMN moduleview_id INTEGER;

-- Mapear datos: permission_id → moduleview_id
UPDATE userpermissions up
SET moduleview_id = (
  SELECT mv.id 
  FROM permissions p
  JOIN moduleviews mv ON mv.view_path = p.route_path
  WHERE p.id = up.permission_id
);

-- Eliminar columna antigua
ALTER TABLE userpermissions DROP COLUMN permission_id;

-- Agregar foreign key
ALTER TABLE userpermissions 
ADD CONSTRAINT fk_userpermissions_moduleview 
FOREIGN KEY (moduleview_id) REFERENCES moduleviews(id) ON DELETE CASCADE;
```

**Registros migrados:** 933  
**Registros eliminados (huérfanos):** 15

---

### Modelos Eloquent

#### 1. `UserPermisos.php`

**ANTES:**
```php
protected $fillable = ['user_id', 'permission_id', 'assigned_at', 'scope'];

protected $casts = [
    'permission_id' => 'integer'
];

public function permission() {
    return $this->belongsTo(Permisos::class, 'permission_id', 'id');
}
```

**DESPUÉS:**
```php
protected $fillable = ['user_id', 'moduleview_id', 'assigned_at', 'scope'];

protected $casts = [
    'moduleview_id' => 'integer'
];

public function moduleView() {
    return $this->belongsTo(ModulesViews::class, 'moduleview_id', 'id');
}
```

---

#### 2. `Permisos.php`

**Eliminado:**
```php
public function users() {
    return $this->belongsToMany(User::class, 'userpermissions', 'permission_id', 'user_id');
}
```

**Razón:** `userpermissions` ya no usa `permission_id`

---

### Controllers

#### 1. `UserPermisosController.php`

**index() - ANTES:**
```php
$permissions = UserPermisos::with('permission.moduleView.module')
    ->where('user_id', $user_id)
    ->get();
```

**index() - DESPUÉS:**
```php
$permissions = UserPermisos::with('moduleView.module')
    ->where('user_id', $user_id)
    ->get();
```

---

**store() - ANTES (COMPLEJO):**
```php
// Validar
'permissions.*' => 'exists:moduleviews,id'

// Mapear moduleview_id → permission_id con JOIN complejo
$permMap = DB::table('permissions as p')
    ->join('moduleviews as mv', 'mv.view_path', '=', 'p.route_path')
    ->whereIn('mv.id', $moduleviewIds)
    ->where('p.action', '=', 'view')
    ->where('p.is_enabled', '=', true)
    ->pluck('p.id', 'mv.id')
    ->toArray();

// Verificar si faltan permisos 'view'
$missingMvIds = array_diff($moduleviewIds, array_keys($permMap));
if (!empty($missingMvIds)) {
    return error 422; // ❌ Este era el error
}

// Insertar con permission_id
$rows[] = [
    'user_id' => $userId,
    'permission_id' => $permMap[$mvId], // ❌
    'assigned_at' => $now,
    'scope' => 'self'
];
```

**store() - DESPUÉS (SIMPLE):**
```php
// Validar directamente contra moduleviews
'permissions.*' => 'exists:moduleviews,id'

// Insertar directamente con moduleview_id
$rows[] = [
    'user_id' => $userId,
    'moduleview_id' => $mvId, // ✅ DIRECTO
    'assigned_at' => $now,
    'scope' => 'self'
];
```

---

#### 2. `LoginController.php`

**ANTES:**
```php
$permissions = UserPermisos::with('permission')
    ->where('user_id', $user->id)
    ->get();

$allowedViews = ModulesViews::whereIn('id', function($query) use ($user) {
    $query->select('permission_id') // ❌
          ->from('userpermissions')
          ->where('user_id', $user->id);
})
```

**DESPUÉS:**
```php
$permissions = UserPermisos::with('moduleView')
    ->where('user_id', $user->id)
    ->get();

$allowedViews = ModulesViews::whereIn('id', function($query) use ($user) {
    $query->select('moduleview_id') // ✅
          ->from('userpermissions')
          ->where('user_id', $user->id);
})
```

---

## ✅ RESULTADOS

### Pruebas exitosas

```bash
php test_new_userpermissions_logic.php
```

**Salida:**
```
✅ user_id: 1
✅ moduleview_id: 4
✅ view_path: /seguimiento
✅ submenu: Panel de Seguimiento
✅ Correcto: Relación 'permission' no existe
✅ Validación pasó correctamente
✅ ModuleView ID 29 ahora es válido

🎉 LÓGICA SEPARADA EXITOSAMENTE
```

---

### Login funcional

```bash
php test_login_fix.php
```

**Salida:**
```
✅ Cargó correctamente: 62 permisos
✅ ModuleView: Panel de Seguimiento
✅ Cargó correctamente: 17 vistas permitidas
✅ Ya no usa relación 'permission'
✅ Usa relación 'moduleView'
```

---

## 📋 ESTRUCTURA FINAL

### Tabla `userpermissions`
```
┌────────────────────────┐
│   userpermissions      │
├────────────────────────┤
│ id                     │
│ user_id (FK → users)   │
│ moduleview_id (FK)     │◄── ✅ DIRECTO a moduleviews
│ assigned_at            │
│ scope                  │
└────────────────────────┘
```

### Tabla `permissions` (solo para roles)
```
┌────────────────────────┐
│   permissions          │
├────────────────────────┤
│ id                     │
│ module                 │
│ section                │
│ resource               │
│ action (view/edit/...) │
│ effect (allow/deny)    │
│ description            │
│ route_path             │
│ is_enabled             │
│ level                  │
└────────────────────────┘
```

---

## 🎯 BENEFICIOS

1. **Simplicidad:** Asignación directa usuario → vista
2. **Performance:** Sin JOINs complejos
3. **Mantenibilidad:** Lógicas separadas y claras
4. **Flexibilidad:** Preparado para sistema de roles futuro
5. **Error resuelto:** No requiere permisos 'view' para cada moduleview

---

## 📝 ARCHIVOS MODIFICADOS

### Modelos
- ✅ `app/Models/UserPermisos.php`
- ✅ `app/Models/Permisos.php`

### Controllers
- ✅ `app/Http/Controllers/Api/UserPermisosController.php`
- ✅ `app/Http/Controllers/Api/LoginController.php`

### Base de Datos
- ✅ `userpermissions` migrada (933 registros)
- ✅ Columna `permission_id` eliminada
- ✅ Columna `moduleview_id` agregada con FK

---

## 🚀 SIGUIENTE PASO

El sistema ahora está correctamente separado:
- **Permisos por USUARIO** → `userpermissions` → `moduleviews` ✅
- **Permisos por ROL** → `permissions` (para implementación futura)

**Frontend debe enviar:**
```json
{
  "user_id": 123,
  "permissions": [1, 2, 3, 29] // IDs de moduleviews directamente
}
```

✅ **ModuleView ID 29 ahora funciona correctamente**
