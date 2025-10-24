# 📊 Guía: Distribución de Estudiantes por Programas

## 🎯 Objetivo
Esta guía explica cómo consumir correctamente los datos de distribución de estudiantes por programa desde el dashboard administrativo.

## 🔍 Problema Resuelto
Se corrigió el bug donde la sección "Distribución por Programas" mostraba 0% para todos los programas y no cargaba adecuadamente los estudiantes asignados.

### ⚠️ Causa del Problema
- El query original hacía un `LEFT JOIN` simple que no excluía registros con soft deletes
- No se filtraban programas inactivos
- No se consideraba la relación correcta entre las tablas `tb_programas`, `estudiante_programa` y `prospectos`

### ✅ Solución Implementada
1. **Filtrado de Soft Deletes**: Se agregó la condición `whereNull('estudiante_programa.deleted_at')` para excluir estudiantes dados de baja
2. **Filtrado de Programas Activos**: Se agregó `where('tb_programas.activo', 1)` para mostrar solo programas activos
3. **Conteo Correcto**: Se usa `COUNT(DISTINCT estudiante_programa.id)` para evitar duplicados
4. **Join con Prospectos**: Se agregó join con la tabla `prospectos` para asegurar la integridad de los datos
5. **Ordenamiento Mejorado**: Se ordena por cantidad de estudiantes y luego por nombre de programa

## 📡 Endpoint

### URL
```
GET /api/administracion/dashboard
```

### Autenticación
Requiere token Bearer (Sanctum):
```bash
Authorization: Bearer {your_token}
```

### Headers
```
Accept: application/json
Content-Type: application/json
```

## 📊 Estructura de Datos

### Respuesta Completa del Dashboard
```json
{
  "matriculas": {...},
  "alumnosNuevos": {...},
  "proximosInicios": {...},
  "graduaciones": {...},
  "evolucionMatricula": [...],
  "distribucionProgramas": [
    {
      "programa": "Bachelor of Business Administration",
      "abreviatura": "BBA",
      "totalEstudiantes": 45
    },
    {
      "programa": "Master of Business Administration",
      "abreviatura": "MBA",
      "totalEstudiantes": 32
    },
    {
      "programa": "Master of Marketing in Commercial Management",
      "abreviatura": "MMCM",
      "totalEstudiantes": 28
    }
  ],
  "notificaciones": {...},
  "estadisticas": {...}
}
```

### Estructura de `distribucionProgramas`
Cada elemento del array contiene:

| Campo | Tipo | Descripción | Ejemplo |
|-------|------|-------------|---------|
| `programa` | string | Nombre completo del programa | "Bachelor of Business Administration" |
| `abreviatura` | string | Abreviatura del programa | "BBA" |
| `totalEstudiantes` | integer | Cantidad de estudiantes activos en el programa | 45 |

**Notas Importantes:**
- Los programas están ordenados por cantidad de estudiantes (descendente)
- Solo se incluyen programas activos (`activo = 1` en `tb_programas`)
- Solo se cuentan estudiantes NO eliminados (sin `deleted_at` en `estudiante_programa`)
- Los programas sin estudiantes mostrarán `totalEstudiantes: 0`

## 💻 Ejemplos de Integración

### JavaScript/Fetch
```javascript
async function obtenerDistribucionProgramas() {
  try {
    const response = await fetch('/api/administracion/dashboard', {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json'
      }
    });
    
    const data = await response.json();
    const distribucion = data.distribucionProgramas;
    
    // Mostrar distribución
    distribucion.forEach(programa => {
      console.log(`${programa.abreviatura}: ${programa.totalEstudiantes} estudiantes`);
    });
    
    return distribucion;
  } catch (error) {
    console.error('Error al obtener distribución:', error);
  }
}
```

### React Hook
```javascript
import { useState, useEffect } from 'react';

function useDistribucionProgramas() {
  const [programas, setProgramas] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  
  useEffect(() => {
    async function fetchData() {
      try {
        const response = await fetch('/api/administracion/dashboard', {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Accept': 'application/json'
          }
        });
        
        if (!response.ok) {
          throw new Error('Error al cargar datos');
        }
        
        const data = await response.json();
        setProgramas(data.distribucionProgramas);
      } catch (err) {
        setError(err.message);
      } finally {
        setLoading(false);
      }
    }
    
    fetchData();
  }, []);
  
  return { programas, loading, error };
}

// Uso en componente
function DistribucionProgramas() {
  const { programas, loading, error } = useDistribucionProgramas();
  
  if (loading) return <div>Cargando...</div>;
  if (error) return <div>Error: {error}</div>;
  
  return (
    <div className="distribucion-programas">
      <h2>Distribución por Programas</h2>
      {programas.map((programa, index) => (
        <div key={index} className="programa-item">
          <h3>{programa.programa}</h3>
          <p>Abreviatura: {programa.abreviatura}</p>
          <p>Estudiantes: {programa.totalEstudiantes}</p>
        </div>
      ))}
    </div>
  );
}
```

### Vue 3 Composition API
```javascript
import { ref, onMounted } from 'vue';

export function useDistribucionProgramas() {
  const programas = ref([]);
  const loading = ref(true);
  const error = ref(null);
  
  const fetchDistribucion = async () => {
    try {
      loading.value = true;
      const response = await fetch('/api/administracion/dashboard', {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
          'Accept': 'application/json'
        }
      });
      
      if (!response.ok) {
        throw new Error('Error al cargar datos');
      }
      
      const data = await response.json();
      programas.value = data.distribucionProgramas;
    } catch (err) {
      error.value = err.message;
    } finally {
      loading.value = false;
    }
  };
  
  onMounted(() => {
    fetchDistribucion();
  });
  
  return {
    programas,
    loading,
    error,
    refresh: fetchDistribucion
  };
}
```

### Angular Service
```typescript
import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';

interface ProgramaDistribucion {
  programa: string;
  abreviatura: string;
  totalEstudiantes: number;
}

interface DashboardResponse {
  distribucionProgramas: ProgramaDistribucion[];
  // ... otros campos
}

@Injectable({ providedIn: 'root' })
export class DistribucionProgramasService {
  private apiUrl = '/api/administracion/dashboard';
  
  constructor(private http: HttpClient) {}
  
  getDistribucionProgramas(): Observable<ProgramaDistribucion[]> {
    return this.http.get<DashboardResponse>(this.apiUrl).pipe(
      map(response => response.distribucionProgramas)
    );
  }
}

// Uso en componente
import { Component, OnInit } from '@angular/core';

@Component({
  selector: 'app-distribucion-programas',
  template: `
    <div class="distribucion-programas">
      <h2>Distribución por Programas</h2>
      <div *ngIf="loading">Cargando...</div>
      <div *ngIf="error">Error: {{ error }}</div>
      <div *ngFor="let programa of programas" class="programa-item">
        <h3>{{ programa.programa }}</h3>
        <p>{{ programa.abreviatura }}: {{ programa.totalEstudiantes }} estudiantes</p>
      </div>
    </div>
  `
})
export class DistribucionProgramasComponent implements OnInit {
  programas: ProgramaDistribucion[] = [];
  loading = true;
  error: string | null = null;
  
  constructor(private service: DistribucionProgramasService) {}
  
  ngOnInit() {
    this.service.getDistribucionProgramas().subscribe({
      next: (data) => {
        this.programas = data;
        this.loading = false;
      },
      error: (err) => {
        this.error = err.message;
        this.loading = false;
      }
    });
  }
}
```

## 📈 Visualización con Gráficas

### Chart.js - Gráfica de Barras
```javascript
import Chart from 'chart.js/auto';

async function crearGraficaDistribucion() {
  const response = await fetch('/api/administracion/dashboard', {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json'
    }
  });
  
  const data = await response.json();
  const distribucion = data.distribucionProgramas;
  
  const ctx = document.getElementById('chartDistribucion').getContext('2d');
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: distribucion.map(p => p.abreviatura),
      datasets: [{
        label: 'Estudiantes por Programa',
        data: distribucion.map(p => p.totalEstudiantes),
        backgroundColor: [
          'rgba(255, 99, 132, 0.7)',
          'rgba(54, 162, 235, 0.7)',
          'rgba(255, 206, 86, 0.7)',
          'rgba(75, 192, 192, 0.7)',
          'rgba(153, 102, 255, 0.7)',
          'rgba(255, 159, 64, 0.7)'
        ],
        borderColor: [
          'rgba(255, 99, 132, 1)',
          'rgba(54, 162, 235, 1)',
          'rgba(255, 206, 86, 1)',
          'rgba(75, 192, 192, 1)',
          'rgba(153, 102, 255, 1)',
          'rgba(255, 159, 64, 1)'
        ],
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      plugins: {
        title: {
          display: true,
          text: 'Distribución de Estudiantes por Programa'
        },
        tooltip: {
          callbacks: {
            label: function(context) {
              const programa = distribucion[context.dataIndex];
              return `${programa.programa}: ${context.parsed.y} estudiantes`;
            }
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          title: {
            display: true,
            text: 'Cantidad de Estudiantes'
          }
        }
      }
    }
  });
}
```

### Chart.js - Gráfica de Pastel/Dona
```javascript
async function crearGraficaPastel() {
  const response = await fetch('/api/administracion/dashboard', {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json'
    }
  });
  
  const data = await response.json();
  const distribucion = data.distribucionProgramas.filter(p => p.totalEstudiantes > 0);
  
  const ctx = document.getElementById('chartPastel').getContext('2d');
  new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: distribucion.map(p => p.abreviatura),
      datasets: [{
        label: 'Distribución',
        data: distribucion.map(p => p.totalEstudiantes),
        backgroundColor: [
          '#FF6384',
          '#36A2EB',
          '#FFCE56',
          '#4BC0C0',
          '#9966FF',
          '#FF9F40',
          '#FF6384',
          '#C9CBCF'
        ],
        hoverOffset: 4
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: {
          position: 'right',
        },
        title: {
          display: true,
          text: 'Distribución Porcentual por Programa'
        },
        tooltip: {
          callbacks: {
            label: function(context) {
              const programa = distribucion[context.dataIndex];
              const total = distribucion.reduce((sum, p) => sum + p.totalEstudiantes, 0);
              const porcentaje = ((programa.totalEstudiantes / total) * 100).toFixed(1);
              return `${programa.programa}: ${programa.totalEstudiantes} (${porcentaje}%)`;
            }
          }
        }
      }
    }
  });
}
```

## 🔧 Cálculos Útiles

### Calcular Porcentajes
```javascript
function calcularPorcentajes(distribucion) {
  const total = distribucion.reduce((sum, p) => sum + p.totalEstudiantes, 0);
  
  return distribucion.map(programa => ({
    ...programa,
    porcentaje: total > 0 ? ((programa.totalEstudiantes / total) * 100).toFixed(2) : 0
  }));
}

// Uso
const data = await fetch('/api/administracion/dashboard').then(r => r.json());
const programasConPorcentaje = calcularPorcentajes(data.distribucionProgramas);

programasConPorcentaje.forEach(p => {
  console.log(`${p.programa}: ${p.totalEstudiantes} estudiantes (${p.porcentaje}%)`);
});
```

### Filtrar Top N Programas
```javascript
function obtenerTopProgramas(distribucion, n = 5) {
  return distribucion
    .filter(p => p.totalEstudiantes > 0)
    .slice(0, n);
}

// Uso
const data = await fetch('/api/administracion/dashboard').then(r => r.json());
const top5 = obtenerTopProgramas(data.distribucionProgramas, 5);
```

### Agrupar Programas Pequeños como "Otros"
```javascript
function agruparProgramasPequenos(distribucion, minimoEstudiantes = 5) {
  const principales = distribucion.filter(p => p.totalEstudiantes >= minimoEstudiantes);
  const pequenos = distribucion.filter(p => p.totalEstudiantes < minimoEstudiantes && p.totalEstudiantes > 0);
  
  if (pequenos.length > 0) {
    const totalOtros = pequenos.reduce((sum, p) => sum + p.totalEstudiantes, 0);
    principales.push({
      programa: 'Otros Programas',
      abreviatura: 'OTROS',
      totalEstudiantes: totalOtros
    });
  }
  
  return principales;
}
```

## 🗄️ Estructura de Base de Datos

### Tablas Involucradas

#### 1. `tb_programas`
```sql
CREATE TABLE tb_programas (
    id BIGSERIAL PRIMARY KEY,
    abreviatura VARCHAR(50),
    nombre_del_programa VARCHAR(255),
    activo BOOLEAN DEFAULT true,
    -- ... otros campos
);
```

#### 2. `estudiante_programa`
```sql
CREATE TABLE estudiante_programa (
    id BIGSERIAL PRIMARY KEY,
    prospecto_id BIGINT,
    programa_id BIGINT,
    convenio_id BIGINT NULL,
    fecha_inicio DATE,
    fecha_fin DATE,
    duracion_meses INTEGER,
    inscripcion DECIMAL(12,2),
    cuota_mensual DECIMAL(12,2),
    inversion_total DECIMAL(14,2),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    deleted_at TIMESTAMP NULL, -- Soft delete
    -- ... otros campos
);
```

#### 3. `prospectos`
```sql
CREATE TABLE prospectos (
    id BIGSERIAL PRIMARY KEY,
    nombre_completo VARCHAR(255),
    correo_electronico VARCHAR(255),
    -- ... otros campos
);
```

### Query Implementado
```sql
SELECT 
    tb_programas.id,
    tb_programas.nombre_del_programa as nombre,
    tb_programas.abreviatura,
    COUNT(DISTINCT estudiante_programa.id) as total_estudiantes
FROM tb_programas
LEFT JOIN estudiante_programa 
    ON tb_programas.id = estudiante_programa.programa_id
    AND estudiante_programa.deleted_at IS NULL
LEFT JOIN prospectos 
    ON estudiante_programa.prospecto_id = prospectos.id
WHERE tb_programas.activo = 1
GROUP BY 
    tb_programas.id, 
    tb_programas.nombre_del_programa, 
    tb_programas.abreviatura
ORDER BY 
    total_estudiantes DESC,
    tb_programas.nombre_del_programa ASC;
```

## 🐛 Manejo de Errores

### Validaciones Recomendadas
```javascript
async function obtenerDistribucionSegura() {
  try {
    const response = await fetch('/api/administracion/dashboard', {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json'
      }
    });
    
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    const data = await response.json();
    
    // Validar que existe la propiedad
    if (!data.distribucionProgramas) {
      throw new Error('distribucionProgramas no está presente en la respuesta');
    }
    
    // Validar que es un array
    if (!Array.isArray(data.distribucionProgramas)) {
      throw new Error('distribucionProgramas no es un array');
    }
    
    // Validar estructura de cada elemento
    const valido = data.distribucionProgramas.every(p => 
      typeof p.programa === 'string' &&
      typeof p.abreviatura === 'string' &&
      typeof p.totalEstudiantes === 'number'
    );
    
    if (!valido) {
      throw new Error('Estructura de datos inválida');
    }
    
    return data.distribucionProgramas;
    
  } catch (error) {
    console.error('Error al obtener distribución:', error);
    // Retornar array vacío en caso de error
    return [];
  }
}
```

## 📝 Notas Adicionales

### Consideraciones Importantes
1. **Soft Deletes**: Los estudiantes dados de baja no se cuentan (tienen `deleted_at` no nulo)
2. **Programas Activos**: Solo se muestran programas con `activo = 1`
3. **Ordenamiento**: Los resultados vienen ordenados por cantidad de estudiantes (mayor a menor)
4. **Caché**: Considere implementar caché en el frontend para no sobrecargar el servidor
5. **Actualización**: Los datos reflejan el estado actual de la base de datos en tiempo real

### Performance
- El query está optimizado con índices en las columnas de join
- Se usa `COUNT(DISTINCT)` para evitar duplicados
- Se recomienda implementar caché de 5-10 minutos en el frontend

### Seguridad
- Requiere autenticación con Sanctum
- Los datos están filtrados por programas activos
- No expone información sensible de estudiantes

## 🔗 Enlaces Relacionados
- [DASHBOARD_ADMINISTRATIVO_RESUMEN.md](./DASHBOARD_ADMINISTRATIVO_RESUMEN.md) - Documentación completa del dashboard
- [DASHBOARD_README.md](./DASHBOARD_README.md) - Guía de uso general
- Modelos: `app/Models/Programa.php`, `app/Models/EstudiantePrograma.php`, `app/Models/Prospecto.php`

---

**Última actualización**: 2025-10-10  
**Versión**: 1.0.0  
**Autor**: Sistema ASM Backend
