# 📚 Documentación API - MantenimientosController

**Controlador:** `App\Http\Controllers\Api\MantenimientosController`  
**Ruta Base:** `/api/mantenimientos`  
**Autenticación:** Requerida (Bearer Token)

---

## 📋 Índice

1. [Dashboard y Resúmenes](#dashboard-y-resúmenes)
2. [CRUD de Cuotas](#crud-de-cuotas)
3. [CRUD de Kardex (Movimientos de Pago)](#crud-de-kardex)
4. [CRUD de Reconciliaciones Bancarias](#crud-de-reconciliaciones)
5. [Relaciones y Flujos de Datos](#relaciones-y-flujos)
6. [Ejemplos de Consumo Frontend](#ejemplos-frontend)

---

## 🎯 Dashboard y Resúmenes

### 1. Dashboard General de Kardex

**Endpoint:** `GET /api/mantenimientos/kardex/dashboard`

**Descripción:** Obtiene resumen general de movimientos de kardex, conciliaciones y cuotas.

**Query Parameters:**
```typescript
{
  prospecto_id?: number;      // Filtrar por estudiante específico
  programa_id?: number;        // Filtrar por programa
  search?: string;             // Búsqueda general (nombre, carnet, correo)
  fecha_inicio?: string;       // Formato: YYYY-MM-DD
  fecha_fin?: string;          // Formato: YYYY-MM-DD
}
```

**Response:**
```json
{
  "timestamp": "2025-10-23T22:00:00+00:00",
  "filters": {
    "prospecto_id": null,
    "programa_id": null,
    "search": null,
    "fecha_inicio": null,
    "fecha_fin": null
  },
  "kardex": {
    "movimientos_registrados": 3553,
    "monto_neto": 4308289.85,
    "aplicados": 3553,
    "pendientes": 0,
    "rechazados": 0
  },
  "reconciliaciones": {
    "total": 2990,
    "monto_total": 3774204.37,
    "conciliados": 2990,
    "rechazados": 0,
    "pendientes": 0
  },
  "cuotas": {
    "total": 3553,
    "pendientes": 0,
    "en_mora": 0,
    "monto_pendiente": 0.00
  }
}
```

**Ejemplo de Uso (Frontend):**
```typescript
// React/TypeScript
const fetchDashboard = async (filters: DashboardFilters) => {
  const params = new URLSearchParams();
  if (filters.prospecto_id) params.append('prospecto_id', filters.prospecto_id.toString());
  if (filters.programa_id) params.append('programa_id', filters.programa_id.toString());
  if (filters.search) params.append('search', filters.search);
  if (filters.fecha_inicio) params.append('fecha_inicio', filters.fecha_inicio);
  if (filters.fecha_fin) params.append('fecha_fin', filters.fecha_fin);

  const response = await fetch(`/api/mantenimientos/kardex/dashboard?${params}`, {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json'
    }
  });
  
  return await response.json();
};
```

---

### 2. Dashboard de Cuotas por Estudiante (Paginado)

**Endpoint:** `GET /api/mantenimientos/cuotas/dashboard`

**Descripción:** Lista estudiantes activos con sus cuotas, saldos y estados. Incluye **paginación completa**.

**Query Parameters:**
```typescript
{
  page?: number;              // Página actual (default: 1)
  per_page?: number;          // Registros por página (default: 100, max: 500)
  prospecto_id?: number;      // Filtrar por estudiante
  programa_id?: number;       // Filtrar por programa
  search?: string;            // Búsqueda (nombre, carnet, correo)
}
```

**Response:**
```json
{
  "timestamp": "2025-10-23T22:00:00+00:00",
  "filters": {
    "prospecto_id": null,
    "programa_id": null,
    "search": null
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
    "saldo_estimado": 0.00,
    "en_mora": 0,
    "planes_reestructurados": 0
  },
  "estudiantes": [
    {
      "estudiante_programa_id": 1,
      "prospecto": {
        "id": 1,
        "nombre": "Juan Pérez",
        "carnet": "ASM2020001",
        "correo": "juan@example.com",
        "telefono": "+502 1234-5678"
      },
      "programa": {
        "id": 1,
        "nombre": "Master of Business Administration"
      },
      "saldo_pendiente": 0.00,
      "cuotas_pendientes": 0,
      "cuotas_pagadas": 12,
      "proxima_cuota": null,
      "cuotas": [
        {
          "id": 1,
          "numero_cuota": 1,
          "monto": 1500.00,
          "fecha_vencimiento": "2024-01-31",
          "estado": "pagado",
          "paid_at": "2024-01-15 10:30:00"
        }
      ]
    }
  ]
}
```

**Ejemplo de Paginación (Frontend):**
```typescript
// React Component con Paginación
const CuotasDashboard = () => {
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(100);
  const [data, setData] = useState(null);

  const fetchCuotas = async () => {
    const response = await fetch(
      `/api/mantenimientos/cuotas/dashboard?page=${page}&per_page=${perPage}`,
      {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Accept': 'application/json'
        }
      }
    );
    const result = await response.json();
    setData(result);
  };

  return (
    <div>
      {/* Tabla de estudiantes */}
      <table>
        {data?.estudiantes.map(est => (
          <tr key={est.estudiante_programa_id}>
            <td>{est.prospecto.nombre}</td>
            <td>{est.prospecto.carnet}</td>
            <td>Q {est.saldo_pendiente.toFixed(2)}</td>
          </tr>
        ))}
      </table>

      {/* Controles de paginación */}
      <div>
        <button 
          onClick={() => setPage(p => p - 1)} 
          disabled={page === 1}
        >
          Anterior
        </button>
        
        <span>Página {data?.pagination.current_page} de {data?.pagination.total_pages}</span>
        
        <button 
          onClick={() => setPage(p => p + 1)} 
          disabled={!data?.pagination.has_more}
        >
          Siguiente
        </button>
      </div>
    </div>
  );
};
```

---

## 💰 CRUD de Cuotas

### 1. Listar Cuotas (con filtros)

**Endpoint:** `GET /api/mantenimientos/cuotas`

**Query Parameters:**
```typescript
{
  page?: number;                      // Página actual
  limit?: number;                     // Registros por página (default: 100)
  carnet?: string;                    // Filtrar por carnet del estudiante
  prospecto_id?: number;              // Filtrar por ID de prospecto
  estudiante_programa_id?: number;   // Filtrar por ID de estudiante_programa
  estado?: string;                    // Valores: pendiente, pagado, vencido, cancelado
  fecha_vencimiento_desde?: string;  // Formato: YYYY-MM-DD
  fecha_vencimiento_hasta?: string;  // Formato: YYYY-MM-DD
  q?: string;                         // Búsqueda general
}
```

**Response:**
```json
{
  "timestamp": "2025-10-23T22:00:00+00:00",
  "pagination": {
    "total": 3553,
    "per_page": 100,
    "current_page": 1,
    "last_page": 36
  },
  "cuotas": [
    {
      "id": 1,
      "estudiante_programa_id": 1,
      "numero_cuota": 1,
      "fecha_vencimiento": "2024-01-31",
      "monto": 1500.00,
      "estado": "pagado",
      "paid_at": "2024-01-15 10:30:00",
      "total_pagado": 1500.00,
      "saldo_pendiente": 0.00,
      "prospecto": {
        "id": 1,
        "nombre": "Juan Pérez",
        "carnet": "ASM2020001",
        "correo": "juan@example.com"
      },
      "programa": {
        "id": 1,
        "nombre": "Master of Business Administration"
      },
      "created_at": "2024-01-01 08:00:00",
      "updated_at": "2024-01-15 10:30:00"
    }
  ]
}
```

**Ejemplo Frontend:**
```typescript
// React Hook personalizado
const useCuotas = (filters: CuotaFilters) => {
  const [cuotas, setCuotas] = useState([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    const fetchCuotas = async () => {
      setLoading(true);
      const params = new URLSearchParams();
      
      if (filters.carnet) params.append('carnet', filters.carnet);
      if (filters.estado) params.append('estado', filters.estado);
      if (filters.fecha_vencimiento_desde) 
        params.append('fecha_vencimiento_desde', filters.fecha_vencimiento_desde);
      
      const response = await fetch(`/api/mantenimientos/cuotas?${params}`, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Accept': 'application/json'
        }
      });
      
      const data = await response.json();
      setCuotas(data.cuotas);
      setLoading(false);
    };

    fetchCuotas();
  }, [filters]);

  return { cuotas, loading };
};
```

---

### 2. Ver Detalle de una Cuota

**Endpoint:** `GET /api/mantenimientos/cuotas/{id}`

**Response:**
```json
{
  "timestamp": "2025-10-23T22:00:00+00:00",
  "cuota": {
    "id": 1,
    "estudiante_programa_id": 1,
    "numero_cuota": 1,
    "fecha_vencimiento": "2024-01-31",
    "monto": 1500.00,
    "estado": "pagado",
    "paid_at": "2024-01-15 10:30:00",
    "total_pagado": 1500.00,
    "saldo_pendiente": 0.00,
    "prospecto": {
      "id": 1,
      "nombre": "Juan Pérez",
      "carnet": "ASM2020001",
      "correo": "juan@example.com",
      "telefono": "+502 1234-5678"
    },
    "programa": {
      "id": 1,
      "nombre": "Master of Business Administration"
    },
    "created_at": "2024-01-01 08:00:00",
    "updated_at": "2024-01-15 10:30:00"
  },
  "pagos": [
    {
      "id": 1,
      "fecha_pago": "2024-01-15",
      "monto_pagado": 1500.00,
      "metodo_pago": "transferencia",
      "estado_pago": "aprobado",
      "numero_boleta": "BOL-2024-001",
      "banco": "Banco Industrial"
    }
  ]
}
```

---

### 3. Crear Cuota

**Endpoint:** `POST /api/mantenimientos/cuotas`

**IMPORTANTE:** Al crear una cuota:
- ✅ Solo se crea el registro en `cuotas_programa_estudiante`
- ❌ NO se crea `kardex` ni `reconciliation` automáticamente
- 📝 Para registrar un pago debes crear el kardex por separado

**Request Body:**
```json
{
  "estudiante_programa_id": 1,
  "numero_cuota": 1,
  "fecha_vencimiento": "2024-12-31",
  "monto": 1500.00,
  "estado": "pendiente",
  "paid_at": null
}
```

**Validaciones:**
- `estudiante_programa_id`: **requerido**, debe existir en `estudiante_programas`
- `numero_cuota`: **requerido**, entero >= 0, no puede estar duplicado para el mismo estudiante
- `fecha_vencimiento`: **requerido**, formato fecha válida
- `monto`: **requerido**, numérico >= 0
- `estado`: opcional, valores: `pendiente`, `pagado`, `vencido`, `cancelado` (default: `pendiente`)
- `paid_at`: opcional, formato fecha/hora

**Response (201 Created):**
```json
{
  "message": "Cuota creada exitosamente",
  "cuota": {
    "id": 100,
    "estudiante_programa_id": 1,
    "numero_cuota": 1,
    "fecha_vencimiento": "2024-12-31",
    "monto": 1500.00,
    "estado": "pendiente",
    "paid_at": null,
    "prospecto": "Juan Pérez",
    "programa": "Master of Business Administration"
  }
}
```

**Ejemplo Frontend:**
```typescript
// Formulario de Creación de Cuota
const crearCuota = async (data: CuotaForm) => {
  const response = await fetch('/api/mantenimientos/cuotas', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    },
    body: JSON.stringify({
      estudiante_programa_id: data.estudianteProgramaId,
      numero_cuota: data.numeroCuota,
      fecha_vencimiento: data.fechaVencimiento, // "2024-12-31"
      monto: data.monto,
      estado: data.estado || 'pendiente'
    })
  });

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.message);
  }

  return await response.json();
};

// Uso en React Component
const FormularioCuota = () => {
  const [formData, setFormData] = useState({
    estudianteProgramaId: 0,
    numeroCuota: 1,
    fechaVencimiento: '',
    monto: 0,
    estado: 'pendiente'
  });

  const handleSubmit = async (e) => {
    e.preventDefault();
    try {
      const result = await crearCuota(formData);
      alert('Cuota creada: ' + result.cuota.id);
    } catch (error) {
      alert('Error: ' + error.message);
    }
  };

  return (
    <form onSubmit={handleSubmit}>
      <input 
        type="number" 
        value={formData.numeroCuota}
        onChange={e => setFormData({...formData, numeroCuota: parseInt(e.target.value)})}
        placeholder="Número de Cuota"
      />
      <input 
        type="date" 
        value={formData.fechaVencimiento}
        onChange={e => setFormData({...formData, fechaVencimiento: e.target.value})}
      />
      <input 
        type="number" 
        step="0.01"
        value={formData.monto}
        onChange={e => setFormData({...formData, monto: parseFloat(e.target.value)})}
        placeholder="Monto"
      />
      <button type="submit">Crear Cuota</button>
    </form>
  );
};
```

---

### 4. Actualizar Cuota

**Endpoint:** `PUT /api/mantenimientos/cuotas/{id}`

**Request Body (campos opcionales):**
```json
{
  "numero_cuota": 2,
  "fecha_vencimiento": "2025-01-31",
  "monto": 1600.00,
  "estado": "pagado",
  "paid_at": "2024-12-15 14:30:00"
}
```

**Response:**
```json
{
  "message": "Cuota actualizada exitosamente",
  "cuota": {
    "id": 1,
    "estudiante_programa_id": 1,
    "numero_cuota": 2,
    "fecha_vencimiento": "2025-01-31",
    "monto": 1600.00,
    "estado": "pagado",
    "paid_at": "2024-12-15 14:30:00",
    "prospecto": "Juan Pérez",
    "programa": "Master of Business Administration"
  }
}
```

**Ejemplo Frontend:**
```typescript
const actualizarCuota = async (id: number, cambios: Partial<Cuota>) => {
  const response = await fetch(`/api/mantenimientos/cuotas/${id}`, {
    method: 'PUT',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    },
    body: JSON.stringify(cambios)
  });

  if (!response.ok) {
    throw new Error('Error al actualizar cuota');
  }

  return await response.json();
};

// Ejemplo: Marcar cuota como pagada
await actualizarCuota(1, {
  estado: 'pagado',
  paid_at: new Date().toISOString()
});
```

---

### 5. Eliminar Cuota (Borrado en Cascada) ⚠️

**Endpoint:** `DELETE /api/mantenimientos/cuotas/{id}`

**IMPORTANTE - Comportamiento de Borrado en Cascada:**

1. **Cuota SIN pagos (pendiente):**
   - ✅ Se elimina directamente
   - No afecta otras tablas

2. **Cuota CON pagos (tiene kardex):**
   - ✅ Se eliminan **reconciliation_records** relacionados (por monto + fecha + banco)
   - ✅ Se eliminan **kardex_pagos** relacionados (por cuota_id)
   - ✅ Finalmente se elimina la **cuota**
   - 📝 Todo dentro de una transacción (rollback automático si hay error)

**Flujo de Eliminación:**
```
DELETE Cuota #1
  ↓
  ¿Tiene kardex?
  ↓
  SÍ → Buscar kardex con cuota_id = 1
  ↓
  Para cada kardex:
    ↓
    Buscar reconciliation_records (por monto + fecha + banco)
    ↓
    DELETE reconciliation_records
    ↓
    DELETE kardex_pago
  ↓
  DELETE cuota
  ↓
  COMMIT
```

**Response (Cuota sin pagos):**
```json
{
  "message": "Cuota eliminada exitosamente",
  "details": {
    "cuota_id": 1,
    "kardex_eliminados": 0,
    "reconciliaciones_eliminadas": 0
  }
}
```

**Response (Cuota con pagos - Cascada):**
```json
{
  "message": "Cuota y registros relacionados eliminados exitosamente",
  "details": {
    "cuota_id": 1,
    "kardex_eliminados": 3,
    "reconciliaciones_eliminadas": 2
  }
}
```

**Ejemplo Frontend con Confirmación:**
```typescript
const eliminarCuota = async (id: number) => {
  // Primero obtener detalle para saber si tiene pagos
  const detalle = await fetch(`/api/mantenimientos/cuotas/${id}`, {
    headers: { 'Authorization': `Bearer ${token}` }
  }).then(r => r.json());

  const tienePagos = detalle.pagos && detalle.pagos.length > 0;

  // Mostrar confirmación apropiada
  const mensaje = tienePagos
    ? `Esta cuota tiene ${detalle.pagos.length} pago(s) registrados. Se eliminarán en cascada:\n` +
      `- Kardex: ${detalle.pagos.length} registros\n` +
      `- Reconciliaciones bancarias asociadas\n` +
      `¿Desea continuar?`
    : '¿Está seguro de eliminar esta cuota?';

  if (!confirm(mensaje)) return;

  // Eliminar
  const response = await fetch(`/api/mantenimientos/cuotas/${id}`, {
    method: 'DELETE',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json'
    }
  });

  if (!response.ok) {
    throw new Error('Error al eliminar cuota');
  }

  const result = await response.json();
  
  console.log(`Eliminación completada:
    - Kardex eliminados: ${result.details.kardex_eliminados}
    - Reconciliaciones eliminadas: ${result.details.reconciliaciones_eliminadas}
  `);

  return result;
};

// Componente React con botón de eliminar
const BotonEliminarCuota = ({ cuota }) => {
  const handleDelete = async () => {
    try {
      await eliminarCuota(cuota.id);
      alert('Cuota eliminada exitosamente');
      // Recargar lista
    } catch (error) {
      alert('Error: ' + error.message);
    }
  };

  return (
    <button 
      onClick={handleDelete}
      className="btn-danger"
    >
      Eliminar Cuota
    </button>
  );
};
```

---

### 6. Listar Cuotas de un Estudiante

**Endpoint:** `GET /api/mantenimientos/cuotas/por-estudiante`

**Query Parameters:**
```typescript
{
  estudiante_programa_id?: number;  // ID del estudiante_programa
  prospecto_id?: number;             // O ID del prospecto
}
```

**Nota:** Debe proporcionar uno de los dos parámetros.

**Response:**
```json
{
  "timestamp": "2025-10-23T22:00:00+00:00",
  "prospecto": "Juan Pérez",
  "carnet": "ASM2020001",
  "programa": "Master of Business Administration",
  "total_cuotas": 12,
  "total_monto": 18000.00,
  "total_pagado": 18000.00,
  "saldo_pendiente": 0.00,
  "cuotas": [
    {
      "id": 1,
      "numero_cuota": 1,
      "fecha_vencimiento": "2024-01-31",
      "monto": 1500.00,
      "estado": "pagado",
      "paid_at": "2024-01-15 10:30:00",
      "total_pagado": 1500.00,
      "saldo_pendiente": 0.00
    }
  ]
}
```

---

## 💳 CRUD de Kardex (Movimientos de Pago)

### 1. Listar Kardex

**Endpoint:** `GET /api/mantenimientos/kardex`

**Query Parameters:**
```typescript
{
  page?: number;
  limit?: number;
  carnet?: string;
  prospecto_id?: number;
  estudiante_programa_id?: number;
  estado_pago?: string;           // pendiente_revision, aprobado, rechazado
  metodo_pago?: string;
  banco?: string;
  fecha_pago_desde?: string;
  fecha_pago_hasta?: string;
  q?: string;                     // Búsqueda general
}
```

**Response:** Similar a cuotas, incluye información de estudiante, programa y cuota asociada.

---

### 2. Crear Kardex (Registrar Pago)

**Endpoint:** `POST /api/mantenimientos/kardex`

**Request Body:**
```json
{
  "estudiante_programa_id": 1,
  "cuota_id": 1,
  "fecha_pago": "2024-01-15",
  "fecha_recibo": "2024-01-15",
  "monto_pagado": 1500.00,
  "metodo_pago": "transferencia",
  "estado_pago": "aprobado",
  "numero_boleta": "BOL-2024-001",
  "banco": "Banco Industrial",
  "observaciones": "Pago completo"
}
```

**Validaciones:**
- `estudiante_programa_id`: **requerido**
- `cuota_id`: opcional (si se asocia a una cuota específica)
- `fecha_pago`: **requerido**
- `monto_pagado`: **requerido**, >= 0
- `metodo_pago`: **requerido**

**Response (201 Created):**
```json
{
  "message": "Movimiento de kardex creado exitosamente",
  "kardex": {
    "id": 100,
    "estudiante_programa_id": 1,
    "cuota_id": 1,
    "fecha_pago": "2024-01-15",
    "monto_pagado": 1500.00,
    "metodo_pago": "transferencia",
    "estado_pago": "aprobado",
    "prospecto": "Juan Pérez",
    "programa": "Master of Business Administration"
  }
}
```

---

### 3. Actualizar Kardex

**Endpoint:** `PUT /api/mantenimientos/kardex/{id}`

**Request Body (campos opcionales):**
```json
{
  "fecha_pago": "2024-01-16",
  "monto_pagado": 1550.00,
  "estado_pago": "aprobado",
  "observaciones": "Actualizado"
}
```

---

### 4. Eliminar Kardex

**Endpoint:** `DELETE /api/mantenimientos/kardex/{id}`

**Nota:** Solo elimina el kardex, NO elimina la cuota asociada.

---

## 🏦 CRUD de Reconciliaciones Bancarias

### 1. Listar Reconciliaciones

**Endpoint:** `GET /api/mantenimientos/reconciliaciones`

**Query Parameters:**
```typescript
{
  page?: number;
  limit?: number;
  prospecto_id?: number;
  status?: string;              // imported, conciliado, rechazado, sin_coincidencia
  bank?: string;
  reference?: string;
  fecha_desde?: string;
  fecha_hasta?: string;
  q?: string;
}
```

---

### 2. Crear Reconciliación

**Endpoint:** `POST /api/mantenimientos/reconciliaciones`

**Request Body:**
```json
{
  "prospecto_id": 1,
  "kardex_pago_id": 1,
  "bank": "Banco Industrial",
  "reference": "REF-2024-001",
  "amount": 1500.00,
  "date": "2024-01-15",
  "status": "conciliado",
  "notes": "Conciliado automáticamente"
}
```

---

### 3. Actualizar Reconciliación

**Endpoint:** `PUT /api/mantenimientos/reconciliaciones/{id}`

**Request Body:**
```json
{
  "kardex_pago_id": 1,
  "status": "conciliado",
  "notes": "Asociado manualmente"
}
```

---

### 4. Eliminar Reconciliación

**Endpoint:** `DELETE /api/mantenimientos/reconciliaciones/{id}`

---

## 🔄 Relaciones y Flujos de Datos

### Estructura de Tablas

```
prospectos (estudiantes)
  ↓
estudiante_programas (inscripción a programa)
  ↓
cuotas_programa_estudiante (cuotas del plan de pago)
  ↓
kardex_pagos (pagos aplicados a cuotas)
  ↓
reconciliation_records (conciliación bancaria)
```

### Flujo de Creación

#### 1. Crear Cuota Manualmente

```typescript
// Paso 1: Crear cuota
POST /api/mantenimientos/cuotas
{
  "estudiante_programa_id": 1,
  "numero_cuota": 1,
  "fecha_vencimiento": "2024-12-31",
  "monto": 1500.00,
  "estado": "pendiente"
}

// Resultado: Cuota #100 creada
// Solo existe en cuotas_programa_estudiante
// NO se crea kardex ni reconciliation automáticamente
```

#### 2. Registrar Pago (Crear Kardex)

```typescript
// Paso 2: Registrar pago para esa cuota
POST /api/mantenimientos/kardex
{
  "estudiante_programa_id": 1,
  "cuota_id": 100,              // ← Asociar a la cuota creada
  "fecha_pago": "2024-12-15",
  "monto_pagado": 1500.00,
  "metodo_pago": "transferencia",
  "estado_pago": "aprobado",
  "numero_boleta": "BOL-001",
  "banco": "Banco Industrial"
}

// Resultado: Kardex #500 creado
// Ahora existe la relación: Cuota #100 → Kardex #500
```

#### 3. Conciliar con Banco (Opcional)

```typescript
// Paso 3: Si viene de importación bancaria, crear reconciliation
POST /api/mantenimientos/reconciliaciones
{
  "prospecto_id": 1,
  "kardex_pago_id": 500,        // ← Asociar al kardex creado
  "bank": "Banco Industrial",
  "reference": "REF-2024-001",
  "amount": 1500.00,
  "date": "2024-12-15",
  "status": "conciliado"
}

// Resultado: ReconciliationRecord #200 creado
// Cadena completa: Cuota #100 → Kardex #500 → Reconciliation #200
```

### Flujo de Eliminación (Cascada)

#### Eliminar Cuota con Pagos

```typescript
// Solicitud
DELETE /api/mantenimientos/cuotas/100

// Backend ejecuta automáticamente:
// 1. Busca kardex con cuota_id = 100 → Encuentra Kardex #500
// 2. Busca reconciliation con monto + fecha + banco del Kardex #500 → Encuentra Reconciliation #200
// 3. DELETE reconciliation_records WHERE id = 200
// 4. DELETE kardex_pagos WHERE id = 500
// 5. DELETE cuotas_programa_estudiante WHERE id = 100
// 6. COMMIT

// Respuesta
{
  "message": "Cuota y registros relacionados eliminados exitosamente",
  "details": {
    "cuota_id": 100,
    "kardex_eliminados": 1,
    "reconciliaciones_eliminadas": 1
  }
}
```

### Matriz de Relaciones

| Acción | Cuota | Kardex | Reconciliation | Notas |
|--------|-------|--------|----------------|-------|
| **Crear Cuota** | ✅ Crea | ❌ No crea | ❌ No crea | Cuota independiente |
| **Crear Kardex** | ➖ Puede asociar | ✅ Crea | ❌ No crea | Asocia a cuota existente |
| **Crear Reconciliation** | ➖ Indirecto | ➖ Puede asociar | ✅ Crea | Asocia a kardex existente |
| **Eliminar Cuota sin pagos** | ✅ Elimina | ➖ No afecta | ➖ No afecta | Eliminación simple |
| **Eliminar Cuota con pagos** | ✅ Elimina | ✅ Elimina (cascada) | ✅ Elimina (cascada) | Borrado en cascada |
| **Eliminar Kardex** | ➖ No afecta | ✅ Elimina | ➖ No elimina | Kardex independiente |
| **Eliminar Reconciliation** | ➖ No afecta | ➖ No afecta | ✅ Elimina | Reconciliation independiente |

---

## 📱 Ejemplos Completos Frontend

### React + TypeScript - CRUD Completo de Cuotas

```typescript
// types.ts
export interface Cuota {
  id: number;
  estudiante_programa_id: number;
  numero_cuota: number;
  fecha_vencimiento: string;
  monto: number;
  estado: 'pendiente' | 'pagado' | 'vencido' | 'cancelado';
  paid_at?: string;
  total_pagado: number;
  saldo_pendiente: number;
  prospecto: {
    id: number;
    nombre: string;
    carnet: string;
  };
  programa: {
    id: number;
    nombre: string;
  };
}

// api/cuotas.ts
export class CuotasAPI {
  private baseURL = '/api/mantenimientos/cuotas';
  private token: string;

  constructor(token: string) {
    this.token = token;
  }

  private get headers() {
    return {
      'Authorization': `Bearer ${this.token}`,
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    };
  }

  async listar(filtros?: any) {
    const params = new URLSearchParams(filtros);
    const response = await fetch(`${this.baseURL}?${params}`, {
      headers: this.headers
    });
    
    if (!response.ok) throw new Error('Error al listar cuotas');
    return await response.json();
  }

  async obtener(id: number) {
    const response = await fetch(`${this.baseURL}/${id}`, {
      headers: this.headers
    });
    
    if (!response.ok) throw new Error('Error al obtener cuota');
    return await response.json();
  }

  async crear(data: {
    estudiante_programa_id: number;
    numero_cuota: number;
    fecha_vencimiento: string;
    monto: number;
    estado?: string;
  }) {
    const response = await fetch(this.baseURL, {
      method: 'POST',
      headers: this.headers,
      body: JSON.stringify(data)
    });
    
    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.message || 'Error al crear cuota');
    }
    
    return await response.json();
  }

  async actualizar(id: number, cambios: Partial<Cuota>) {
    const response = await fetch(`${this.baseURL}/${id}`, {
      method: 'PUT',
      headers: this.headers,
      body: JSON.stringify(cambios)
    });
    
    if (!response.ok) throw new Error('Error al actualizar cuota');
    return await response.json();
  }

  async eliminar(id: number) {
    const response = await fetch(`${this.baseURL}/${id}`, {
      method: 'DELETE',
      headers: this.headers
    });
    
    if (!response.ok) throw new Error('Error al eliminar cuota');
    return await response.json();
  }
}

// components/CuotasList.tsx
import React, { useState, useEffect } from 'react';
import { CuotasAPI } from '../api/cuotas';

const CuotasList: React.FC = () => {
  const [cuotas, setCuotas] = useState<Cuota[]>([]);
  const [loading, setLoading] = useState(false);
  const [filtros, setFiltros] = useState({
    estado: '',
    carnet: ''
  });

  const api = new CuotasAPI(localStorage.getItem('token') || '');

  useEffect(() => {
    cargarCuotas();
  }, [filtros]);

  const cargarCuotas = async () => {
    setLoading(true);
    try {
      const data = await api.listar(filtros);
      setCuotas(data.cuotas);
    } catch (error) {
      console.error(error);
      alert('Error al cargar cuotas');
    } finally {
      setLoading(false);
    }
  };

  const handleEliminar = async (cuota: Cuota) => {
    const mensaje = cuota.total_pagado > 0
      ? `Esta cuota tiene pagos registrados (Q ${cuota.total_pagado}). ` +
        `Se eliminarán en cascada los registros relacionados. ¿Continuar?`
      : '¿Está seguro de eliminar esta cuota?';

    if (!confirm(mensaje)) return;

    try {
      const result = await api.eliminar(cuota.id);
      
      alert(`Cuota eliminada exitosamente\n` +
        `- Kardex eliminados: ${result.details.kardex_eliminados}\n` +
        `- Reconciliaciones eliminadas: ${result.details.reconciliaciones_eliminadas}`
      );
      
      cargarCuotas();
    } catch (error) {
      alert('Error: ' + error.message);
    }
  };

  return (
    <div className="cuotas-list">
      {/* Filtros */}
      <div className="filtros">
        <input
          type="text"
          placeholder="Buscar por carnet..."
          value={filtros.carnet}
          onChange={e => setFiltros({...filtros, carnet: e.target.value})}
        />
        
        <select
          value={filtros.estado}
          onChange={e => setFiltros({...filtros, estado: e.target.value})}
        >
          <option value="">Todos los estados</option>
          <option value="pendiente">Pendiente</option>
          <option value="pagado">Pagado</option>
          <option value="vencido">Vencido</option>
        </select>
      </div>

      {/* Tabla */}
      {loading ? (
        <div>Cargando...</div>
      ) : (
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Estudiante</th>
              <th>Carnet</th>
              <th>Cuota #</th>
              <th>Vencimiento</th>
              <th>Monto</th>
              <th>Estado</th>
              <th>Saldo</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            {cuotas.map(cuota => (
              <tr key={cuota.id}>
                <td>{cuota.id}</td>
                <td>{cuota.prospecto.nombre}</td>
                <td>{cuota.prospecto.carnet}</td>
                <td>{cuota.numero_cuota}</td>
                <td>{cuota.fecha_vencimiento}</td>
                <td>Q {cuota.monto.toFixed(2)}</td>
                <td>
                  <span className={`badge badge-${cuota.estado}`}>
                    {cuota.estado}
                  </span>
                </td>
                <td>Q {cuota.saldo_pendiente.toFixed(2)}</td>
                <td>
                  <button onClick={() => handleEliminar(cuota)}>
                    Eliminar
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
};

export default CuotasList;
```

---

## ⚠️ Consideraciones Importantes

### Transacciones y Rollback

Todos los métodos de creación/actualización/eliminación usan transacciones:

```php
DB::beginTransaction();
try {
    // Operaciones...
    DB::commit();
} catch (\Throwable $th) {
    DB::rollBack();
    // Manejo de error
}
```

Si algo falla, **todos los cambios se revierten automáticamente**.

### Logs

Todas las operaciones importantes se registran en `storage/logs/laravel.log`:

```
[2025-10-23 22:00:00] production.INFO: ✅ Cuota creada manualmente
[2025-10-23 22:05:00] production.INFO: 🗑️ Iniciando eliminación de cuota
[2025-10-23 22:05:01] production.INFO: ⚠️ Cuota con kardex relacionados - eliminación en cascada
[2025-10-23 22:05:02] production.INFO: ✅ Eliminación en cascada completada exitosamente
```

### Validaciones

- **Número de cuota duplicado:** No se permite crear dos cuotas con el mismo `numero_cuota` para el mismo `estudiante_programa_id`
- **Fechas:** Todas las fechas deben ser válidas y en formato ISO (YYYY-MM-DD)
- **Montos:** Deben ser >= 0
- **Estados:** Solo valores permitidos: `pendiente`, `pagado`, `vencido`, `cancelado`

### Paginación

- Default: 100 registros por página
- Máximo: 500 registros por página
- Usar `page` y `per_page` o `limit` según el endpoint

---

## 🚀 Testing con Postman/Insomnia

### Colección de Ejemplos

```json
{
  "name": "Mantenimientos API",
  "requests": [
    {
      "name": "Dashboard General",
      "method": "GET",
      "url": "{{base_url}}/api/mantenimientos/kardex/dashboard",
      "headers": {
        "Authorization": "Bearer {{token}}"
      }
    },
    {
      "name": "Crear Cuota",
      "method": "POST",
      "url": "{{base_url}}/api/mantenimientos/cuotas",
      "headers": {
        "Authorization": "Bearer {{token}}",
        "Content-Type": "application/json"
      },
      "body": {
        "estudiante_programa_id": 1,
        "numero_cuota": 1,
        "fecha_vencimiento": "2024-12-31",
        "monto": 1500.00,
        "estado": "pendiente"
      }
    },
    {
      "name": "Eliminar Cuota (Cascada)",
      "method": "DELETE",
      "url": "{{base_url}}/api/mantenimientos/cuotas/{{cuota_id}}",
      "headers": {
        "Authorization": "Bearer {{token}}"
      }
    }
  ]
}
```

---

## 📞 Soporte

Si encuentras problemas o necesitas clarificaciones:

1. Revisa los logs en `storage/logs/laravel.log`
2. Verifica que el token de autenticación sea válido
3. Confirma que los IDs de relaciones existan en la BD
4. Usa Postman para probar los endpoints individualmente

---

**Última actualización:** 2025-10-23  
**Versión del API:** 1.0  
**Autor:** GitHub Copilot
