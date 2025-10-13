# API: Estudiantes Matriculados - Documentación

## 📋 Descripción

Nuevo endpoint para obtener y exportar **todos los estudiantes matriculados** del sistema, con filtros opcionales por fecha, programa y tipo de alumno. Este endpoint complementa el endpoint de reportes de matrícula existente, permitiendo cargar la totalidad de estudiantes de forma optimizada.

## 🎯 Endpoints

### 1. Consultar Estudiantes Matriculados

**Ruta:** `GET /api/administracion/estudiantes-matriculados`

**Autenticación:** Requerida (Bearer Token)

#### Parámetros de Consulta (Query Parameters)

| Parámetro | Tipo | Valores | Por Defecto | Descripción |
|-----------|------|---------|-------------|-------------|
| `fechaInicio` | date | YYYY-MM-DD | Primera matrícula del sistema | Fecha inicial del filtro |
| `fechaFin` | date | YYYY-MM-DD | Fecha actual | Fecha final del filtro |
| `programaId` | string | ID del programa o `all` | `all` | Filtrar por programa específico |
| `tipoAlumno` | string | `all`, `Nuevo`, `Recurrente` | `all` | Filtrar por tipo de alumno |
| `page` | integer | >= 1 | 1 | Número de página |
| `perPage` | integer | 1-1000 | 100 | Registros por página |
| `exportar` | boolean | `true`, `false` | `false` | Retornar todos los registros sin paginación |

#### Ejemplos de Uso

**1. Obtener todos los estudiantes (sin filtros)**
```bash
GET /api/administracion/estudiantes-matriculados
```

**2. Filtrar por rango de fechas**
```bash
GET /api/administracion/estudiantes-matriculados?fechaInicio=2024-01-01&fechaFin=2024-12-31
```

**3. Filtrar por programa específico**
```bash
GET /api/administracion/estudiantes-matriculados?programaId=5
```

**4. Obtener solo alumnos nuevos**
```bash
GET /api/administracion/estudiantes-matriculados?tipoAlumno=Nuevo
```

**5. Obtener todos los registros para exportación**
```bash
GET /api/administracion/estudiantes-matriculados?exportar=true
```

**6. Paginación personalizada**
```bash
GET /api/administracion/estudiantes-matriculados?page=2&perPage=500
```

#### Respuesta Exitosa (200 OK)

```json
{
  "estudiantes": [
    {
      "id": 123,
      "nombre": "Juan Pérez García",
      "carnet": "ASM2024001",
      "email": "juan.perez@example.com",
      "telefono": "+502 1234-5678",
      "fechaMatricula": "2024-03-15",
      "tipo": "Nuevo",
      "programa": "Desarrollo Web",
      "programaId": 5,
      "estado": "Activo"
    },
    {
      "id": 124,
      "nombre": "María López Hernández",
      "carnet": "ASM2024002",
      "email": "maria.lopez@example.com",
      "telefono": "+502 8765-4321",
      "fechaMatricula": "2024-03-18",
      "tipo": "Recurrente",
      "programa": "Marketing Digital",
      "programaId": 3,
      "estado": "Activo"
    }
  ],
  "paginacion": {
    "pagina": 1,
    "porPagina": 100,
    "total": 1250,
    "totalPaginas": 13
  },
  "estadisticas": {
    "totalEstudiantes": 1250,
    "nuevos": 350,
    "recurrentes": 900,
    "distribucionProgramas": [
      {
        "programa": "Desarrollo Web",
        "total": 450,
        "porcentaje": 36.0
      },
      {
        "programa": "Marketing Digital",
        "total": 380,
        "porcentaje": 30.4
      },
      {
        "programa": "Diseño Gráfico",
        "total": 420,
        "porcentaje": 33.6
      }
    ]
  },
  "filtros": {
    "fechaInicio": "2020-01-01",
    "fechaFin": "2024-12-31",
    "programaId": "all",
    "tipoAlumno": "all"
  },
  "filtrosDisponibles": {
    "rangosDisponibles": ["month", "quarter", "semester", "year", "custom"],
    "programas": [
      {
        "id": "3",
        "nombre": "Marketing Digital"
      },
      {
        "id": "5",
        "nombre": "Desarrollo Web"
      }
    ],
    "tiposAlumno": ["Nuevo", "Recurrente"]
  }
}
```

**Nota:** Cuando `exportar=true`, la respuesta no incluye el campo `paginacion` y retorna todos los estudiantes.

---

### 2. Exportar Estudiantes Matriculados

**Ruta:** `POST /api/administracion/estudiantes-matriculados/exportar`

**Autenticación:** Requerida (Bearer Token)

#### Parámetros del Cuerpo (JSON)

| Parámetro | Tipo | Valores | Por Defecto | Descripción |
|-----------|------|---------|-------------|-------------|
| `formato` | string | `pdf`, `excel`, `csv` | - | **Requerido**. Formato de exportación |
| `fechaInicio` | date | YYYY-MM-DD | Primera matrícula | Fecha inicial del filtro |
| `fechaFin` | date | YYYY-MM-DD | Fecha actual | Fecha final del filtro |
| `programaId` | string | ID o `all` | `all` | Filtrar por programa |
| `tipoAlumno` | string | `all`, `Nuevo`, `Recurrente` | `all` | Tipo de alumno |

#### Ejemplos de Uso

**1. Exportar a PDF**
```bash
curl -X POST "https://api.example.com/api/administracion/estudiantes-matriculados/exportar" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "formato": "pdf"
  }'
```

**2. Exportar a Excel con filtros**
```bash
curl -X POST "https://api.example.com/api/administracion/estudiantes-matriculados/exportar" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "formato": "excel",
    "fechaInicio": "2024-01-01",
    "fechaFin": "2024-12-31",
    "programaId": "5",
    "tipoAlumno": "Nuevo"
  }'
```

**3. Exportar a CSV todos los estudiantes**
```bash
curl -X POST "https://api.example.com/api/administracion/estudiantes-matriculados/exportar" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "formato": "csv"
  }'
```

#### Respuesta Exitosa

**PDF:**
- Content-Type: `application/pdf`
- Archivo: `estudiantes_matriculados_YYYY-MM-DD_HH-MM-SS.pdf`

**Excel:**
- Content-Type: `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`
- Archivo: `estudiantes_matriculados_YYYY-MM-DD_HH-MM-SS.xlsx`
- Incluye 3 hojas: Estadísticas, Estudiantes, Distribución por Programas

**CSV:**
- Content-Type: `text/csv; charset=UTF-8`
- Archivo: `estudiantes_matriculados_YYYY-MM-DD_HH-MM-SS.csv`

---

## 📊 Estructura de Datos

### Campos de Estudiante

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | integer | ID único del estudiante-programa |
| `nombre` | string | Nombre completo del estudiante |
| `carnet` | string | Carnet académico (o "N/A") |
| `email` | string | Correo electrónico (o "N/A") |
| `telefono` | string | Número de teléfono (o "N/A") |
| `fechaMatricula` | date | Fecha de matrícula (YYYY-MM-DD) |
| `tipo` | string | "Nuevo" o "Recurrente" |
| `programa` | string | Nombre del programa |
| `programaId` | integer | ID del programa |
| `estado` | string | "Activo" o "Inactivo" |

### Estadísticas

- **totalEstudiantes:** Total de estudiantes en el período
- **nuevos:** Estudiantes que se matricularon por primera vez en el período
- **recurrentes:** Estudiantes que ya tenían matrículas previas
- **distribucionProgramas:** Array con la distribución por programa

---

## 🔍 Diferencias con el Endpoint de Reportes de Matrícula

| Característica | `/reportes-matricula` | `/estudiantes-matriculados` |
|----------------|----------------------|----------------------------|
| **Propósito** | Reportes comparativos con períodos | Listado completo de estudiantes |
| **Datos** | Enfocado en métricas y tendencias | Enfocado en listado detallado |
| **Período** | Requiere rango específico | Opcional (por defecto: todo el historial) |
| **Paginación** | 1-100 registros/página | 1-1000 registros/página |
| **Comparativas** | Incluye comparación con período anterior | No incluye comparativas |
| **Tendencias** | Incluye evolución de 12 meses | No incluye tendencias |
| **Exportar todo** | No soportado directamente | Parámetro `exportar=true` |

---

## ⚡ Optimizaciones

1. **Índices Recomendados:**
```sql
CREATE INDEX idx_ep_created_at ON estudiante_programa(created_at);
CREATE INDEX idx_ep_programa_id ON estudiante_programa(programa_id);
CREATE INDEX idx_ep_prospecto_id ON estudiante_programa(prospecto_id);
```

2. **Paginación Eficiente:** Hasta 1000 registros por página para exportaciones masivas

3. **Lazy Loading:** El parámetro `exportar=true` obtiene todos los registros sin paginación

4. **Joins Optimizados:** Uso de joins para evitar N+1 queries

---

## 🔐 Seguridad

- Autenticación requerida con `auth:sanctum`
- Auditoría de exportaciones en logs
- Validación exhaustiva de parámetros
- Protección contra SQL injection (uso de Eloquent)

---

## 🐛 Manejo de Errores

### 422 Validation Error
```json
{
  "error": "Parámetros inválidos",
  "messages": {
    "formato": ["The formato field is required."]
  }
}
```

### 500 Server Error
```json
{
  "error": "Error al obtener estudiantes matriculados",
  "message": "Descripción del error",
  "debug": "Stack trace (solo en modo debug)"
}
```

---

## 📝 Ejemplos de Integración

### JavaScript/Fetch

```javascript
// Obtener todos los estudiantes con paginación
async function obtenerEstudiantes(pagina = 1) {
  const response = await fetch(
    `/api/administracion/estudiantes-matriculados?page=${pagina}&perPage=100`,
    {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json'
      }
    }
  );
  
  const data = await response.json();
  return data;
}

// Exportar a Excel
async function exportarExcel() {
  const response = await fetch('/api/administracion/estudiantes-matriculados/exportar', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      formato: 'excel',
      tipoAlumno: 'Nuevo'
    })
  });
  
  const blob = await response.blob();
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'estudiantes.xlsx';
  a.click();
}
```

### PHP/Laravel

```php
use Illuminate\Support\Facades\Http;

// Obtener estudiantes
$response = Http::withToken($token)
    ->get('https://api.example.com/api/administracion/estudiantes-matriculados', [
        'programaId' => '5',
        'tipoAlumno' => 'Nuevo',
        'page' => 1,
        'perPage' => 200
    ]);

$estudiantes = $response->json();
```

---

## ✅ Testing

### Casos de Prueba Recomendados

1. **Sin filtros:** Debe retornar todos los estudiantes del sistema
2. **Filtro por fecha:** Debe retornar solo estudiantes en el rango
3. **Filtro por programa:** Debe retornar solo estudiantes del programa
4. **Filtro por tipo:** Debe clasificar correctamente nuevos vs recurrentes
5. **Paginación:** Debe respetar los límites de página
6. **Exportar=true:** Debe retornar todos sin paginación
7. **Exportación PDF:** Debe generar archivo válido
8. **Exportación Excel:** Debe generar archivo con 3 hojas
9. **Exportación CSV:** Debe generar archivo CSV válido

---

## 📚 Archivos Modificados/Creados

1. **Controller:** `app/Http/Controllers/Api/AdministracionController.php`
   - Método `estudiantesMatriculados()`
   - Método `exportarEstudiantesMatriculados()`
   - Método `mapearEstudiante()`
   - Método `obtenerEstadisticasEstudiantes()`

2. **Export:** `app/Exports/EstudiantesMatriculadosExport.php`
   - Clase principal con soporte multi-hoja
   - `EstadisticasSheet`
   - `EstudiantesSheet`
   - `DistribucionSheet`

3. **View:** `resources/views/pdf/estudiantes-matriculados.blade.php`

4. **Routes:** `routes/api.php`
   - `GET /api/administracion/estudiantes-matriculados`
   - `POST /api/administracion/estudiantes-matriculados/exportar`
