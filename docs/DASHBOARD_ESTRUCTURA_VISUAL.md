# Estructura Visual del Dashboard Administrativo

```
┌─────────────────────────────────────────────────────────────────────────┐
│                     DASHBOARD ADMINISTRATIVO                             │
│                  GET /api/administracion/dashboard                       │
└─────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────┐
│  TARJETAS DE MÉTRICAS PRINCIPALES                                       │
├──────────────┬──────────────┬──────────────┬──────────────────────────┤
│ MATRÍCULAS  │ ALUMNOS      │ PRÓXIMOS     │ GRADUACIONES             │
│ DEL MES     │ NUEVOS       │ INICIOS      │                          │
│             │              │              │                          │
│    245      │     87       │     12       │      34                  │
│ +12% vs mes │  +5% vs mes  │ Próximos     │ Próximo trimestre       │
│  anterior   │  anterior    │  30 días     │                          │
└──────────────┴──────────────┴──────────────┴──────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────┐
│  GRÁFICAS                                                                │
├────────────────────────────────┬────────────────────────────────────────┤
│                                │                                        │
│  EVOLUCIÓN DE MATRÍCULA        │  DISTRIBUCIÓN POR PROGRAMAS            │
│  ═══════════════════════        │  ════════════════════════              │
│                                │                                        │
│   150 ┤        ╱╲              │   MBA        ████████ 156              │
│   120 ┤      ╱    ╲    ╱       │   DGP        █████ 89                 │
│    90 ┤    ╱      ╲  ╱         │   MAF        ████ 67                  │
│    60 ┤  ╱          ╲╱          │   MFI        ███ 54                   │
│    30 ┤╱                        │   Otros      ██ 157                   │
│     0 ┴────────────────────     │                                        │
│     May Jun Jul Ago Sep Oct     │   Total: 523 estudiantes              │
│                                │                                        │
│  Opciones: 6M | 1A | Todo      │                                        │
└────────────────────────────────┴────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────┐
│  ACCESOS RÁPIDOS                                                         │
├──────────────┬──────────────┬──────────────┬──────────────────────────┤
│ 📊 Reportes  │ 📅 Program.  │ 🎓 Reporte   │ 📧 Plantillas           │
│ de Matrícula │ de Cursos    │ Graduaciones │ y Mailing                │
└──────────────┴──────────────┴──────────────┴──────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────┐
│  NOTIFICACIONES IMPORTANTES                                              │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  ⚠️  Solicitudes Pendientes                                             │
│      Hay 15 solicitudes pendientes de revisión                          │
│      [ Ver solicitudes ]                                                 │
│                                                                          │
│  🎓  Graduaciones Próximas                                              │
│      34 alumnos se graduarán en el próximo trimestre                    │
│      [ Ver detalles ]                                                    │
│                                                                          │
│  📚  Cursos por Finalizar                                               │
│      8 cursos finalizarán en los próximos 15 días                       │
│      [ Programar evaluaciones ]                                          │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────┐
│  ESTADÍSTICAS ADICIONALES                                                │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  👥 Total Estudiantes: 523                                              │
│  📚 Total Programas: 12                                                 │
│  📖 Total Cursos: 145                                                   │
│                                                                          │
│  🎯 Estudiantes en Múltiples Programas:                                │
│     • Total: 78 estudiantes                                             │
│     • Promedio: 2.3 programas por estudiante                            │
│     • Máximo: 4 programas                                               │
│                                                                          │
│  Top 5 Estudiantes con Más Programas:                                   │
│  1️⃣  Prospecto #123 - 4 programas                                      │
│  2️⃣  Prospecto #456 - 3 programas                                      │
│  3️⃣  Prospecto #789 - 3 programas                                      │
│  4️⃣  Prospecto #234 - 3 programas                                      │
│  5️⃣  Prospecto #567 - 2 programas                                      │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────┐
│  ACCIONES                                                                │
├─────────────────────────────────────────────────────────────────────────┤
│  [ 📥 Exportar Datos ]  [ 🖨️  Imprimir ]                               │
└─────────────────────────────────────────────────────────────────────────┘
```

## Flujo de Datos

```
┌──────────────┐
│   Frontend   │
│  Dashboard   │
│   Component  │
└──────┬───────┘
       │
       │ HTTP GET /api/administracion/dashboard?periodo=6meses
       │ Authorization: Bearer {token}
       ▼
┌──────────────────────────────────────────────────────┐
│   AdministracionController::dashboard()              │
│                                                       │
│   ┌─────────────────────────────────────────────┐  │
│   │  1. obtenerMatriculas()                     │  │
│   │     └─> Query: EstudiantePrograma           │  │
│   │         • Mes actual vs mes anterior        │  │
│   │         • Calcular % cambio                 │  │
│   └─────────────────────────────────────────────┘  │
│                                                       │
│   ┌─────────────────────────────────────────────┐  │
│   │  2. obtenerAlumnosNuevos()                  │  │
│   │     └─> Query: Prospecto with programas     │  │
│   │         • Nuevos estudiantes del mes        │  │
│   └─────────────────────────────────────────────┘  │
│                                                       │
│   ┌─────────────────────────────────────────────┐  │
│   │  3. obtenerProximosInicios()                │  │
│   │     └─> Query: Course + PeriodoInscripcion  │  │
│   │         • Próximos 30 días                  │  │
│   └─────────────────────────────────────────────┘  │
│                                                       │
│   ┌─────────────────────────────────────────────┐  │
│   │  4. obtenerGraduaciones()                   │  │
│   │     └─> Query: EstudiantePrograma           │  │
│   │         • Fecha fin en próximo trimestre    │  │
│   └─────────────────────────────────────────────┘  │
│                                                       │
│   ┌─────────────────────────────────────────────┐  │
│   │  5. obtenerEvolucionMatricula(periodo)      │  │
│   │     └─> Query: EstudiantePrograma grouped   │  │
│   │         • Últimos 6, 12 o 60 meses          │  │
│   └─────────────────────────────────────────────┘  │
│                                                       │
│   ┌─────────────────────────────────────────────┐  │
│   │  6. obtenerDistribucionProgramas()          │  │
│   │     └─> Query: Programa LEFT JOIN           │  │
│   │         • Ordenado por más estudiantes      │  │
│   └─────────────────────────────────────────────┘  │
│                                                       │
│   ┌─────────────────────────────────────────────┐  │
│   │  7. obtenerNotificaciones()                 │  │
│   │     └─> Queries múltiples:                  │  │
│   │         • Prospectos pendientes             │  │
│   │         • Graduaciones próximas             │  │
│   │         • Cursos por finalizar              │  │
│   └─────────────────────────────────────────────┘  │
│                                                       │
│   ┌─────────────────────────────────────────────┐  │
│   │  8. obtenerEstadisticasGenerales()          │  │
│   │     └─> Queries:                            │  │
│   │         • Total estudiantes                 │  │
│   │         • Total programas                   │  │
│   │         • Múltiples inscripciones           │  │
│   │         • Top 5 estudiantes                 │  │
│   └─────────────────────────────────────────────┘  │
│                                                       │
└───────────────────────┬───────────────────────────────┘
                        │
                        │ JSON Response
                        ▼
                ┌───────────────┐
                │   Frontend    │
                │   Updates UI  │
                │  - Charts     │
                │  - Metrics    │
                │  - Alerts     │
                └───────────────┘
```

## Base de Datos - Tablas Utilizadas

```
┌──────────────────────┐
│   estudiante_programa │ ◄──┐
├──────────────────────┤    │
│ id                   │    │
│ prospecto_id         │───┐│
│ programa_id          │  ││
│ fecha_inicio         │  ││
│ fecha_fin            │  ││
│ created_at           │  ││
└──────────────────────┘  ││
                          ││
┌──────────────────────┐  ││
│      prospectos       │ ◄┘│
├──────────────────────┤   │
│ id                   │   │
│ nombre_completo      │   │
│ status               │   │
│ created_at           │   │
└──────────────────────┘   │
                           │
┌──────────────────────┐   │
│    tb_programas       │ ◄─┘
├──────────────────────┤
│ id                   │
│ nombre_del_programa  │
│ abreviatura          │
│ activo               │
└──────────────────────┘

┌──────────────────────┐
│       courses         │
├──────────────────────┤
│ id                   │
│ name                 │
│ start_date           │
│ end_date             │
│ status               │
└──────────────────────┘

┌─────────────────────────────┐
│  tb_periodos_inscripcion    │
├─────────────────────────────┤
│ id                          │
│ nombre                      │
│ fecha_inicio                │
│ fecha_fin                   │
│ activo                      │
└─────────────────────────────┘
```

## Respuesta JSON - Estructura Completa

```json
{
  "matriculas": {
    "total": number,
    "mesAnterior": number,
    "porcentajeCambio": number
  },
  "alumnosNuevos": {
    "total": number,
    "mesAnterior": number,
    "porcentajeCambio": number
  },
  "proximosInicios": {
    "total": number,
    "cursos": number,
    "periodos": number,
    "proximos30Dias": true
  },
  "graduaciones": {
    "total": number,
    "proximoTrimestre": true,
    "fechaInicio": "YYYY-MM-DD",
    "fechaFin": "YYYY-MM-DD"
  },
  "evolucionMatricula": [
    {
      "mes": "MMM YYYY",
      "total": number
    }
  ],
  "distribucionProgramas": [
    {
      "programa": "string",
      "abreviatura": "string",
      "totalEstudiantes": number
    }
  ],
  "notificaciones": {
    "solicitudesPendientes": {
      "total": number,
      "mensaje": "string"
    },
    "graduacionesProximas": {
      "total": number,
      "mensaje": "string"
    },
    "cursosPorFinalizar": {
      "total": number,
      "mensaje": "string"
    }
  },
  "estadisticas": {
    "totalEstudiantes": number,
    "totalProgramas": number,
    "totalCursos": number,
    "estudiantesEnMultiplesProgramas": {
      "total": number,
      "promedio": number,
      "maximo": number,
      "top5": [
        {
          "prospecto_id": number,
          "total_programas": number
        }
      ]
    }
  }
}
```

## Integración con Frontend - Ejemplo React

```jsx
import React from 'react';
import { useDashboard } from './hooks/useDashboard';
import MetricCard from './components/MetricCard';
import EvolutionChart from './components/EvolutionChart';
import ProgramDistribution from './components/ProgramDistribution';
import NotificationPanel from './components/NotificationPanel';

function DashboardAdministrativo() {
  const { data, loading, error } = useDashboard('6meses');

  if (loading) return <LoadingSpinner />;
  if (error) return <ErrorMessage error={error} />;

  return (
    <div className="dashboard-container">
      {/* Métricas Principales */}
      <div className="metrics-row">
        <MetricCard 
          title="Matrículas del Mes"
          value={data.matriculas.total}
          change={data.matriculas.porcentajeCambio}
          icon="📊"
        />
        <MetricCard 
          title="Alumnos Nuevos"
          value={data.alumnosNuevos.total}
          change={data.alumnosNuevos.porcentajeCambio}
          icon="👥"
        />
        <MetricCard 
          title="Próximos Inicios"
          value={data.proximosInicios.total}
          subtitle="Próximos 30 días"
          icon="📅"
        />
        <MetricCard 
          title="Graduaciones"
          value={data.graduaciones.total}
          subtitle="Próximo trimestre"
          icon="🎓"
        />
      </div>

      {/* Gráficas */}
      <div className="charts-row">
        <EvolutionChart data={data.evolucionMatricula} />
        <ProgramDistribution data={data.distribucionProgramas} />
      </div>

      {/* Notificaciones */}
      <NotificationPanel data={data.notificaciones} />

      {/* Estadísticas Adicionales */}
      <EstadisticasPanel data={data.estadisticas} />
    </div>
  );
}
```
