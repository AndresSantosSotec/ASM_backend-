# 📊 Guía de Integración: Dashboard de Cuotas - Frontend

## 🎯 Resumen Ejecutivo

El backend ya está funcionando correctamente con **PAGINACIÓN COMPLETA** y retorna **todos los estudiantes activos** (2,959 registros) con sus cuotas, saldos y estados de pago. El frontend debe consumir el endpoint `/api/mantenimientos/cuotas/dashboard` para mostrar esta información de forma paginada.

---

## 🔗 Endpoint Principal

### GET `/api/mantenimientos/cuotas/dashboard`

**URL completa:** `http://localhost:8000/api/mantenimientos/cuotas/dashboard`

**Headers requeridos:**
```http
Authorization: Bearer {TOKEN}
Content-Type: application/json
```

**Parámetros opcionales (Query Params):**
```javascript
{
  page: 1,                 // Número de página (default: 1)
  per_page: 100,           // Registros por página (default: 100, max: 500)
  search: "ASM2020",       // Búsqueda por carnet, nombre o correo
  programa_id: 18,         // Filtrar por programa específico
  prospecto_id: 123        // Filtrar por prospecto específico
}
```

**Ejemplos de URLs:**
```
# Primera página con 50 registros
GET /api/mantenimientos/cuotas/dashboard?page=1&per_page=50

# Segunda página con 100 registros
GET /api/mantenimientos/cuotas/dashboard?page=2&per_page=100

# Buscar estudiantes con "ASM2021"
GET /api/mantenimientos/cuotas/dashboard?search=ASM2021&per_page=50

# Filtrar por programa
GET /api/mantenimientos/cuotas/dashboard?programa_id=18&page=1&per_page=100
```

---

## 📦 Estructura de la Respuesta

### Respuesta Completa (JSON) con Paginación

```json
{
  "timestamp": "2025-10-23T18:41:57+00:00",
  "filters": {
    "prospecto_id": null,
    "programa_id": null,
    "search": null,
    "fecha_inicio": null,
    "fecha_fin": null
  },
  "pagination": {
    "current_page": 1,
    "per_page": 100,
    "total": 2959,
    "total_pages": 30,
    "from": 1,
    "to": 100,
    "has_more": true
  },
  "summary": {
    "estudiantes_activos": 2959,
    "saldo_estimado": 22500.00,
    "en_mora": 0,
    "planes_reestructurados": 0
  },
  "estudiantes": [
    {
      "estudiante_programa_id": 1,
      "prospecto": {
        "id": 1,
        "nombre": "Marta Julia de León Bolaños",
        "carnet": "ASM2020126",
        "correo": "20mjdel1@gmail.com",
        "telefono": "58794155"
      },
      "programa": {
        "id": 18,
        "nombre": "Master of Business Administration"
      },
      "saldo_pendiente": 4500.00,
      "cuotas_pendientes": 9,
      "cuotas_pagadas": 3,
      "proxima_cuota": {
        "id": 4,
        "numero_cuota": 4,
        "fecha_vencimiento": "2026-02-15",
        "monto": 500.00,
        "estado": "pendiente"
      },
      "cuotas": [
        {
          "id": 1,
          "numero_cuota": 1,
          "fecha_vencimiento": "2025-11-15",
          "monto": 500.00,
          "estado": "pagado",
          "paid_at": "2024-11-23 18:30:45"
        },
        {
          "id": 2,
          "numero_cuota": 2,
          "fecha_vencimiento": "2025-12-15",
          "monto": 500.00,
          "estado": "pagado",
          "paid_at": "2024-12-23 18:30:45"
        },
        {
          "id": 3,
          "numero_cuota": 3,
          "fecha_vencimiento": "2026-01-15",
          "monto": 500.00,
          "estado": "pagado",
          "paid_at": "2025-01-23 18:30:45"
        },
        {
          "id": 4,
          "numero_cuota": 4,
          "fecha_vencimiento": "2026-02-15",
          "monto": 500.00,
          "estado": "pendiente",
          "paid_at": null
        }
        // ... más cuotas
      ]
    }
    // ... más estudiantes
  ]
}
```

---

## 🔍 Campos Importantes y su Significado

### `pagination` (Información de Paginación) ⭐ NUEVO
| Campo | Tipo | Descripción |
|-------|------|-------------|
| `current_page` | `number` | Página actual (1, 2, 3...) |
| `per_page` | `number` | Cantidad de registros por página |
| `total` | `number` | Total de registros en la base de datos (con filtros aplicados) |
| `total_pages` | `number` | Total de páginas disponibles |
| `from` | `number` | Índice del primer registro de esta página (base 1) |
| `to` | `number` | Índice del último registro de esta página |
| `has_more` | `boolean` | `true` si hay más páginas disponibles |

**Ejemplo de uso:**
```typescript
// Verificar si hay página siguiente
if (response.pagination.has_more) {
  // Cargar página siguiente
  loadPage(response.pagination.current_page + 1);
}

// Mostrar "Mostrando 1-100 de 2,959 registros"
const text = `Mostrando ${response.pagination.from}-${response.pagination.to} de ${response.pagination.total} registros`;
```

### `summary` (Resumen Global)
| Campo | Tipo | Descripción |
|-------|------|-------------|
| `estudiantes_activos` | `number` | Total de estudiantes activos en el sistema (sin filtros) |
| `saldo_estimado` | `number` | Suma total de todas las cuotas pendientes de todos los estudiantes |
| `en_mora` | `number` | Cantidad de cuotas vencidas (fecha_vencimiento < hoy) y no pagadas |
| `planes_reestructurados` | `number` | Cantidad de planes de pago reestructurados activos |

### `estudiantes[]` (Array de Estudiantes)
| Campo | Tipo | Descripción |
|-------|------|-------------|
| `estudiante_programa_id` | `number` | ID único de la relación estudiante-programa |
| `prospecto.id` | `number` | ID del prospecto/estudiante |
| `prospecto.nombre` | `string` | Nombre completo del estudiante |
| `prospecto.carnet` | `string` | Carnet único (ej: ASM2020126) |
| `prospecto.correo` | `string` | Correo electrónico |
| `prospecto.telefono` | `string` | Teléfono de contacto |
| `programa.id` | `number` | ID del programa académico |
| `programa.nombre` | `string` | Nombre del programa (ej: MBA) |
| `saldo_pendiente` | `number` | Suma de todas las cuotas pendientes del estudiante |
| `cuotas_pendientes` | `number` | Cantidad de cuotas con estado != 'pagado' |
| `cuotas_pagadas` | `number` | Cantidad de cuotas con estado = 'pagado' |
| `proxima_cuota` | `object\|null` | Siguiente cuota por vencer (ordenada por fecha) |
| `cuotas[]` | `array` | Lista completa de todas las cuotas del estudiante |

### `cuotas[]` (Array de Cuotas por Estudiante)
| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | `number` | ID único de la cuota |
| `numero_cuota` | `number` | Número correlativo de cuota (1, 2, 3...) |
| `fecha_vencimiento` | `string` | Fecha de vencimiento (formato: YYYY-MM-DD) |
| `monto` | `number` | Monto de la cuota en Quetzales |
| `estado` | `string` | Estado: "pagado", "pendiente", "vencido" |
| `paid_at` | `string\|null` | Fecha y hora de pago (formato: YYYY-MM-DD HH:mm:ss) |

---

## 🎨 Ejemplo de Uso en Frontend (React/TypeScript)

### 1. Definir Tipos (TypeScript)

```typescript
// types/cuotas.ts

export interface Pagination {
  current_page: number;
  per_page: number;
  total: number;
  total_pages: number;
  from: number;
  to: number;
  has_more: boolean;
}

export interface CuotaDashboardSummary {
  estudiantes_activos: number;
  saldo_estimado: number;
  en_mora: number;
  planes_reestructurados: number;
}

export interface Prospecto {
  id: number;
  nombre: string;
  carnet: string;
  correo: string;
  telefono: string;
}

export interface Programa {
  id: number;
  nombre: string;
}

export interface Cuota {
  id: number;
  numero_cuota: number;
  fecha_vencimiento: string;
  monto: number;
  estado: 'pagado' | 'pendiente' | 'vencido';
  paid_at: string | null;
}

export interface ProximaCuota {
  id: number;
  numero_cuota: number;
  fecha_vencimiento: string;
  monto: number;
  estado: string;
}

export interface EstudianteCuotas {
  estudiante_programa_id: number;
  prospecto: Prospecto;
  programa: Programa;
  saldo_pendiente: number;
  cuotas_pendientes: number;
  cuotas_pagadas: number;
  proxima_cuota: ProximaCuota | null;
  cuotas: Cuota[];
}

export interface CuotasDashboardResponse {
  timestamp: string;
  filters: {
    prospecto_id: number | null;
    programa_id: number | null;
    search: string | null;
    fecha_inicio: string | null;
    fecha_fin: string | null;
  };
  pagination: Pagination;  // ⭐ NUEVO - Información de paginación
  summary: CuotaDashboardSummary;
  estudiantes: EstudianteCuotas[];
}
```

### 2. Servicio API con Paginación

```typescript
// services/mantenimientos.ts

import axios from 'axios';
import { CuotasDashboardResponse } from '@/types/cuotas';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';

export interface CuotasDashboardFilters {
  page?: number;           // ⭐ NUEVO - Número de página
  per_page?: number;       // ⭐ NUEVO - Registros por página (reemplaza 'limit')
  search?: string;
  programa_id?: number;
  prospecto_id?: number;
}

export const getCuotasDashboard = async (
  filters?: CuotasDashboardFilters
): Promise<CuotasDashboardResponse> => {
  const params = new URLSearchParams();
  
  // Paginación
  if (filters?.page) params.append('page', filters.page.toString());
  if (filters?.per_page) params.append('per_page', filters.per_page.toString());
  
  // Filtros
  if (filters?.search) params.append('search', filters.search);
  if (filters?.programa_id) params.append('programa_id', filters.programa_id.toString());
  if (filters?.prospecto_id) params.append('prospecto_id', filters.prospecto_id.toString());

  const response = await axios.get<CuotasDashboardResponse>(
    `${API_BASE_URL}/mantenimientos/cuotas/dashboard`,
    {
      params,
      headers: {
        Authorization: `Bearer ${localStorage.getItem('token')}`,
      },
    }
  );

  return response.data;
};
```

### 3. Componente de Ejemplo (React) con Paginación

```typescript
// components/SeguimientoEstudiantes.tsx

import React, { useEffect, useState } from 'react';
import { getCuotasDashboard } from '@/services/mantenimientos';
import { EstudianteCuotas, CuotaDashboardSummary, Pagination } from '@/types/cuotas';

const SeguimientoEstudiantes: React.FC = () => {
  const [estudiantes, setEstudiantes] = useState<EstudianteCuotas[]>([]);
  const [summary, setSummary] = useState<CuotaDashboardSummary | null>(null);
  const [pagination, setPagination] = useState<Pagination | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  
  // Filtros y paginación
  const [currentPage, setCurrentPage] = useState(1);
  const [perPage, setPerPage] = useState(100);
  const [search, setSearch] = useState('');
  const [programaId, setProgramaId] = useState<number | undefined>();

  useEffect(() => {
    loadData();
  }, [currentPage, perPage, search, programaId]);

  const loadData = async () => {
    try {
      setLoading(true);
      setError(null);
      
      const data = await getCuotasDashboard({
        page: currentPage,
        per_page: perPage,
        search: search || undefined,
        programa_id: programaId,
      });

      setEstudiantes(data.estudiantes);
      setSummary(data.summary);
      setPagination(data.pagination);
    } catch (err: any) {
      console.error('Error al cargar dashboard:', err);
      setError(err.message || 'Error al cargar datos');
    } finally {
      setLoading(false);
    }
  };

  const handlePageChange = (newPage: number) => {
    if (newPage >= 1 && pagination && newPage <= pagination.total_pages) {
      setCurrentPage(newPage);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  };

  const handlePerPageChange = (newPerPage: number) => {
    setPerPage(newPerPage);
    setCurrentPage(1); // Resetear a primera página
  };

  const formatCurrency = (amount: number) => {
    return `Q${amount.toLocaleString('es-GT', { minimumFractionDigits: 2 })}`;
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('es-GT');
  };

  if (loading) return <div>Cargando...</div>;
  if (error) return <div className="error">Error: {error}</div>;

  return (
    <div className="seguimiento-estudiantes">
      <h1>Seguimiento de Estudiantes</h1>

      {/* Resumen */}
      {summary && (
        <div className="summary-cards">
          <div className="card">
            <h3>Estudiantes Activos</h3>
            <p className="number">{summary.estudiantes_activos.toLocaleString()}</p>
          </div>
          <div className="card">
            <h3>Saldo Pendiente Total</h3>
            <p className="number">{formatCurrency(summary.saldo_estimado)}</p>
          </div>
          <div className="card">
            <h3>Cuotas en Mora</h3>
            <p className="number alert">{summary.en_mora}</p>
          </div>
          <div className="card">
            <h3>Planes Reestructurados</h3>
            <p className="number">{summary.planes_reestructurados}</p>
          </div>
        </div>
      )}

      {/* Filtros */}
      <div className="filters">
        <input
          type="text"
          placeholder="Buscar por carnet o nombre..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />
      </div>

      {/* Tabla de Estudiantes */}
      <div className="table-container">
        {estudiantes.length === 0 ? (
          <p>No se encontraron estudiantes</p>
        ) : (
          <table>
            <thead>
              <tr>
                <th>Carnet</th>
                <th>Nombre</th>
                <th>Programa</th>
                <th>Cuotas Pagadas</th>
                <th>Cuotas Pendientes</th>
                <th>Saldo Pendiente</th>
                <th>Próxima Cuota</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              {estudiantes.map((est) => (
                <tr key={est.estudiante_programa_id}>
                  <td>{est.prospecto.carnet}</td>
                  <td>{est.prospecto.nombre}</td>
                  <td>{est.programa.nombre}</td>
                  <td className="center">{est.cuotas_pagadas}</td>
                  <td className="center">{est.cuotas_pendientes}</td>
                  <td className="currency">{formatCurrency(est.saldo_pendiente)}</td>
                  <td>
                    {est.proxima_cuota ? (
                      <div>
                        <span>Cuota #{est.proxima_cuota.numero_cuota}</span><br />
                        <small>{formatDate(est.proxima_cuota.fecha_vencimiento)}</small><br />
                        <strong>{formatCurrency(est.proxima_cuota.monto)}</strong>
                      </div>
                    ) : (
                      <span className="text-muted">Sin cuotas pendientes</span>
                    )}
                  </td>
                  <td>
                    <button onClick={() => verDetalle(est)}>Ver Detalle</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {/* Controles de Paginación */}
      {pagination && pagination.total > 0 && (
        <div className="pagination-controls">
          <div className="pagination-info">
            Mostrando {pagination.from} - {pagination.to} de {pagination.total.toLocaleString()} estudiantes
          </div>
          
          <div className="pagination-buttons">
            <button 
              onClick={() => handlePageChange(1)}
              disabled={currentPage === 1}
            >
              Primera
            </button>
            
            <button 
              onClick={() => handlePageChange(currentPage - 1)}
              disabled={currentPage === 1}
            >
              Anterior
            </button>
            
            <span className="page-indicator">
              Página {currentPage} de {pagination.total_pages}
            </span>
            
            <button 
              onClick={() => handlePageChange(currentPage + 1)}
              disabled={!pagination.has_more}
            >
              Siguiente
            </button>
            
            <button 
              onClick={() => handlePageChange(pagination.total_pages)}
              disabled={currentPage === pagination.total_pages}
            >
              Última
            </button>
          </div>
          
          <div className="per-page-selector">
            <label>Mostrar:</label>
            <select 
              value={perPage} 
              onChange={(e) => handlePerPageChange(Number(e.target.value))}
            >
              <option value={25}>25</option>
              <option value={50}>50</option>
              <option value={100}>100</option>
              <option value={200}>200</option>
            </select>
            <span>por página</span>
          </div>
        </div>
      )}
    </div>
  );
};

export default SeguimientoEstudiantes;
```

---

## ⚠️ Posibles Problemas a Corregir en Frontend

### 1. **Error: "No se encontraron estudiantes"**
**Causa:** El frontend espera que `response.estudiantes` tenga datos, pero el endpoint anterior retornaba array vacío.

**Solución:** ✅ Ya corregido en backend. Ahora retorna todos los estudiantes activos aunque no tengan cuotas.

**Verificar en frontend:**
```typescript
// ANTES (❌ Incorrecto)
if (!response.estudiantes || response.estudiantes.length === 0) {
  return <div>No se encontraron estudiantes</div>;
}

// AHORA (✅ Correcto)
// Siempre debería retornar estudiantes si hay activos en BD
if (!response.estudiantes) {
  return <div>Error en la respuesta del servidor</div>;
}
if (response.estudiantes.length === 0) {
  return <div>No hay estudiantes con los filtros aplicados</div>;
}
```

### 2. **Error: Campo `proxima_cuota` puede ser `null`**
**Causa:** Estudiantes sin cuotas o con todas pagadas tienen `proxima_cuota: null`.

**Solución:**
```typescript
// ✅ Verificar siempre antes de acceder
{est.proxima_cuota ? (
  <div>
    Cuota #{est.proxima_cuota.numero_cuota} - 
    {formatCurrency(est.proxima_cuota.monto)}
  </div>
) : (
  <span>Sin cuotas pendientes</span>
)}
```

### 3. **Error: Formateo de fechas**
**Causa:** Las fechas vienen como string "YYYY-MM-DD", no como objetos Date.

**Solución:**
```typescript
const formatDate = (dateString: string | null): string => {
  if (!dateString) return 'N/A';
  return new Date(dateString).toLocaleDateString('es-GT', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  });
};
```

### 4. **Error: Formateo de montos**
**Causa:** Los montos vienen como number, necesitan formato de moneda.

**Solución:**
```typescript
const formatCurrency = (amount: number): string => {
  return new Intl.NumberFormat('es-GT', {
    style: 'currency',
    currency: 'GTQ'
  }).format(amount);
};

// O simplemente:
const formatCurrency = (amount: number): string => {
  return `Q${amount.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,')}`;
};
```

### 5. **Error: Búsqueda no funciona**
**Causa:** El parámetro `search` no se está pasando correctamente al endpoint.

**Solución:**
```typescript
// ✅ Asegurar que el parámetro se envía
const params = new URLSearchParams();
if (search && search.trim() !== '') {
  params.append('search', search.trim());
}

// Agregar debounce para optimizar búsquedas
const [searchTerm, setSearchTerm] = useState('');
const [debouncedSearch, setDebouncedSearch] = useState('');

useEffect(() => {
  const timer = setTimeout(() => {
    setDebouncedSearch(searchTerm);
  }, 500);
  return () => clearTimeout(timer);
}, [searchTerm]);
```

### 6. **⭐ IMPLEMENTAR PAGINACIÓN (REQUERIDO)**
**Causa:** El backend tiene 2,959 estudiantes activos, pero solo retorna 100 por defecto (configurable hasta 500 máx).

**Solución COMPLETA:**
```typescript
const [currentPage, setCurrentPage] = useState(1);
const [perPage, setPerPage] = useState(100);
const [pagination, setPagination] = useState<Pagination | null>(null);

const loadData = async () => {
  const data = await getCuotasDashboard({
    page: currentPage,
    per_page: perPage,
    search: searchTerm,
  });
  
  setEstudiantes(data.estudiantes);
  setPagination(data.pagination);
};

// Renderizar controles de paginación
{pagination && (
  <div>
    <span>
      Mostrando {pagination.from}-{pagination.to} de {pagination.total} registros
    </span>
    <button 
      onClick={() => setCurrentPage(p => p - 1)}
      disabled={currentPage === 1}
    >
      Anterior
    </button>
    <span>Página {pagination.current_page} de {pagination.total_pages}</span>
    <button 
      onClick={() => setCurrentPage(p => p + 1)}
      disabled={!pagination.has_more}
    >
      Siguiente
    </button>
  </div>
)}
```

**⚠️ IMPORTANTE:** Sin paginación, solo verás los primeros 100 estudiantes de 2,959 totales.
    limit: limit,
    // Backend retorna hasta 500 máximo
  });
  // Implementar paginación en frontend si necesitas más
};
```

---

## 🧪 Cómo Probar el Endpoint

### Usando cURL:
```bash
curl -X GET "http://localhost:8000/api/mantenimientos/cuotas/dashboard?limit=10" \
  -H "Authorization: Bearer TU_TOKEN_AQUI" \
  -H "Content-Type: application/json"
```

### Usando Postman:
1. **Method:** GET
2. **URL:** `http://localhost:8000/api/mantenimientos/cuotas/dashboard`
3. **Headers:**
   - `Authorization: Bearer {token}`
   - `Content-Type: application/json`
4. **Params (opcional):**
   - `limit`: 10
   - `search`: ASM2020

### Usando Axios en consola del navegador:
```javascript
const token = localStorage.getItem('token');
const response = await fetch('http://localhost:8000/api/mantenimientos/cuotas/dashboard?limit=5', {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  }
});
const data = await response.json();
console.log(data);
```

---

## 📊 Estados de Cuotas

| Estado | Descripción | Color sugerido |
|--------|-------------|----------------|
| `"pagado"` | Cuota pagada completamente | 🟢 Verde |
| `"pendiente"` | Cuota por pagar, no vencida | 🟡 Amarillo |
| `"vencido"` | Cuota no pagada y fecha_vencimiento < hoy | 🔴 Rojo |
| `null` | Sin estado (tratarlo como pendiente) | 🟡 Amarillo |

---

## 🔧 Relaciones de Base de Datos

```
Prospecto (prospectos)
    └── EstudiantePrograma (estudiante_programa)
            └── CuotaProgramaEstudiante (cuotas_programa_estudiante)
                    ├── estado: 'pagado' | 'pendiente' | null
                    ├── monto: decimal
                    └── fecha_vencimiento: date
```

---

## ✅ Checklist para Frontend

- [ ] Actualizar tipos TypeScript con la estructura exacta de la respuesta
- [ ] Modificar `getCuotasDashboard()` para usar el endpoint correcto
- [ ] Agregar manejo de `proxima_cuota` cuando es `null`
- [ ] Implementar formateo de fechas (YYYY-MM-DD → formato legible)
- [ ] Implementar formateo de moneda (number → Q1,234.56)
- [ ] Agregar validación para `estudiantes.length === 0`
- [ ] Implementar debounce en búsqueda para optimizar requests
- [ ] Agregar loading states y error handling
- [ ] Mostrar el resumen (`summary`) con las 4 métricas principales
- [ ] Implementar filtros por programa si es necesario
- [ ] Agregar indicadores visuales para estados (pagado/pendiente/vencido)
- [ ] Probar con diferentes límites (10, 50, 100, 200)

---

## 🚀 Próximos Pasos Sugeridos

1. **Actualizar el servicio API** con los tipos correctos
2. **Probar el endpoint** desde Postman/cURL para verificar datos
3. **Actualizar el componente** para manejar la nueva estructura
4. **Agregar indicadores visuales** para cuotas vencidas/pendientes
5. **Implementar vista detallada** por estudiante con todas sus cuotas
6. **Agregar filtros avanzados** (por programa, por estado, por rango de fechas)

---

## 📞 Soporte

Si encuentras algún problema con el backend, verifica:

1. ✅ El token de autorización es válido
2. ✅ La URL del endpoint es correcta: `/api/mantenimientos/cuotas/dashboard`
3. ✅ Los filtros se envían como query params, no en el body
4. ✅ El backend está corriendo en el puerto correcto (8000)
5. ✅ Hay estudiantes activos en la base de datos

**Logs del backend:** Revisar `storage/logs/laravel.log` para ver errores detallados.

---

## 🎉 Resumen

✅ **Backend funcionando correctamente**
✅ **Retorna todos los estudiantes activos** (2,959 en tu caso)
✅ **Cálculos correctos** de cuotas pagadas/pendientes/saldo
✅ **Estructura de datos clara** y bien documentada
✅ **Filtros implementados** (search, programa_id, prospecto_id, limit)

**Ahora solo necesitas actualizar el frontend para consumir correctamente estos datos.**

---

*Documento generado el 23 de octubre de 2025*
*Backend: Laravel | Endpoint: MantenimientosController@cuotasDashboard*
