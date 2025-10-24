# 🔧 SOLUCIÓN: Curso ID 412 con Código Incorrecto

## 📋 Problema Detectado

**Curso sincronizado desde Moodle:**
```
ID: 412
Moodle ID: 1494
Nombre: "Noviembre Lunes 2025 MBA Gestión del Talento Humano y Liderazgo"
Código: MBA ❌
Área: common ❌
Créditos: 3 ❌
Status: draft
```

**Debería ser:**
```
Código: MHTM08 ✅
Área: specialty ✅
Créditos: 4 ✅
```

---

## 🔍 Causa Raíz

El curso en Moodle está **mal nombrado**:
- **Nombre en Moodle**: "MBA Gestión del Talento Humano y Liderazgo"
- **Nombre correcto según pensum**: "MHTM Liderazgo de Equipos de Alto Rendimiento y Cultura Organizacional"

El curso pertenece al programa **MHTM** (Master in Human Talent Management), no al **MBA**.

---

## ✅ Solución Implementada

### 1️⃣ **Mapeo Temporal de Corrección**

He agregado al sistema un mapeo que detecta nombres incorrectos y los corrige automáticamente:

```php
// CourseController.php - codeMap()
'MBA Gestión del Talento Humano y Liderazgo' => 'MHTM08',
'MBA Gestión del Talento y Desarrollo Organizacional' => 'MHTM10',
'BBA Contabilidad Financiera' => 'BBA15',
```

### 2️⃣ **Metadatos Automáticos**

El sistema ahora asigna automáticamente:
- **MHTM08**: area=specialty, credits=4
- **BBA15**: area=common, credits=4

---

## 🚀 Cómo Probar la Solución

### **Opción A: Actualizar el curso existente (Rápido)**

```sql
UPDATE courses
SET 
    code = 'MHTM08',
    area = 'specialty',
    credits = 4,
    updated_at = NOW()
WHERE id = 412 AND moodle_id = 1494;
```

### **Opción B: Eliminar y re-sincronizar (Recomendado)**

1. **Eliminar el curso mal creado:**
```sql
DELETE FROM courses WHERE id = 412 AND moodle_id = 1494;
```

2. **Re-sincronizar desde el frontend:**
   - Ir a **Cursos** > **Sincronizar desde Moodle**
   - Seleccionar el curso "MBA Gestión del Talento Humano y Liderazgo"
   - Click en **Sincronizar**

3. **O usar el API directamente:**
```bash
POST /api/courses/bulk-sync-moodle
Content-Type: application/json

[{
    "moodle_id": 1494,
    "fullname": "Noviembre Lunes 2025 MBA Gestión del Talento Humano y Liderazgo"
}]
```

### **Opción C: Usar Tinker**

```bash
php artisan tinker
```

```php
// Eliminar el curso mal creado
Course::where('moodle_id', 1494)->delete();

// Ver todos los cursos MHTM
Course::where('code', 'LIKE', 'MHTM%')->get(['id', 'code', 'name', 'area', 'credits']);
```

---

## 🔧 Verificación

### **Verificar en base de datos:**
```sql
SELECT id, name, code, area, credits, moodle_id, status
FROM courses
WHERE moodle_id = 1494;
```

**Resultado esperado:**
```
code: MHTM08
area: specialty
credits: 4
status: draft
```

### **Verificar en logs:**
```bash
tail -f storage/logs/laravel.log | grep generateCourseCode
```

Deberías ver:
```
[generateCourseCode] ✅ Coincidencia EXACTA encontrada
title: MBA Gestión del Talento Humano y Liderazgo
code: MHTM08
```

---

## 📝 Cursos Detectados con Nombres Incorrectos en Moodle

| Nombre en Moodle | Nombre Correcto | Código |
|-----------------|----------------|--------|
| ❌ MBA Gestión del Talento Humano y Liderazgo | MHTM Liderazgo de Equipos de Alto Rendimiento... | MHTM08 |
| ❌ MBA Gestión del Talento y Desarrollo Organizacional | MHTM Gestión del Talento y Desarrollo Organizacional | MHTM10 |
| ✅ BBA Contabilidad Financiera | BBA Contabilidad Financiera | BBA15 |

---

## 🎯 Recomendación Permanente

### **Corregir los nombres en Moodle:**

1. Ir a **Moodle** como administrador
2. Buscar curso ID **1494**
3. **Editar configuración** > **General**
4. Cambiar **Nombre completo** de:
   ```
   Noviembre Lunes 2025 MBA Gestión del Talento Humano y Liderazgo
   ```
   a:
   ```
   Noviembre Lunes 2025 MHTM Liderazgo de Equipos de Alto Rendimiento y Cultura Organizacional
   ```
5. **Guardar cambios**
6. **Re-sincronizar** desde Blue Atlas

### **Ventajas de corregir en Moodle:**
- ✅ Los nombres serán consistentes con el pensum
- ✅ No se necesitarán mapeos de corrección
- ✅ Futuros cursos se sincronizarán correctamente
- ✅ Evita confusión para estudiantes y facilitadores

---

## 📊 Estado Actual del Sistema

### **Archivos Modificados:**
1. `app/Http/Controllers/Api/CourseController.php`
   - ✅ Método `codeMap()` con 200+ mapeos de cursos
   - ✅ Método `generateCourseCode()` mejorado con búsqueda parcial
   - ✅ Método `getCourseMetadata()` para área y créditos automáticos
   - ✅ Logging detallado para debugging

### **Archivos Creados:**
1. `test_course_mapping.php` - Script de prueba
2. `fix_course_412.sql` - Queries de corrección
3. `docs/FIX_COURSE_MAPPING.md` - Este documento

### **Caché:**
- ✅ `php artisan config:cache` ejecutado
- ✅ Sistema listo para sincronización

---

## 🔍 Debugging

Si el curso sigue mal después de re-sincronizar:

1. **Verificar logs:**
```bash
tail -f storage/logs/laravel.log
```

2. **Buscar curso en base de datos:**
```sql
SELECT * FROM courses WHERE moodle_id = 1494;
```

3. **Verificar mapeo en código:**
```bash
grep -n "MBA Gestión del Talento Humano" app/Http/Controllers/Api/CourseController.php
```

4. **Limpiar caché nuevamente:**
```bash
php artisan config:cache
php artisan route:cache
php artisan cache:clear
```

---

## 📞 Siguiente Paso

**Decide cuál opción usar:**
- 🔵 **Opción A** (SQL directo) - Inmediato, pero temporal
- 🟢 **Opción B** (Re-sincronizar) - Usa la lógica completa del sistema
- 🟡 **Opción C** (Corregir Moodle) - Solución permanente

**Recomendación:** Usar **Opción B** ahora + **Opción C** después.

---

## ✅ Checklist

- [x] Mapeo de corrección agregado
- [x] Metadatos automáticos implementados
- [x] Caché limpiada
- [x] Scripts de prueba creados
- [ ] Curso ID 412 corregido
- [ ] Nombres corregidos en Moodle
- [ ] Verificación final

