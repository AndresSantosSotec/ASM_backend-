# Data Flow Diagram - Payment History Import

## Flow Process

```
┌─────────────────────────────────────────────────────────────────────┐
│                    EXCEL FILE IMPORT PROCESS                        │
└─────────────────────────────────────────────────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│  Step 1: Normalize Carnet                                           │
│  ─────────────────────────────────────────────────────────────────  │
│  Input:  "ASM 2025 2962" or "asm20252962"                          │
│  Output: "ASM20252962" (uppercase, no spaces)                       │
└─────────────────────────────────────────────────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│  Step 2: Query Prospecto (Student)                                  │
│  ─────────────────────────────────────────────────────────────────  │
│  Table: prospectos                                                   │
│  Query: WHERE REPLACE(UPPER(p.carnet), ' ', '') = 'ASM20252962'    │
│  Returns: prospecto_id, carnet, nombres, apellidos                  │
└─────────────────────────────────────────────────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│  Step 3: Find Active Programs                                       │
│  ─────────────────────────────────────────────────────────────────  │
│  Table: estudiante_programa                                          │
│  Query: WHERE prospecto_id = X AND estado = 'activo' ✅ FIXED       │
│  Returns: estudiante_programa_id, programa_id, fecha_inscripcion    │
└─────────────────────────────────────────────────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│  Step 4: Find Pending Quotas (Cuotas)                              │
│  ─────────────────────────────────────────────────────────────────  │
│  Table: cuotas_programa_estudiante                                   │
│  Query: WHERE estudiante_programa_id = X AND estado = 'pendiente'   │
│  Strategy: Match by amount, date, or chronological order            │
│  Returns: cuota_id, monto, fecha_vencimiento                        │
└─────────────────────────────────────────────────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│  Step 5: Create KardexPago Entry                                    │
│  ─────────────────────────────────────────────────────────────────  │
│  Table: kardex_pagos                                                 │
│  Fields:                                                             │
│    - estudiante_programa_id (link to program)                       │
│    - cuota_id (link to quota)                                       │
│    - numero_boleta (receipt number)                                 │
│    - monto_pagado (amount paid)                                     │
│    - fecha_pago (payment date)                                      │
│    - banco (bank name, defaults to 'EFECTIVO') ✅ NEW               │
│    - estado_pago = 'aprobado'                                       │
│    - uploaded_by, created_by ✅ NEW FIELDS                          │
└─────────────────────────────────────────────────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│  Step 6: Update Cuota Status                                        │
│  ─────────────────────────────────────────────────────────────────  │
│  Table: cuotas_programa_estudiante                                   │
│  Update:                                                             │
│    - estado = 'pagado'                                              │
│    - paid_at = fecha_pago                                           │
│    - updated_at = now()                                             │
└─────────────────────────────────────────────────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│  Step 7: Create Automatic Reconciliation                            │
│  ─────────────────────────────────────────────────────────────────  │
│  Table: reconciliation_records                                       │
│  Fields:                                                             │
│    - bank (normalized bank name)                                    │
│    - reference (receipt number)                                     │
│    - amount (payment amount)                                        │
│    - date (payment date)                                            │
│    - fingerprint (MD5 hash for uniqueness)                          │
│    - status = 'conciliado'                                          │
│    - kardex_pago_id (link to KardexPago) ✅ NEW RELATIONSHIP        │
│    - uploaded_by                                                    │
└─────────────────────────────────────────────────────────────────────┘
```

## Database Relationships

```
┌──────────────┐      ┌────────────────────┐      ┌──────────────────────────┐
│  prospectos  │      │ estudiante_programa│      │ cuotas_programa_estudiante│
│              │      │                    │      │                           │
│ id           │◄─────┤ prospecto_id       │◄─────┤ estudiante_programa_id    │
│ carnet       │      │ id                 │      │ id                        │
│ nombres      │      │ programa_id        │      │ numero_cuota              │
│ apellidos    │      │ estado             │      │ monto                     │
└──────────────┘      └────────────────────┘      │ estado                    │
                                                   │ paid_at                   │
                                                   └───────────┬───────────────┘
                                                               │
                                                               │ cuota_id
                                                               ▼
                      ┌────────────────────┐      ┌──────────────────────────┐
                      │ kardex_pagos       │      │ reconciliation_records    │
                      │                    │      │                           │
                      │ id                 │◄─────┤ kardex_pago_id ✅ NEW     │
                      │ estudiante_prog_id │      │ bank                      │
                      │ cuota_id           │      │ reference                 │
                      │ numero_boleta      │      │ amount                    │
                      │ monto_pagado       │      │ date                      │
                      │ fecha_pago         │      │ fingerprint               │
                      │ banco ✅ IMPROVED   │      │ status                    │
                      │ estado_pago        │      │ uploaded_by               │
                      │ uploaded_by ✅ NEW  │      └──────────────────────────┘
                      │ created_by ✅ NEW   │
                      └────────────────────┘
```

## Key Improvements

### 1. SQL Syntax Fix
**Before**: `WHERE ep.estado = activo` (unquoted, causes error)
**After**: `WHERE ep.estado = ?` with binding `['activo']` (properly quoted)

### 2. Default Bank Value
**Before**: `'No especificado'`
**After**: `'EFECTIVO'` when field is empty or null

### 3. Relationship Chain
**New**: ReconciliationRecord now links to KardexPago via `kardex_pago_id`
**Benefit**: Full traceability from bank statement to payment to quota

### 4. Complete Data Chain
```
Carnet → Prospecto → EstudiantePrograma → Cuota → KardexPago → ReconciliationRecord
```

## Transaction Safety

All operations in Steps 5-7 occur within a single database transaction:
- If any step fails, all changes are rolled back
- Prevents partial data corruption
- Ensures data consistency
- With SQL fix, transactions complete successfully ✅
