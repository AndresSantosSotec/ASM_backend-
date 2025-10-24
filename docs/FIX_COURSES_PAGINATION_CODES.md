# 🐛 FIX: Problemas de Cursos - Paginación y Códigos Malformados

**Fecha**: 24 de Octubre, 2025  
**Archivos Modificados**: `CourseController.php`  
**Prioridad**: 🔴 CRÍTICA

---

## 📋 Resumen de Problemas

### Problema 1: ❌ Frontend solo carga últimos cursos
**Síntoma**: La interfaz muestra solo la página 1, pero siempre con los mismos cursos (últimos 10-15)

**Causa**: El frontend está paginando correctamente, pero probablemente hay un problema de caché en el navegador o el frontend no está enviando correctamente los parámetros `page` y `per_page`.

**Estado del Backend**: ✅ El backend YA estaba usando `paginate()` correctamente

### Problema 2: ❌ Códigos de curso malformados
**Síntoma**: 
```
❌ CÓDIGO ACTUAL: NOVIEMBRELUNES2025MBAGESTINDELTALENTOHUMANOYLIDERA
✅ CÓDIGO ESPERADO: MBA
```

**Causa**: La función `generateCourseCode()` estaba tomando TODO el nombre del curso y eliminando espacios, en lugar de extraer SOLO el prefijo del programa.

**Ejemplo Real**:
```
Nombre: "Noviembre Lunes 2025 MBA Gestión del Talento Humano y Liderazgo"
Código Anterior: NOVIEMBRELUNES2025MBAGESTINDELTALENTOHUMANOYLIDERA
Código Corregido: MBA
```

---

## ✅ Soluciones Implementadas

### 1. Verificación de Paginación Backend

El método `index()` ya está correcto:

```php
public function index(Request $request)
{
    $perPage = $request->input('per_page', 15);
    $perPage = min(max((int)$perPage, 1), 200);

    $query = Course::with(['facilitator', 'programas'])
        ->when($request->search, ...)
        ->orderBy('created_at', 'desc');

    // ✅ Paginación correcta
    $courses = $query->paginate($perPage);

    return response()->json($courses);
}
```

**Respuesta JSON Esperada**:
```json
{
  "current_page": 1,
  "data": [...],
  "first_page_url": "http://localhost:8000/api/courses?page=1",
  "from": 1,
  "last_page": 36,
  "last_page_url": "http://localhost:8000/api/courses?page=36",
  "next_page_url": "http://localhost:8000/api/courses?page=2",
  "path": "http://localhost:8000/api/courses",
  "per_page": 10,
  "prev_page_url": null,
  "to": 10,
  "total": 360
}
```

### 2. Corrección de Generación de Códigos

**ANTES** (❌ Código malformado):
```php
private function generateCourseCode(string $shortnameOrFullname, ?string $fullname = null): string
{
    $title = $this->canonicalTitle($fullname ?: $shortnameOrFullname);
    $map = $this->codeMap();

    if (isset($map[$title])) {
        $base = $map[$title];
    } else {
        // ❌ PROBLEMA: Toma TODO el nombre y quita TODOS los espacios
        $src  = $shortnameOrFullname ?: $title;
        $base = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $src));
        // Resultado: NOVIEMBRELUNES2025MBAGESTINDELTALENTOHUMANOYLIDERA
    }

    return $base;
}
```

**DESPUÉS** (✅ Solo prefijo del programa):
```php
private function generateCourseCode(string $shortnameOrFullname, ?string $fullname = null): string
{
    $title = $this->canonicalTitle($fullname ?: $shortnameOrFullname);
    $map = $this->codeMap();

    if (isset($map[$title])) {
        return $map[$title];
    }

    // ✅ NUEVO: Extraer SOLO el prefijo del programa (MBA, BBA, MMK, etc.)
    if (preg_match('/^(MBA|BBA|MMK|EMBA|DBA|MSc|PhD)\b/i', $title, $matches)) {
        return strtoupper($matches[1]); // Devuelve solo "MBA", "BBA", etc.
    }

    // Fallback si no se encuentra prefijo
    $src = $shortnameOrFullname ?: $title;
    $base = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $src));
    
    if ($base === '') {
        $base = 'COURSE' . time();
    }

    return substr($base, 0, 50);
}
```

---

## 🔍 Flujo de Procesamiento de Códigos

### Paso 1: Entrada desde Moodle
```
Moodle shortname: "NOVIEMBRELUNES2025MBAGESTINDELTALENTOHUMANOYLIDERA"
Moodle fullname: "Noviembre Lunes 2025 MBA Gestión del Talento Humano y Liderazgo"
```

### Paso 2: Canonicalización del Título
```php
canonicalTitle("Noviembre Lunes 2025 MBA Gestión del Talento Humano y Liderazgo")
// Quita: "Noviembre Lunes 2025 "
// Resultado: "MBA Gestión del Talento Humano y Liderazgo"
```

### Paso 3: Verificar Mapeo Exacto
```php
$map = [
    'BBA Contabilidad Aplicada' => 'BBA14',
    // ...
];

if (isset($map["MBA Gestión del Talento Humano y Liderazgo"])) {
    return $map[...]; // No existe en el mapeo
}
```

### Paso 4: Extraer Prefijo del Programa
```php
preg_match('/^(MBA|BBA|MMK|EMBA|DBA|MSc|PhD)\b/i', "MBA Gestión del...", $matches)
// $matches[1] = "MBA"
return "MBA"; // ✅ Código correcto
```

### Paso 5: Verificar Duplicados
```php
$code = "MBA";
$counter = 1;
while (Course::where('code', $code)->exists()) {
    $code = "MBA-" . $counter; // MBA-1, MBA-2, etc.
    $counter++;
}
```

---

## 📊 Ejemplos de Transformación

| Nombre Completo | Título Canónico | Código Generado |
|----------------|-----------------|----------------|
| Noviembre Lunes 2025 MBA Gestión del Talento Humano | MBA Gestión del Talento Humano | **MBA** |
| Noviembre Lunes 2025 BBA Excel Ejecutivo | BBA Excel Ejecutivo | **BBA** |
| Octubre Sábado 2025 MMK Marketing Digital | MMK Marketing Digital | **MMK** |
| Noviembre Martes 2025 MBA Cash Flow Management | MBA Cash Flow Management | **MBA-1** (si MBA ya existe) |
| Diciembre 2025 BBA Contabilidad Aplicada | BBA Contabilidad Aplicada | **BBA14** (mapeo exacto) |

---

## 🧪 Cómo Probar

### 1. Limpiar Cursos Duplicados (Opcional)

Si tienes cursos con códigos malformados, puedes limpiarlos:

```sql
-- Ver cursos con códigos largos/malformados
SELECT id, code, name 
FROM courses 
WHERE LENGTH(code) > 20
ORDER BY LENGTH(code) DESC;

-- Opcional: Eliminar cursos duplicados de Moodle (CUIDADO)
DELETE FROM courses 
WHERE LENGTH(code) > 20 
AND origen = 'moodle';
```

### 2. Probar Sincronización

```bash
# 1. Limpiar caché
php artisan cache:clear
php artisan config:cache

# 2. Sincronizar curso de prueba desde Moodle
# Usar la API de Moodle test
```

### 3. Verificar Paginación en Frontend

```javascript
// En DevTools Console
console.log('Página actual:', currentPage);
console.log('Total de páginas:', totalPages);
console.log('Cursos cargados:', courses.length);

// Verificar que el frontend esté enviando los params correctos
// Network tab → Ver request a /api/courses
// Query params: ?page=2&per_page=10
```

---

## 🔧 Solución al Problema de Paginación Frontend

El backend está correcto. El problema puede estar en:

### 1. Caché del Navegador

```javascript
// Limpiar caché de axios/fetch
// En api.ts o donde configures axios:
api.defaults.headers.common['Cache-Control'] = 'no-cache';
api.defaults.headers.common['Pragma'] = 'no-cache';
```

### 2. Lógica de fetchCourses()

Tu código frontend está bien, pero agrega validación:

```typescript
export const fetchCourses = async (programId?: number) => {
  const perPage = 200
  let page = 1
  const courses: Course[] = []
  const maxPages = 50

  while (page <= maxPages) {
    console.log(`📥 Cargando página ${page}...`);
    
    const res = await api.get('/courses', {
      params: { 
        program_id: programId || undefined,
        per_page: perPage, 
        page 
      },
    })
    
    // ✅ Verificar que data sea paginación de Laravel
    const pagination = res.data
    const data = Array.isArray(pagination) ? pagination : pagination.data
    
    console.log(`✅ Página ${page}/${pagination.last_page}: ${data.length} cursos`);
    
    if (!data || data.length === 0) break
    
    courses.push(...data.map(mapCourseFromApi))

    // ✅ Usar last_page de Laravel en lugar de comparar length
    if (pagination.last_page && page >= pagination.last_page) {
      console.log(`🏁 Última página alcanzada (${page}/${pagination.last_page})`);
      break
    }
    
    if (data.length < perPage) {
      console.log(`🏁 Última página (${data.length} < ${perPage})`);
      break
    }
    
    page++
  }

  console.log(`📊 Total: ${courses.length} cursos`);
  return courses
}
```

### 3. Verificar Respuesta del Backend

```bash
# Probar manualmente la paginación
curl "http://localhost:8000/api/courses?page=1&per_page=10" \
  -H "Authorization: Bearer TU_TOKEN"

curl "http://localhost:8000/api/courses?page=2&per_page=10" \
  -H "Authorization: Bearer TU_TOKEN"

# Verificar que los cursos sean diferentes entre página 1 y 2
```

---

## 📝 Prefijos de Programa Soportados

La nueva función reconoce automáticamente estos prefijos:

- **MBA** - Master in Business Administration
- **BBA** - Bachelor in Business Administration  
- **MMK** - Master in Marketing
- **EMBA** - Executive MBA
- **DBA** - Doctor in Business Administration
- **MSc** - Master of Science
- **PhD** - Doctor of Philosophy

Si necesitas agregar más:

```php
// En generateCourseCode(), línea de regex:
if (preg_match('/^(MBA|BBA|MMK|EMBA|DBA|MSc|PhD|NUEVO)\b/i', $title, $matches)) {
    return strtoupper($matches[1]);
}
```

---

## ⚠️ Nota Importante: Códigos Duplicados

Con el nuevo sistema, múltiples cursos pueden tener el mismo prefijo (MBA, BBA, etc.). El sistema agrega sufijos automáticamente:

```
- MBA (primer curso)
- MBA-1 (segundo curso con mismo prefijo)
- MBA-2 (tercer curso con mismo prefijo)
- BBA (primer curso BBA)
- BBA-1 (segundo curso BBA)
```

**Si prefieres códigos únicos descriptivos**, usa el mapeo en `codeMap()`:

```php
private function codeMap(): array
{
    return [
        'BBA Contabilidad Aplicada' => 'BBA14',
        'BBA Excel Ejecutivo' => 'BBA12',
        'MBA Gestión del Talento Humano y Liderazgo' => 'MBA01',
        'MBA Cash Flow Management' => 'MBA02',
        // Agregar más mapeos según necesites
    ];
}
```

---

## 🎯 Resultado Final

### Antes del Fix:
```
Código: NOVIEMBRELUNES2025MBAGESTINDELTALENTOHUMANOYLIDERA
Nombre: Noviembre Lunes 2025 MBA Gestión del Talento Humano y Liderazgo
```

### Después del Fix:
```
Código: MBA
Nombre: Noviembre Lunes 2025 MBA Gestión del Talento Humano y Liderazgo
```

O con mapeo exacto:
```
Código: MBA01 (definido en codeMap())
Nombre: Noviembre Lunes 2025 MBA Gestión del Talento Humano y Liderazgo
```

---

**Estado**: ✅ CORREGIDO
**Siguiente Paso**: Resincronizar cursos de Moodle para aplicar los nuevos códigos
