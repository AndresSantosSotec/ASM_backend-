# 🚀 Guía Rápida: Endpoint de Estudiantes Matriculados

## 📌 Resumen

Nuevo endpoint para obtener y exportar **todos los estudiantes matriculados** del sistema con filtros opcionales y soporte para exportación en múltiples formatos.

---

## 🎯 Endpoints Principales

### 1. Consultar Estudiantes

```bash
GET /api/administracion/estudiantes-matriculados
```

### 2. Exportar Estudiantes

```bash
POST /api/administracion/estudiantes-matriculados/exportar
```

---

## 🔧 Parámetros Comunes

| Parámetro | Tipo | Descripción | Por Defecto |
|-----------|------|-------------|-------------|
| `fechaInicio` | date | Fecha inicial (YYYY-MM-DD) | Inicio del sistema |
| `fechaFin` | date | Fecha final (YYYY-MM-DD) | Fecha actual |
| `programaId` | string | ID del programa o "all" | "all" |
| `tipoAlumno` | string | "all", "Nuevo", "Recurrente" | "all" |

---

## 💡 Casos de Uso Rápidos

### 1. Obtener TODOS los estudiantes del sistema

```bash
GET /api/administracion/estudiantes-matriculados
```

**Retorna:** Primeros 100 estudiantes con estadísticas generales

---

### 2. Filtrar por rango de fechas

```bash
GET /api/administracion/estudiantes-matriculados?fechaInicio=2024-01-01&fechaFin=2024-12-31
```

**Retorna:** Estudiantes matriculados en 2024

---

### 3. Solo estudiantes nuevos

```bash
GET /api/administracion/estudiantes-matriculados?tipoAlumno=Nuevo
```

**Retorna:** Estudiantes que se matricularon por primera vez

---

### 4. Filtrar por programa

```bash
GET /api/administracion/estudiantes-matriculados?programaId=5
```

**Retorna:** Solo estudiantes del programa con ID 5

---

### 5. Obtener TODO sin paginación (para exportar)

```bash
GET /api/administracion/estudiantes-matriculados?exportar=true
```

**Retorna:** TODOS los estudiantes sin límite de paginación

---

### 6. Paginación personalizada

```bash
GET /api/administracion/estudiantes-matriculados?page=2&perPage=500
```

**Retorna:** Página 2 con 500 registros

---

### 7. Exportar a Excel con filtros

```bash
POST /api/administracion/estudiantes-matriculados/exportar
Content-Type: application/json

{
  "formato": "excel",
  "fechaInicio": "2024-01-01",
  "fechaFin": "2024-12-31",
  "tipoAlumno": "Nuevo"
}
```

**Retorna:** Archivo Excel con estudiantes nuevos de 2024

---

### 8. Exportar TODO a PDF

```bash
POST /api/administracion/estudiantes-matriculados/exportar
Content-Type: application/json

{
  "formato": "pdf"
}
```

**Retorna:** PDF con todos los estudiantes del sistema

---

## 📊 Respuesta de Ejemplo

```json
{
  "estudiantes": [
    {
      "id": 123,
      "nombre": "Juan Pérez",
      "carnet": "ASM2024001",
      "email": "juan@example.com",
      "telefono": "+502 1234-5678",
      "fechaMatricula": "2024-03-15",
      "tipo": "Nuevo",
      "programa": "Desarrollo Web",
      "programaId": 5,
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
    "distribucionProgramas": [...]
  },
  "filtros": {
    "fechaInicio": "2020-01-01",
    "fechaFin": "2024-12-31",
    "programaId": "all",
    "tipoAlumno": "all"
  }
}
```

---

## 🎨 Formatos de Exportación

### PDF
- Archivo: `estudiantes_matriculados_YYYY-MM-DD_HH-MM-SS.pdf`
- Incluye: Estadísticas + Distribución + Listado completo
- Vista profesional con tablas y gráficos

### Excel (XLSX)
- Archivo: `estudiantes_matriculados_YYYY-MM-DD_HH-MM-SS.xlsx`
- **3 Hojas:**
  1. Estadísticas (totales, nuevos, recurrentes)
  2. Estudiantes (listado completo con todos los campos)
  3. Distribución por Programas

### CSV
- Archivo: `estudiantes_matriculados_YYYY-MM-DD_HH-MM-SS.csv`
- Formato simple compatible con Excel y herramientas de análisis
- Encoding UTF-8

---

## ⚡ Características Especiales

### 1. Paginación Flexible
- Mínimo: 1 registro/página
- Máximo: 1000 registros/página
- Ideal para cargas masivas

### 2. Sin Límite de Tiempo
- Por defecto obtiene **TODOS** los estudiantes desde el inicio del sistema
- No requiere especificar fechas

### 3. Exportación Completa
- Parámetro `exportar=true` ignora la paginación
- Retorna TODOS los registros filtrados en una sola respuesta

### 4. Estadísticas Automáticas
- Calcula totales, nuevos y recurrentes automáticamente
- Genera distribución por programa con porcentajes

---

## 🔍 Diferencias con `/reportes-matricula`

| Característica | `/reportes-matricula` | `/estudiantes-matriculados` |
|----------------|----------------------|----------------------------|
| **Enfoque** | Reportes comparativos | Listado completo |
| **Período** | Requiere rango | Opcional (todo por defecto) |
| **Paginación** | Max 100/página | Max 1000/página |
| **Comparativas** | ✅ Con período anterior | ❌ No incluye |
| **Tendencias** | ✅ 12 meses | ❌ No incluye |
| **Exportar todo** | ❌ No directo | ✅ Con `exportar=true` |
| **Datos de contacto** | ❌ No incluye | ✅ Email, teléfono, carnet |

---

## 🚀 Integración Rápida

### JavaScript

```javascript
// Obtener todos los estudiantes
const estudiantes = await fetch('/api/administracion/estudiantes-matriculados', {
  headers: { 'Authorization': `Bearer ${token}` }
}).then(r => r.json());

// Descargar Excel
const exportar = async () => {
  const response = await fetch('/api/administracion/estudiantes-matriculados/exportar', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ formato: 'excel' })
  });
  
  const blob = await response.blob();
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'estudiantes.xlsx';
  a.click();
};
```

---

## ✅ Validaciones

- `fechaInicio` debe ser una fecha válida (YYYY-MM-DD)
- `fechaFin` debe ser mayor o igual a `fechaInicio`
- `programaId` acepta IDs numéricos o "all"
- `tipoAlumno` solo acepta: "all", "Nuevo", "Recurrente"
- `page` debe ser >= 1
- `perPage` debe estar entre 1 y 1000
- `formato` (exportación) solo acepta: "pdf", "excel", "csv"

---

## 🐛 Errores Comunes

### Error 422: Parámetros inválidos
```json
{
  "error": "Parámetros inválidos",
  "messages": {
    "formato": ["The formato field is required."]
  }
}
```

**Solución:** Verificar que todos los parámetros requeridos estén presentes y sean válidos

---

### Error 401: No autenticado
```json
{
  "message": "Unauthenticated."
}
```

**Solución:** Incluir el Bearer Token en el header `Authorization`

---

## 📝 Notas Importantes

1. **Autenticación obligatoria:** Todos los endpoints requieren `auth:sanctum`
2. **Auditoría:** Todas las exportaciones se registran en logs
3. **Performance:** Usa índices en `created_at`, `programa_id`, `prospecto_id`
4. **Sin fechas = TODO:** Si no se especifican fechas, obtiene todos los estudiantes del sistema
5. **Paginación inteligente:** Ajusta `perPage` según necesidad (100 para UI, 1000 para reportes)

---

## 🎯 Recomendaciones

1. **Para UI:** Usa paginación con `perPage=100`
2. **Para reportes:** Usa `exportar=true` o `perPage=1000`
3. **Para análisis:** Exporta a Excel para datos estructurados
4. **Para compartir:** Exporta a PDF para presentaciones
5. **Para integración:** Exporta a CSV para otras herramientas

---

## 📚 Documentación Completa

Ver: `docs/ESTUDIANTES_MATRICULADOS_API_DOCS.md`
