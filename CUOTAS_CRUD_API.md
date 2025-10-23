# CRUD de Cuotas - Documentación API

## Endpoints Disponibles

Todas las rutas están bajo el prefijo: `/api/mantenimientos/cuotas`

---

## 1. Listar Cuotas (DataTable)

**GET** `/api/mantenimientos/cuotas`

### Parámetros Query (opcionales):
- `carnet` - Buscar por carnet del prospecto
- `prospecto_id` - Filtrar por ID del prospecto
- `estudiante_programa_id` - Filtrar por ID de estudiante-programa
- `estado` - Filtrar por estado: `pendiente`, `pagado`, `vencido`, `cancelado`
- `fecha_vencimiento_desde` - Fecha inicio (YYYY-MM-DD)
- `fecha_vencimiento_hasta` - Fecha fin (YYYY-MM-DD)
- `q` - Búsqueda general (nombre o carnet)
- `page` - Número de página (default: 1)
- `limit` - Registros por página (default: 100, max: 500)

### Ejemplo de Request:
```bash
GET /api/mantenimientos/cuotas?carnet=ASM20251&estado=pendiente&page=1&limit=50
```

### Respuesta:
```json
{
  "timestamp": "2025-10-23T10:30:00Z",
  "pagination": {
    "total": 150,
    "per_page": 50,
    "current_page": 1,
    "last_page": 3
  },
  "cuotas": [
    {
      "id": 1,
      "estudiante_programa_id": 123,
      "numero_cuota": 1,
      "fecha_vencimiento": "2025-12-31",
      "monto": 500.00,
      "estado": "pendiente",
      "paid_at": null,
      "total_pagado": 0.00,
      "saldo_pendiente": 500.00,
      "prospecto": {
        "id": 45,
        "nombre_completo": "Juan Pérez",
        "carnet": "ASM20251",
        "correo_electronico": "juan@example.com",
        "telefono": "12345678"
      },
      "programa": {
        "id": 10,
        "nombre": "Maestría en Administración"
      },
      "created_at": "2025-10-01 10:00:00",
      "updated_at": "2025-10-01 10:00:00"
    }
  ]
}
```

---

## 2. Ver Detalle de una Cuota

**GET** `/api/mantenimientos/cuotas/{id}`

### Respuesta:
```json
{
  "timestamp": "2025-10-23T10:30:00Z",
  "cuota": {
    "id": 1,
    "estudiante_programa_id": 123,
    "numero_cuota": 1,
    "fecha_vencimiento": "2025-12-31",
    "monto": 500.00,
    "estado": "pendiente",
    "paid_at": null,
    "total_pagado": 250.00,
    "saldo_pendiente": 250.00,
    "prospecto": {
      "id": 45,
      "nombre_completo": "Juan Pérez",
      "carnet": "ASM20251",
      "correo_electronico": "juan@example.com",
      "telefono": "12345678"
    },
    "programa": {
      "id": 10,
      "nombre": "Maestría en Administración"
    },
    "created_at": "2025-10-01 10:00:00",
    "updated_at": "2025-10-01 10:00:00"
  },
  "pagos": [
    {
      "id": 501,
      "fecha_pago": "2025-10-15",
      "monto_pagado": 250.00,
      "metodo_pago": "transferencia",
      "estado_pago": "aprobado",
      "numero_boleta": "BOL123456",
      "banco": "BAC",
      "observaciones": "Pago parcial"
    }
  ]
}
```

---

## 3. Crear Nueva Cuota

**POST** `/api/mantenimientos/cuotas`

### Body (JSON):
```json
{
  "estudiante_programa_id": 123,
  "numero_cuota": 2,
  "fecha_vencimiento": "2025-12-31",
  "monto": 500.00,
  "estado": "pendiente"
}
```

### Validaciones:
- `estudiante_programa_id` - **requerido**, debe existir en `estudiante_programas`
- `numero_cuota` - **requerido**, entero >= 0
- `fecha_vencimiento` - **requerido**, formato fecha válido
- `monto` - **requerido**, numérico >= 0
- `estado` - opcional, valores: `pendiente`, `pagado`, `vencido`, `cancelado` (default: `pendiente`)

### Respuesta (201 Created):
```json
{
  "message": "Cuota creada exitosamente",
  "cuota": {
    "id": 152,
    "estudiante_programa_id": 123,
    "numero_cuota": 2,
    "fecha_vencimiento": "2025-12-31",
    "monto": 500.00,
    "estado": "pendiente",
    "prospecto": "Juan Pérez",
    "programa": "Maestría en Administración"
  }
}
```

---

## 4. Actualizar Cuota

**PUT** `/api/mantenimientos/cuotas/{id}`

### Body (JSON) - Todos los campos son opcionales:
```json
{
  "numero_cuota": 3,
  "fecha_vencimiento": "2025-11-30",
  "monto": 600.00,
  "estado": "pagado",
  "paid_at": "2025-10-20"
}
```

### Respuesta:
```json
{
  "message": "Cuota actualizada exitosamente",
  "cuota": {
    "id": 152,
    "estudiante_programa_id": 123,
    "numero_cuota": 3,
    "fecha_vencimiento": "2025-11-30",
    "monto": 600.00,
    "estado": "pagado",
    "paid_at": "2025-10-20 00:00:00",
    "prospecto": "Juan Pérez",
    "programa": "Maestría en Administración"
  }
}
```

---

## 5. Eliminar Cuota

**DELETE** `/api/mantenimientos/cuotas/{id}`

⚠️ **Importante**: Solo se puede eliminar si **NO tiene pagos aplicados** en la tabla `kardex_pagos`.

### Respuesta exitosa:
```json
{
  "message": "Cuota eliminada exitosamente"
}
```

### Respuesta error (400):
```json
{
  "message": "No se puede eliminar la cuota porque tiene pagos aplicados"
}
```

---

## 6. Listar Cuotas por Estudiante

**GET** `/api/mantenimientos/cuotas/estudiante`

### Parámetros Query (uno requerido):
- `estudiante_programa_id` - ID del estudiante-programa
- `prospecto_id` - ID del prospecto

### Ejemplo:
```bash
GET /api/mantenimientos/cuotas/estudiante?prospecto_id=45
```

### Respuesta:
```json
{
  "timestamp": "2025-10-23T10:30:00Z",
  "prospecto": "Juan Pérez",
  "carnet": "ASM20251",
  "programa": "Maestría en Administración",
  "total_cuotas": 10,
  "total_monto": 5000.00,
  "total_pagado": 2500.00,
  "saldo_pendiente": 2500.00,
  "cuotas": [
    {
      "id": 1,
      "numero_cuota": 1,
      "fecha_vencimiento": "2025-01-31",
      "monto": 500.00,
      "estado": "pagado",
      "paid_at": "2025-01-25 14:30:00",
      "total_pagado": 500.00,
      "saldo_pendiente": 0.00
    },
    {
      "id": 2,
      "numero_cuota": 2,
      "fecha_vencimiento": "2025-02-28",
      "monto": 500.00,
      "estado": "pendiente",
      "paid_at": null,
      "total_pagado": 250.00,
      "saldo_pendiente": 250.00
    }
  ]
}
```

---

## Códigos de Estado HTTP

- **200 OK** - Operación exitosa (GET, PUT)
- **201 Created** - Cuota creada exitosamente
- **400 Bad Request** - Error de validación o lógica de negocio
- **404 Not Found** - Cuota no encontrada
- **422 Unprocessable Entity** - Error de validación de campos
- **500 Internal Server Error** - Error del servidor

---

## Notas Importantes

1. **Autenticación**: Todas las rutas requieren autenticación con token Bearer (Sanctum).

2. **Soft Deletes**: Las cuotas eliminadas se marcan con `deleted_at` y el campo `deleted_by` guarda el ID del usuario que eliminó.

3. **Auditoría**: Los campos `created_by` y `updated_by` se llenan automáticamente con el usuario autenticado.

4. **Relaciones**:
   - Cuota → EstudiantePrograma → Prospecto
   - Cuota → EstudiantePrograma → Programa
   - Cuota → KardexPago (pagos aplicados)

5. **Cálculos**:
   - `total_pagado`: Suma de `kardex_pagos.monto_pagado` donde `estado_pago = 'aprobado'`
   - `saldo_pendiente`: `monto - total_pagado`

---

## Ejemplos con cURL

### Listar cuotas filtradas
```bash
curl -X GET "http://localhost:8000/api/mantenimientos/cuotas?carnet=ASM20251&estado=pendiente" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Crear cuota
```bash
curl -X POST "http://localhost:8000/api/mantenimientos/cuotas" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "estudiante_programa_id": 123,
    "numero_cuota": 5,
    "fecha_vencimiento": "2025-12-31",
    "monto": 500.00
  }'
```

### Actualizar cuota
```bash
curl -X PUT "http://localhost:8000/api/mantenimientos/cuotas/152" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "estado": "pagado",
    "paid_at": "2025-10-23"
  }'
```

### Eliminar cuota
```bash
curl -X DELETE "http://localhost:8000/api/mantenimientos/cuotas/152" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## Frontend: Integración con DataTable

### Ejemplo con Axios (Vue.js/React):
```javascript
// Listar cuotas con paginación y filtros
const fetchCuotas = async (page = 1, filters = {}) => {
  const params = new URLSearchParams({
    page,
    limit: 50,
    ...filters
  });
  
  const response = await axios.get(`/api/mantenimientos/cuotas?${params}`, {
    headers: { Authorization: `Bearer ${token}` }
  });
  
  return response.data;
};

// Uso
const data = await fetchCuotas(1, { 
  carnet: 'ASM20251', 
  estado: 'pendiente' 
});
```

---

## Testing

### Probar con Postman o Insomnia:

1. **Login** para obtener token
2. Crear estudiante-programa si no existe
3. Crear cuota con el endpoint POST
4. Listar cuotas para verificar
5. Actualizar estado a "pagado"
6. Intentar eliminar (debería fallar si tiene pagos)

---

¿Necesitas algo más específico o algún ajuste a los endpoints?
