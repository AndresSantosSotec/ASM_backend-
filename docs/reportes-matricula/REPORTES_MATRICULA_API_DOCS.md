# API de Reportes de Matrícula y Alumnos Nuevos

## Descripción General

Este módulo proporciona endpoints para generar reportes detallados de matrícula y alumnos nuevos, con capacidades de filtrado, comparación de períodos, análisis de tendencias y exportación en múltiples formatos.

## Endpoints

### 1. Consultar Reportes de Matrícula

**GET** `/api/administracion/reportes-matricula`

Obtiene un reporte completo de matrícula con métricas, comparativas y tendencias.

#### Autenticación

Requiere token de autenticación (Sanctum) con rol de administrador.

```http
Authorization: Bearer {token}
```

#### Parámetros de Consulta

| Parámetro | Tipo | Valores | Por Defecto | Descripción |
|-----------|------|---------|-------------|-------------|
| `rango` | string | `month`, `quarter`, `semester`, `year`, `custom` | `month` | Período de tiempo a analizar |
| `fechaInicio` | date | YYYY-MM-DD | - | Fecha inicial (requerido si rango=custom) |
| `fechaFin` | date | YYYY-MM-DD | - | Fecha final (requerido si rango=custom) |
| `programaId` | string | ID del programa o `all` | `all` | Filtrar por programa específico |
| `tipoAlumno` | string | `all`, `Nuevo`, `Recurrente` | `all` | Tipo de alumno a incluir |
| `page` | integer | >= 1 | `1` | Página para paginación |
| `perPage` | integer | 1-100 | `50` | Registros por página |

#### Ejemplos de Uso

##### Reporte del mes actual
```bash
GET /api/administracion/reportes-matricula
```

##### Reporte del trimestre actual filtrando por programa
```bash
GET /api/administracion/reportes-matricula?rango=quarter&programaId=5
```

##### Reporte de alumnos nuevos en rango personalizado
```bash
GET /api/administracion/reportes-matricula?rango=custom&fechaInicio=2025-01-01&fechaFin=2025-03-31&tipoAlumno=Nuevo
```

##### Reporte paginado
```bash
GET /api/administracion/reportes-matricula?page=2&perPage=25
```

#### Respuesta Exitosa (200 OK)

```json
{
  "filtros": {
    "rangosDisponibles": ["month", "quarter", "semester", "year", "custom"],
    "programas": [
      {
        "id": "1",
        "nombre": "Desarrollo Web"
      },
      {
        "id": "2",
        "nombre": "Marketing Digital"
      }
    ],
    "tiposAlumno": ["Nuevo", "Recurrente"]
  },
  "periodoActual": {
    "rango": {
      "fechaInicio": "2025-03-01",
      "fechaFin": "2025-03-31",
      "descripcion": "marzo 2025"
    },
    "totales": {
      "matriculados": 124,
      "alumnosNuevos": 74,
      "alumnosRecurrentes": 50
    },
    "distribucionProgramas": [
      {
        "programa": "Desarrollo Web",
        "total": 40
      },
      {
        "programa": "Marketing Digital",
        "total": 32
      }
    ],
    "evolucionMensual": [
      {
        "mes": "2025-01",
        "total": 98
      },
      {
        "mes": "2025-02",
        "total": 110
      },
      {
        "mes": "2025-03",
        "total": 124
      }
    ],
    "distribucionTipo": [
      {
        "tipo": "Nuevo",
        "total": 74
      },
      {
        "tipo": "Recurrente",
        "total": 50
      }
    ]
  },
  "periodoAnterior": {
    "totales": {
      "matriculados": 102,
      "alumnosNuevos": 60,
      "alumnosRecurrentes": 42
    },
    "rangoComparado": {
      "fechaInicio": "2025-02-01",
      "fechaFin": "2025-02-28",
      "descripcion": "febrero 2025"
    }
  },
  "comparativa": {
    "totales": {
      "actual": 124,
      "anterior": 102,
      "variacion": 21.57
    },
    "nuevos": {
      "actual": 74,
      "anterior": 60,
      "variacion": 23.33
    },
    "recurrentes": {
      "actual": 50,
      "anterior": 42,
      "variacion": 19.05
    }
  },
  "tendencias": {
    "ultimosDoceMeses": [
      {
        "mes": "2024-04",
        "total": 85
      },
      {
        "mes": "2024-05",
        "total": 90
      }
      // ... más meses
    ],
    "crecimientoPorPrograma": [
      {
        "programa": "Desarrollo Web",
        "variacion": 12.5
      },
      {
        "programa": "Marketing Digital",
        "variacion": 8.3
      }
    ],
    "proyeccion": [
      {
        "periodo": "2025-04",
        "totalEsperado": 130
      }
    ]
  },
  "listado": {
    "alumnos": [
      {
        "id": 1,
        "nombre": "Ana García",
        "fechaMatricula": "2025-03-05",
        "tipo": "Nuevo",
        "programa": "Desarrollo Web",
        "estado": "Activo"
      }
      // ... más alumnos
    ],
    "paginacion": {
      "pagina": 1,
      "porPagina": 50,
      "total": 124,
      "totalPaginas": 3
    }
  }
}
```

#### Respuestas de Error

##### 401 Unauthorized
```json
{
  "message": "Unauthenticated."
}
```

##### 422 Validation Error
```json
{
  "error": "Parámetros inválidos",
  "messages": {
    "fechaInicio": [
      "The fecha inicio field is required when rango is custom."
    ],
    "fechaFin": [
      "The fecha fin field is required when rango is custom."
    ]
  }
}
```

##### 500 Server Error
```json
{
  "error": "Error al obtener reportes de matrícula",
  "message": "Descripción del error",
  "debug": "Stack trace (solo en modo debug)"
}
```

---

### 2. Exportar Reportes de Matrícula

**POST** `/api/administracion/reportes-matricula/exportar`

Exporta el reporte de matrícula en formato PDF, Excel o CSV.

#### Autenticación

Requiere token de autenticación (Sanctum) con rol de administrador.

```http
Authorization: Bearer {token}
Content-Type: application/json
```

#### Parámetros del Cuerpo (JSON)

| Parámetro | Tipo | Valores | Por Defecto | Descripción |
|-----------|------|---------|-------------|-------------|
| `formato` | string | `pdf`, `excel`, `csv` | - | **Requerido**. Formato de exportación |
| `detalle` | string | `complete`, `summary`, `data` | `complete` | Nivel de detalle del reporte |
| `incluirGraficas` | boolean | `true`, `false` | `false` | Incluir gráficas en PDF (futuro) |
| `rango` | string | `month`, `quarter`, `semester`, `year`, `custom` | `month` | Período a exportar |
| `fechaInicio` | date | YYYY-MM-DD | - | Fecha inicial (si rango=custom) |
| `fechaFin` | date | YYYY-MM-DD | - | Fecha final (si rango=custom) |
| `programaId` | string | ID del programa o `all` | `all` | Filtrar por programa |
| `tipoAlumno` | string | `all`, `Nuevo`, `Recurrente` | `all` | Tipo de alumno |

#### Niveles de Detalle

- **`complete`**: Incluye resumen ejecutivo, listado de alumnos y distribución por programas
- **`summary`**: Solo resumen ejecutivo y métricas comparativas
- **`data`**: Solo listado de alumnos (útil para análisis en Excel)

#### Ejemplos de Uso

##### Exportar a PDF con detalle completo
```bash
curl -X POST /api/administracion/reportes-matricula/exportar \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "formato": "pdf",
    "detalle": "complete",
    "rango": "month"
  }'
```

##### Exportar a Excel solo con datos
```bash
curl -X POST /api/administracion/reportes-matricula/exportar \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "formato": "excel",
    "detalle": "data",
    "rango": "quarter",
    "programaId": "5"
  }'
```

##### Exportar a CSV rango personalizado
```bash
curl -X POST /api/administracion/reportes-matricula/exportar \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "formato": "csv",
    "detalle": "summary",
    "rango": "custom",
    "fechaInicio": "2025-01-01",
    "fechaFin": "2025-03-31",
    "tipoAlumno": "Nuevo"
  }'
```

#### Respuesta Exitosa (200 OK)

##### Para PDF
```
Content-Type: application/pdf
Content-Disposition: attachment; filename="reportes_matricula_2025-03-15_14-30-45.pdf"

[Binary PDF content]
```

##### Para Excel
```
Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
Content-Disposition: attachment; filename="reportes_matricula_2025-03-15_14-30-45.xlsx"

[Binary Excel content]
```

##### Para CSV
```
Content-Type: text/csv; charset=UTF-8
Content-Disposition: attachment; filename="reportes_matricula_2025-03-15_14-30-45.csv"

[CSV content]
```

#### Estructura de Archivos Exportados

##### PDF
- Encabezado con título y fecha de generación
- Período analizado
- Resumen ejecutivo con métricas comparativas (si detalle=complete o summary)
- Distribución por programas (si detalle=complete)
- Listado de alumnos (si detalle=complete o data)
- Pie de página con información del sistema

##### Excel (Múltiples hojas)
- **Hoja 1 - Resumen**: Métricas comparativas (si detalle=complete o summary)
- **Hoja 2 - Listado de Alumnos**: Lista detallada (si detalle=complete o data)
- **Hoja 3 - Distribución por Programas**: Tabla de distribución (si detalle=complete)

##### CSV
- Formato tabular simple
- Encabezados en primera fila
- Datos según el nivel de detalle seleccionado

#### Respuestas de Error

##### 422 Validation Error
```json
{
  "error": "Parámetros inválidos",
  "messages": {
    "formato": [
      "The formato field is required."
    ]
  }
}
```

##### 500 Server Error
```json
{
  "error": "Error al exportar reportes",
  "message": "Descripción del error"
}
```

---

## Definiciones de Conceptos

### Alumno Nuevo
Un alumno se considera "nuevo" cuando su **primera matrícula en el sistema** se encuentra dentro del período seleccionado. Esto significa que es la primera vez que se inscribe en cualquier programa de la institución.

### Alumno Recurrente
Un alumno es "recurrente" cuando tiene matrículas anteriores al período seleccionado, pero se matriculó nuevamente dentro del período actual. Puede estar inscribiéndose en el mismo programa u otro diferente.

### Rangos de Fecha

- **month**: Mes actual (del 1 al último día del mes)
- **quarter**: Trimestre actual (Q1: ene-mar, Q2: abr-jun, Q3: jul-sep, Q4: oct-dic)
- **semester**: Semestre actual (S1: ene-jun, S2: jul-dic)
- **year**: Año actual (1 de enero al 31 de diciembre)
- **custom**: Rango personalizado definido por fechaInicio y fechaFin

### Cálculo de Variación Porcentual

```
variacion = ((actual - anterior) / anterior) * 100
```

**Casos especiales:**
- Si anterior = 0 y actual > 0: variación = 100%
- Si anterior = 0 y actual = 0: variación = 0%
- Valores negativos indican decrecimiento

---

## Auditoría y Logs

Cada exportación se registra en los logs del sistema con la siguiente información:

```php
[
    'user_id' => ID del usuario que exporta,
    'formato' => 'pdf|excel|csv',
    'detalle' => 'complete|summary|data',
    'filtros' => [
        'rango' => 'month|quarter|etc',
        'programaId' => 'all|ID',
        'tipoAlumno' => 'all|Nuevo|Recurrente'
    ]
]
```

---

## Consideraciones de Performance

1. **Paginación**: Use paginación para grandes volúmenes de datos (parámetros `page` y `perPage`)
2. **Filtros**: Aplique filtros específicos (programa, tipo de alumno) para reducir el volumen de datos
3. **Caché**: Los reportes NO se cachean automáticamente debido a su naturaleza dinámica
4. **Exportación**: Las exportaciones a PDF pueden tardar más en generarse que Excel/CSV

---

## Ejemplos de Integración

### JavaScript/Fetch

```javascript
// Obtener reporte del mes actual
async function obtenerReporteMatricula() {
  const response = await fetch('/api/administracion/reportes-matricula', {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json'
    }
  });
  
  const data = await response.json();
  return data;
}

// Exportar a Excel
async function exportarAExcel() {
  const response = await fetch('/api/administracion/reportes-matricula/exportar', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      formato: 'excel',
      detalle: 'complete',
      rango: 'month'
    })
  });
  
  const blob = await response.blob();
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'reporte_matricula.xlsx';
  a.click();
}
```

### PHP/Guzzle

```php
use GuzzleHttp\Client;

$client = new Client([
    'base_uri' => 'https://api.example.com',
    'headers' => [
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json'
    ]
]);

// Obtener reporte
$response = $client->get('/api/administracion/reportes-matricula', [
    'query' => [
        'rango' => 'quarter',
        'programaId' => '5'
    ]
]);

$data = json_decode($response->getBody(), true);

// Exportar
$response = $client->post('/api/administracion/reportes-matricula/exportar', [
    'json' => [
        'formato' => 'pdf',
        'detalle' => 'complete'
    ]
]);

file_put_contents('reporte.pdf', $response->getBody());
```

---

## Troubleshooting

### Error: "The fecha inicio field is required when rango is custom"
**Solución**: Al usar `rango=custom`, debe proporcionar `fechaInicio` y `fechaFin`.

### Error: "The fecha fin must be a date after or equal to fecha inicio"
**Solución**: Verifique que `fechaFin` sea posterior o igual a `fechaInicio`.

### Resultado vacío
**Verificar**:
- Que existan matrículas en el rango de fechas seleccionado
- Que el programa seleccionado tenga estudiantes
- Que los filtros aplicados no sean demasiado restrictivos

### Exportación tarda mucho
**Optimizar**:
- Use filtros para reducir el volumen de datos
- Reduzca el rango de fechas
- Use nivel de detalle `summary` en lugar de `complete`

---

## Versionado

**Versión**: 1.0.0  
**Fecha**: Octubre 2025  
**Compatibilidad**: Laravel 10.x, PHP 8.x

---

## Soporte

Para reportar problemas o solicitar mejoras, contacte al equipo de desarrollo o cree un issue en el repositorio del proyecto.
