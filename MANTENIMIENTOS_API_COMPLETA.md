# API Mantenimientos - CRUD Completo

## 📋 Resumen de Endpoints

Todos los endpoints bajo el prefijo: `/api/mantenimientos`

---

## 🔹 CUOTAS (Gestión de pagos programados)

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/cuotas` | Listar cuotas con filtros (DataTable) |
| GET | `/cuotas/{id}` | Ver detalle de una cuota |
| GET | `/cuotas/estudiante` | Listar cuotas de un estudiante |
| POST | `/cuotas` | Crear nueva cuota |
| PUT | `/cuotas/{id}` | Actualizar cuota |
| DELETE | `/cuotas/{id}` | Eliminar cuota |

### Filtros disponibles en `/cuotas`:
- `carnet` - Buscar por carnet del estudiante
- `prospecto_id` - Filtrar por ID del prospecto
- `estudiante_programa_id` - Filtrar por ID estudiante-programa
- `estado` - Filtrar por estado (pendiente, pagado, vencido, cancelado)
- `fecha_vencimiento_desde` / `fecha_vencimiento_hasta` - Rango de fechas
- `q` - Búsqueda general (nombre o carnet)
- `page` - Página actual
- `limit` - Registros por página (max 500)

---

## 💰 KARDEX (Movimientos de pago registrados)

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/kardex` | Listar movimientos de kardex con filtros |
| GET | `/kardex/{id}` | Ver detalle de un movimiento |
| POST | `/kardex` | Crear nuevo movimiento de pago |
| PUT | `/kardex/{id}` | Actualizar movimiento |
| DELETE | `/kardex/{id}` | Eliminar movimiento |

### Filtros disponibles en `/kardex`:
- `carnet` - Buscar por carnet del estudiante
- `prospecto_id` - Filtrar por ID del prospecto
- `estudiante_programa_id` - Filtrar por ID estudiante-programa
- `estado_pago` - Filtrar por estado (pendiente_revision, aprobado, rechazado)
- `metodo_pago` - Filtrar por método de pago
- `banco` - Filtrar por banco
- `fecha_pago_desde` / `fecha_pago_hasta` - Rango de fechas de pago
- `q` - Búsqueda general (boleta, nombre, carnet)
- `page` - Página actual
- `limit` - Registros por página

### Ejemplo POST `/kardex`:
```json
{
  "estudiante_programa_id": 123,
  "cuota_id": 45,
  "fecha_pago": "2025-10-23",
  "fecha_recibo": "2025-10-23",
  "monto_pagado": 500.00,
  "metodo_pago": "transferencia",
  "estado_pago": "aprobado",
  "numero_boleta": "BOL123456",
  "banco": "BAC",
  "observaciones": "Pago completo de cuota 1"
}
```

---

## 🏦 RECONCILIACIONES (Conciliaciones bancarias)

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/reconciliaciones` | Listar reconciliaciones con filtros |
| GET | `/reconciliaciones/{id}` | Ver detalle de una reconciliación |
| POST | `/reconciliaciones` | Crear reconciliación manual |
| PUT | `/reconciliaciones/{id}` | Actualizar reconciliación (conciliar) |
| DELETE | `/reconciliaciones/{id}` | Eliminar reconciliación |

### Filtros disponibles en `/reconciliaciones`:
- `prospecto_id` - Filtrar por ID del prospecto
- `status` - Filtrar por estado (imported, conciliado, rechazado, sin_coincidencia)
- `bank` - Filtrar por banco
- `reference` - Filtrar por referencia bancaria
- `fecha_desde` / `fecha_hasta` - Rango de fechas
- `q` - Búsqueda general (referencia, banco, nombre)
- `page` - Página actual
- `limit` - Registros por página

### Ejemplo POST `/reconciliaciones`:
```json
{
  "prospecto_id": 45,
  "kardex_pago_id": 501,
  "bank": "BAC",
  "reference": "REF987654321",
  "amount": 500.00,
  "date": "2025-10-23",
  "status": "conciliado",
  "notes": "Conciliado automáticamente"
}
```

### Ejemplo PUT `/reconciliaciones/{id}` (Conciliar):
```json
{
  "prospecto_id": 45,
  "kardex_pago_id": 501,
  "status": "conciliado",
  "notes": "Conciliado manualmente por admin"
}
```

---

## 📊 DASHBOARDS (Reportes consolidados)

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/kardex/dashboard` | Resumen general de kardex y cuotas |
| GET | `/kardex/datos` | Datos tabulares de kardex, reconciliaciones y cuotas |
| GET | `/cuotas/dashboard` | Dashboard de cuotas por estudiante |
| GET | `/estudiantes/activos` | Listar estudiantes activos con info adicional |

### Filtros comunes en dashboards:
- `prospecto_id` - Filtrar por prospecto específico
- `programa_id` - Filtrar por programa
- `search` - Búsqueda general (nombre, carnet, correo)
- `fecha_inicio` / `fecha_fin` - Rango de fechas
- `limit` - Límite de registros

---

## 🔐 Autenticación

Todos los endpoints requieren autenticación con token Bearer (Sanctum):

```bash
Authorization: Bearer YOUR_TOKEN_HERE
```

---

## 📝 Códigos de Estado HTTP

- **200 OK** - Operación exitosa (GET, PUT)
- **201 Created** - Recurso creado exitosamente (POST)
- **400 Bad Request** - Error de lógica de negocio
- **404 Not Found** - Recurso no encontrado
- **422 Unprocessable Entity** - Error de validación
- **500 Internal Server Error** - Error del servidor

---

## 🧪 Ejemplos de Uso con cURL

### Listar cuotas pendientes de un estudiante
```bash
curl -X GET "http://localhost:8000/api/mantenimientos/cuotas?carnet=ASM20251&estado=pendiente" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Crear movimiento de kardex
```bash
curl -X POST "http://localhost:8000/api/mantenimientos/kardex" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "estudiante_programa_id": 123,
    "cuota_id": 45,
    "fecha_pago": "2025-10-23",
    "monto_pagado": 500.00,
    "metodo_pago": "transferencia",
    "estado_pago": "aprobado",
    "numero_boleta": "BOL123456",
    "banco": "BAC"
  }'
```

### Actualizar estado de reconciliación
```bash
curl -X PUT "http://localhost:8000/api/mantenimientos/reconciliaciones/78" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "prospecto_id": 45,
    "kardex_pago_id": 501,
    "status": "conciliado",
    "notes": "Conciliado manualmente"
  }'
```

### Obtener dashboard de cuotas con filtros
```bash
curl -X GET "http://localhost:8000/api/mantenimientos/cuotas/dashboard?programa_id=10&limit=50" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## 🔄 Flujo de Trabajo Típico

### 1. Importar Reconciliaciones Bancarias
```
POST /reconciliaciones
{
  "bank": "BAC",
  "reference": "REF123",
  "amount": 500.00,
  "date": "2025-10-23",
  "status": "imported"
}
```

### 2. Buscar Coincidencias (Frontend o automático)
```
GET /kardex?monto_pagado=500&fecha_pago_desde=2025-10-20&fecha_pago_hasta=2025-10-25
```

### 3. Conciliar Movimiento
```
PUT /reconciliaciones/{id}
{
  "kardex_pago_id": 501,
  "prospecto_id": 45,
  "status": "conciliado"
}
```

### 4. Actualizar Estado de Cuota (si aplica)
```
PUT /cuotas/{id}
{
  "estado": "pagado",
  "paid_at": "2025-10-23"
}
```

---

## 📌 Notas Importantes

### Cuotas:
- No se pueden eliminar si tienen pagos aplicados (kardex)
- El campo `paid_at` se actualiza automáticamente cuando `estado = "pagado"`
- `saldo_pendiente` se calcula: `monto - total_pagado` (pagos aprobados)

### Kardex:
- `estado_pago` valores: `pendiente_revision`, `aprobado`, `rechazado`
- Solo pagos con `estado_pago = "aprobado"` cuentan para el saldo
- `cuota_id` es opcional (pueden existir pagos sin cuota asignada)

### Reconciliaciones:
- `status` valores: `imported`, `conciliado`, `rechazado`, `sin_coincidencia`
- Al conciliar, se asocia `kardex_pago_id` y `prospecto_id`
- Las importaciones automáticas entran como `status = "imported"`

### Auditoría:
- Todos los modelos tienen `created_by`, `updated_by`, `deleted_by`
- Se llenan automáticamente con `auth()->id()`
- Soft deletes habilitado en todos los modelos

---

## 🐛 Debugging

Para habilitar logs de queries SQL en desarrollo, agregar en `.env`:
```
APP_DEBUG=true
```

Los logs de errores se guardan en `storage/logs/laravel.log` con contexto completo.

---

## 📦 Respuestas de Error

### Validación (422):
```json
{
  "message": "Error de validación",
  "errors": {
    "monto_pagado": ["El campo monto pagado es obligatorio."],
    "fecha_pago": ["El campo fecha pago debe ser una fecha válida."]
  }
}
```

### No encontrado (404):
```json
{
  "message": "No query results for model [App\\Models\\KardexPago] 999"
}
```

### Error del servidor (500):
```json
{
  "message": "Error al crear el movimiento de kardex",
  "error": "SQLSTATE[23000]: Integrity constraint violation...",
  "trace": "..." // Solo en APP_DEBUG=true
}
```

---


