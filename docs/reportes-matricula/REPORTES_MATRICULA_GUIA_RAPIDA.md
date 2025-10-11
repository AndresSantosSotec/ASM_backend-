# Guía Rápida: Reportes de Matrícula - Implementación

## Resumen de la Implementación

Se han creado dos nuevos endpoints en el módulo de administración para generar reportes de matrícula y alumnos nuevos, con capacidades avanzadas de filtrado, comparación de períodos y exportación en múltiples formatos.

## Archivos Modificados/Creados

### 1. Controller Principal
**Archivo**: `app/Http/Controllers/Api/AdministracionController.php`

**Métodos Agregados**:
- `reportesMatricula()` - Endpoint principal de consulta
- `exportarReportesMatricula()` - Endpoint de exportación
- `obtenerFiltrosDisponibles()` - Lista de filtros disponibles
- `obtenerRangoFechas()` - Calcula rangos de fechas
- `obtenerRangoAnterior()` - Calcula período anterior para comparación
- `obtenerDatosPeriodo()` - Obtiene métricas del período actual
- `obtenerDatosPeriodoAnterior()` - Obtiene métricas del período anterior
- `obtenerComparativa()` - Calcula variaciones porcentuales
- `calcularVariacion()` - Fórmula de cálculo de variación
- `contarAlumnosNuevos()` - Identifica alumnos nuevos
- `obtenerDistribucionProgramasRango()` - Distribución por programas
- `obtenerEvolucionMensualRango()` - Evolución mes a mes
- `obtenerTendencias()` - Tendencias de 12 meses
- `obtenerCrecimientoPorPrograma()` - Crecimiento por programa
- `obtenerProyeccion()` - Proyección simple basada en promedio
- `obtenerListadoAlumnos()` - Listado paginado de alumnos

### 2. Clase de Exportación
**Archivo**: `app/Exports/ReportesMatriculaExport.php`

**Clases**:
- `ReportesMatriculaExport` - Clase principal (multi-sheet)
- `ResumenSheet` - Hoja de resumen ejecutivo
- `ListadoAlumnosSheet` - Hoja de listado de alumnos
- `DistribucionProgramasSheet` - Hoja de distribución por programas

### 3. Vista PDF
**Archivo**: `resources/views/pdf/reportes-matricula.blade.php`

Template Blade para generación de PDFs con:
- Encabezado profesional
- Resumen ejecutivo
- Tablas de datos
- Distribución por programas
- Listado de alumnos
- Pie de página

### 4. Rutas API
**Archivo**: `routes/api.php`

```php
Route::prefix('administracion')->middleware('auth:sanctum')->group(function () {
    // ... rutas existentes
    
    // Nuevas rutas de reportes de matrícula
    Route::get('/reportes-matricula', [AdministracionController::class, 'reportesMatricula']);
    Route::post('/reportes-matricula/exportar', [AdministracionController::class, 'exportarReportesMatricula']);
});
```

### 5. Tests
**Archivo**: `tests/Feature/ReportesMatriculaTest.php`

Suite de tests completa con 15+ casos de prueba incluyendo:
- Autenticación
- Filtrado por programa
- Filtrado por tipo de alumno
- Rangos de fecha
- Validación de parámetros
- Paginación
- Exportación en diferentes formatos

## Uso Rápido

### Consultar Reporte (GET)
```bash
# Reporte del mes actual
curl -X GET "https://api.example.com/api/administracion/reportes-matricula" \
  -H "Authorization: Bearer TOKEN"

# Reporte del trimestre filtrando por programa
curl -X GET "https://api.example.com/api/administracion/reportes-matricula?rango=quarter&programaId=5" \
  -H "Authorization: Bearer TOKEN"

# Reporte personalizado de alumnos nuevos
curl -X GET "https://api.example.com/api/administracion/reportes-matricula?rango=custom&fechaInicio=2025-01-01&fechaFin=2025-03-31&tipoAlumno=Nuevo" \
  -H "Authorization: Bearer TOKEN"
```

### Exportar Reporte (POST)
```bash
# Exportar a PDF
curl -X POST "https://api.example.com/api/administracion/reportes-matricula/exportar" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"formato":"pdf","detalle":"complete","rango":"month"}'

# Exportar a Excel solo datos
curl -X POST "https://api.example.com/api/administracion/reportes-matricula/exportar" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"formato":"excel","detalle":"data","rango":"quarter"}'

# Exportar a CSV resumen
curl -X POST "https://api.example.com/api/administracion/reportes-matricula/exportar" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"formato":"csv","detalle":"summary","rango":"semester"}'
```

## Parámetros de Consulta

### Rangos de Fecha (`rango`)
| Valor | Descripción | Requiere Fechas |
|-------|-------------|-----------------|
| `month` | Mes actual | No |
| `quarter` | Trimestre actual (Q1-Q4) | No |
| `semester` | Semestre actual (S1 o S2) | No |
| `year` | Año actual | No |
| `custom` | Rango personalizado | Sí (fechaInicio y fechaFin) |

### Filtros Adicionales
- `programaId`: ID del programa o `"all"` (por defecto: `"all"`)
- `tipoAlumno`: `"all"`, `"Nuevo"`, `"Recurrente"` (por defecto: `"all"`)
- `page`: Número de página >= 1 (por defecto: 1)
- `perPage`: Registros por página 1-100 (por defecto: 50)

### Formatos de Exportación
- `pdf`: Documento PDF profesional
- `excel`: Archivo Excel (.xlsx) con múltiples hojas
- `csv`: Archivo CSV simple

### Niveles de Detalle
- `complete`: Todo (resumen + listado + distribución)
- `summary`: Solo métricas y resumen
- `data`: Solo listado de alumnos

## Lógica de Negocio Clave

### Identificación de Alumnos Nuevos
```sql
-- Un alumno es "nuevo" si su PRIMERA matrícula está en el rango
SELECT DISTINCT ep1.prospecto_id
FROM estudiante_programa ep1
JOIN (
    SELECT prospecto_id, MIN(created_at) as primera_matricula
    FROM estudiante_programa
    WHERE deleted_at IS NULL
    GROUP BY prospecto_id
) ep2 ON ep1.prospecto_id = ep2.prospecto_id
WHERE ep2.primera_matricula BETWEEN ? AND ?
```

### Cálculo de Variación Porcentual
```php
// Fórmula básica
$variacion = (($actual - $anterior) / $anterior) * 100;

// Casos especiales:
if ($anterior == 0 && $actual > 0) return 100;  // 0 → positivo = +100%
if ($anterior == 0 && $actual == 0) return 0;   // 0 → 0 = 0%
```

### Período Anterior Automático
El sistema calcula automáticamente el período anterior con la misma duración:
- Si el actual es marzo → anterior es febrero
- Si el actual es Q2 → anterior es Q1
- Si el actual es rango custom de 30 días → anterior son los 30 días previos

## Estructura de Respuesta JSON

```json
{
  "filtros": { ... },           // Filtros disponibles
  "periodoActual": {
    "rango": { ... },           // Fechas y descripción del período
    "totales": { ... },         // Contadores principales
    "distribucionProgramas": [],// Array de distribución por programa
    "evolucionMensual": [],     // Array de evolución mes a mes
    "distribucionTipo": []      // Array Nuevo vs Recurrente
  },
  "periodoAnterior": {
    "totales": { ... },         // Totales del período anterior
    "rangoComparado": { ... }   // Fechas del período anterior
  },
  "comparativa": {
    "totales": { ... },         // Comparativa con variaciones
    "nuevos": { ... },
    "recurrentes": { ... }
  },
  "tendencias": {
    "ultimosDoceMeses": [],     // Últimos 12 meses
    "crecimientoPorPrograma": [],// Crecimiento por programa
    "proyeccion": []            // Proyección simple
  },
  "listado": {
    "alumnos": [],              // Array paginado de alumnos
    "paginacion": { ... }       // Info de paginación
  }
}
```

## Validaciones Implementadas

```php
[
    'rango' => 'nullable|in:month,quarter,semester,year,custom',
    'fechaInicio' => 'nullable|date|required_if:rango,custom',
    'fechaFin' => 'nullable|date|required_if:rango,custom|after_or_equal:fechaInicio',
    'programaId' => 'nullable|string',
    'tipoAlumno' => 'nullable|in:all,Nuevo,Recurrente',
    'page' => 'nullable|integer|min:1',
    'perPage' => 'nullable|integer|min:1|max:100',
    
    // Para exportación
    'formato' => 'required|in:pdf,excel,csv',
    'detalle' => 'nullable|in:complete,summary,data',
    'incluirGraficas' => 'nullable|boolean'
]
```

## Auditoría y Logging

Cada exportación se registra automáticamente:

```php
Log::info('Exportación de reportes de matrícula', [
    'user_id' => auth()->id(),
    'formato' => 'pdf|excel|csv',
    'detalle' => 'complete|summary|data',
    'filtros' => [...]
]);
```

## Tests - Comandos Útiles

```bash
# Ejecutar solo los tests de reportes de matrícula
php artisan test --filter ReportesMatriculaTest

# Con output detallado
php artisan test --filter ReportesMatriculaTest --verbose

# Test específico
php artisan test --filter ReportesMatriculaTest::it_filters_by_program
```

## Performance Tips

1. **Índices de Base de Datos** (recomendado):
```sql
-- Para acelerar consultas por fecha
CREATE INDEX idx_ep_created_at ON estudiante_programa(created_at);
CREATE INDEX idx_ep_programa_id ON estudiante_programa(programa_id);
CREATE INDEX idx_ep_prospecto_id ON estudiante_programa(prospecto_id);
```

2. **Paginación**: Siempre use paginación para grandes volúmenes
3. **Filtros**: Aplique filtros específicos para reducir datos
4. **Caché**: Considere cachear programas activos si no cambian frecuentemente

## Troubleshooting

### Error: "Unauthenticated"
**Causa**: Falta token de autenticación  
**Solución**: Incluir header `Authorization: Bearer {token}`

### Error: "fechaInicio is required when rango is custom"
**Causa**: Rango custom sin fechas  
**Solución**: Proporcionar fechaInicio y fechaFin

### Resultados vacíos
**Causas posibles**:
- No hay datos en el rango seleccionado
- Filtros muy restrictivos
- Programa sin estudiantes

**Solución**: Verificar datos en base de datos y ajustar filtros

### Exportación lenta
**Causas**:
- Muchos registros sin paginación
- Consultas no optimizadas

**Solución**:
- Usar filtros más específicos
- Reducir rango de fechas
- Nivel de detalle `summary` en lugar de `complete`

## Próximas Mejoras (Roadmap)

1. ☐ Endpoint adicional para listar programas disponibles
2. ☐ Webhooks para notificaciones de reportes generados
3. ☐ Caché de métricas pre-calculadas
4. ☐ Gráficas incluidas en PDF
5. ☐ Exportación asíncrona para reportes grandes
6. ☐ Filtro por asesor/vendedor
7. ☐ Comparación de múltiples períodos
8. ☐ Dashboard web integrado

## Contacto y Soporte

Para dudas o problemas con la implementación:
- Revisar documentación completa en `REPORTES_MATRICULA_API_DOCS.md`
- Ejecutar tests para verificar funcionalidad
- Consultar logs del sistema en `storage/logs/laravel.log`

---

**Versión**: 1.0.0  
**Fecha de Implementación**: Octubre 2025  
**Desarrollado por**: GitHub Copilot Agent
