# Dashboard Administrativo - Documentación

## Descripción General

El endpoint del Dashboard Administrativo proporciona estadísticas y métricas clave para el módulo de administración del sistema académico. Está diseñado para alimentar un dashboard visual con información en tiempo real sobre matrículas, estudiantes, cursos y graduaciones.

## Endpoints

### 1. Dashboard Principal

**GET** `/api/administracion/dashboard`

Retorna todas las estadísticas principales del dashboard administrativo.

#### Autenticación
Requiere autenticación via `auth:sanctum`

#### Parámetros de Query (Opcionales)
- `periodo` (string): Para la evolución de matrícula. Valores: `6meses`, `1año`, `todo`. Por defecto: `6meses`

#### Respuesta Exitosa (200 OK)

```json
{
  "matriculas": {
    "total": 245,
    "mesAnterior": 219,
    "porcentajeCambio": 11.87
  },
  "alumnosNuevos": {
    "total": 87,
    "mesAnterior": 83,
    "porcentajeCambio": 4.82
  },
  "proximosInicios": {
    "total": 12,
    "cursos": 8,
    "periodos": 4,
    "proximos30Dias": true
  },
  "graduaciones": {
    "total": 34,
    "proximoTrimestre": true,
    "fechaInicio": "2025-10-10",
    "fechaFin": "2026-01-10"
  },
  "evolucionMatricula": [
    {
      "mes": "may 2025",
      "total": 42
    },
    {
      "mes": "jun 2025",
      "total": 51
    },
    {
      "mes": "jul 2025",
      "total": 38
    }
    // ... más meses
  ],
  "distribucionProgramas": [
    {
      "programa": "Maestría en Business Analytics",
      "abreviatura": "MBA",
      "totalEstudiantes": 156
    },
    {
      "programa": "Diplomado en Gestión de Proyectos",
      "abreviatura": "DGP",
      "totalEstudiantes": 89
    }
    // ... más programas
  ],
  "notificaciones": {
    "solicitudesPendientes": {
      "total": 15,
      "mensaje": "Hay 15 solicitudes pendientes de revisión"
    },
    "graduacionesProximas": {
      "total": 34,
      "mensaje": "34 alumnos se graduarán en el próximo trimestre"
    },
    "cursosPorFinalizar": {
      "total": 8,
      "mensaje": "8 cursos finalizarán en los próximos 15 días"
    }
  },
  "estadisticas": {
    "totalEstudiantes": 523,
    "totalProgramas": 12,
    "totalCursos": 145,
    "estudiantesEnMultiplesProgramas": {
      "total": 78,
      "promedio": 2.3,
      "maximo": 4,
      "top5": [
        {
          "prospecto_id": 123,
          "total_programas": 4
        },
        {
          "prospecto_id": 456,
          "total_programas": 3
        }
        // ... top 5
      ]
    }
  }
}
```

#### Errores

**500 Internal Server Error**
```json
{
  "error": "Error al obtener datos del dashboard",
  "message": "Descripción del error",
  "debug": "Stack trace (solo en modo debug)"
}
```

---

### 2. Exportar Dashboard

**GET** `/api/administracion/dashboard/exportar`

Exporta los datos del dashboard en diferentes formatos.

#### Autenticación
Requiere autenticación via `auth:sanctum`

#### Parámetros de Query
- `formato` (string): Formato de exportación. Valores: `json`, `excel`, `pdf`. Por defecto: `json`
- `periodo` (string): Igual que en el endpoint principal

#### Respuesta Exitosa (200 OK)

```json
{
  "formato": "json",
  "datos": {
    // ... mismo formato que el dashboard principal
  },
  "exportado_en": "2025-10-10 15:30:45"
}
```

---

## Métricas Detalladas

### Matrículas del Mes
- **Total**: Número de matrículas creadas en el mes actual
- **Mes Anterior**: Número de matrículas del mes previo
- **Porcentaje de Cambio**: Cambio porcentual entre ambos períodos

### Alumnos Nuevos
- Cuenta prospectos que han sido convertidos a estudiantes (tienen al menos un programa asignado)
- Compara mes actual vs mes anterior

### Próximos Inicios
- **Cursos**: Cursos que inician en los próximos 30 días
- **Periodos**: Periodos de inscripción que inician en los próximos 30 días
- Solo considera cursos no cancelados

### Graduaciones
- Estudiantes cuya fecha de finalización de programa cae en el próximo trimestre (3 meses)
- Incluye solo registros con fecha_fin válida

### Evolución de Matrícula
Datos mensuales históricos:
- **6 meses**: Últimos 6 meses
- **1 año**: Últimos 12 meses
- **todo**: Últimos 5 años

### Distribución por Programas
- Lista de programas ordenados por número de estudiantes
- Incluye nombre completo, abreviatura y total de estudiantes

### Notificaciones Importantes
1. **Solicitudes Pendientes**: Prospectos con status "Seguimiento" o "En Proceso"
2. **Graduaciones Próximas**: Estudiantes que finalizan en el próximo trimestre
3. **Cursos por Finalizar**: Cursos que terminan en los próximos 15 días

### Estadísticas Generales
- **Total Estudiantes**: Prospectos con al menos un programa
- **Total Programas**: Programas activos en el sistema
- **Total Cursos**: Todos los cursos registrados
- **Estudiantes en Múltiples Programas**: 
  - Total de estudiantes inscritos en más de un programa
  - Promedio de programas por estudiante
  - Máximo de programas por estudiante
  - Top 5 estudiantes con más programas

---

## Ejemplos de Uso

### Obtener Dashboard con Evolución de 1 Año

```bash
curl -X GET \
  'https://api.example.com/api/administracion/dashboard?periodo=1año' \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Accept: application/json'
```

### Exportar Dashboard en JSON

```bash
curl -X GET \
  'https://api.example.com/api/administracion/dashboard/exportar?formato=json' \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Accept: application/json'
```

---

## Modelos Utilizados

El endpoint utiliza los siguientes modelos de Eloquent:

- `EstudiantePrograma`: Matrículas y relación estudiante-programa
- `Programa`: Programas académicos
- `Course`: Cursos del sistema
- `Prospecto`: Prospectos/estudiantes
- `PeriodoInscripcion`: Periodos de inscripción
- `InscripcionPeriodo`: Inscripciones a periodos específicos

---

## Notas de Implementación

1. **Rendimiento**: Las consultas están optimizadas con agregaciones a nivel de base de datos
2. **Cache**: Se recomienda implementar cache para el dashboard (no incluido en esta versión)
3. **Permisos**: El endpoint está protegido con `auth:sanctum`, se recomienda añadir control de roles
4. **Exportación**: Los formatos Excel y PDF requieren implementación adicional (actualmente solo JSON)

---

## Compatibilidad con la UI del Dashboard

Este endpoint está diseñado para alimentar una interfaz de usuario que incluye:

- **Tarjetas de Métricas**: Matrículas, Alumnos Nuevos, Próximos Inicios, Graduaciones
- **Gráficas**: Evolución de matrícula mensual y distribución por programas
- **Accesos Rápidos**: Enlaces a reportes, programación, graduaciones
- **Notificaciones**: Alertas de solicitudes pendientes, graduaciones próximas, cursos por finalizar
- **Botones de Acción**: Exportar datos, imprimir

La estructura de datos retornada está lista para ser consumida directamente por componentes de React, Vue o Angular.
