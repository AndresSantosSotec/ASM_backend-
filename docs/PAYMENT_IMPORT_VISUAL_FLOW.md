# Payment History Import - Visual Flow Diagram

## Before vs After Comparison

### BEFORE (Old Logic)
```
Excel File
    ↓
┌─────────────────────────────────────┐
│ Columns: carnet, nombre, boleta,    │
│          monto, fecha_pago,         │
│          mensualidad_aprobada       │
└─────────────────────────────────────┘
    ↓
┌─────────────────────────────────────┐
│ Process Each Payment                │
│ - Find student                      │
│ - Find program                      │
│ - ALL payments assigned to quota    │ ← No distinction
└─────────────────────────────────────┘
    ↓
┌─────────────────────────────────────┐
│ If no quotas exist:                 │
│ - Generate ALL quotas from scratch  │ ← Creates all at once
└─────────────────────────────────────┘
    ↓
Result: All payments = quotas
```

### AFTER (New Logic)
```
Excel File
    ↓
┌─────────────────────────────────────┐
│ Columns: carnet, nombre, boleta,    │
│          monto, fecha_pago,         │
│          tipo_pago, mes_pago, año   │ ← NEW columns
│          concepto, estatus          │
└─────────────────────────────────────┘
    ↓
┌─────────────────────────────────────┐
│ Process Each Payment                │
│ - Find student                      │
│ - Find program                      │
│ - Check tipo_pago                   │ ← NEW logic
│                                     │
│   ┌─────────────────────┐          │
│   │ tipo_pago MENSUAL?  │          │
│   └─────────────────────┘          │
│      Yes ↓         No ↓             │
│   Assign quota   Skip quota         │ ← Smart assignment
└─────────────────────────────────────┘
    ↓
┌─────────────────────────────────────┐
│ Check estudiante_programa:          │ ← NEW: Check before creating
│ - duracion_meses = 12               │
│ - cuotas_existentes = 8             │
│ - cuotas_faltantes = 4              │
└─────────────────────────────────────┘
    ↓
┌─────────────────────────────────────┐
│ Create ONLY missing quotas:         │ ← NEW: Smart creation
│ - Create quotas #9, #10, #11, #12  │
│ - Amount = cuota_mensual            │
└─────────────────────────────────────┘
    ↓
Result: Smart quota assignment + auto-generation
```

## Payment Type Flow

```
┌─────────────────────────────────────────────────────────┐
│                    Excel Row                            │
│  tipo_pago = ?                                          │
└─────────────────────────────────────────────────────────┘
                        ↓
        ┌───────────────┴───────────────┐
        │   esPagoMensual(tipo_pago)    │
        └───────────────┬───────────────┘
                        ↓
        ┌───────────────┴───────────────┐
        │                               │
    Contains:                       Contains:
    - MENSUAL                       - ESPECIAL
    - MENSUALIDAD                   - INSCRIPCION
    - CUOTA                         - RECARGO
    - CUOTA MENSUAL                 - MORA
        │                               │
        ↓                               ↓
┌───────────────┐               ┌───────────────┐
│  Return TRUE  │               │  Return FALSE │
└───────────────┘               └───────────────┘
        │                               │
        ↓                               ↓
┌───────────────┐               ┌───────────────┐
│ Assign Quota  │               │  Skip Quota   │
│ Create Kardex │               │ Create Kardex │
│ Update Quota  │               │  (no update)  │
└───────────────┘               └───────────────┘
```

## Quota Generation Flow

```
┌────────────────────────────────────────────┐
│  Import starts for student                 │
│  estudiante_programa_id = 123             │
└────────────────────────────────────────────┘
            ↓
┌────────────────────────────────────────────┐
│  Load estudiante_programa data:           │
│  - duracion_meses = 12                    │
│  - cuota_mensual = 1500.00                │
│  - fecha_inicio = 2024-01-01              │
└────────────────────────────────────────────┘
            ↓
┌────────────────────────────────────────────┐
│  Count existing quotas in DB:             │
│  SELECT COUNT(*) FROM cuotas_programa...  │
│  Result: 8 quotas                         │
└────────────────────────────────────────────┘
            ↓
┌────────────────────────────────────────────┐
│  Compare:                                  │
│  cuotas_existentes (8) < duracion_meses (12)?│
└────────────────────────────────────────────┘
            ↓ YES
┌────────────────────────────────────────────┐
│  Calculate missing:                        │
│  cuotas_faltantes = 12 - 8 = 4            │
└────────────────────────────────────────────┘
            ↓
┌────────────────────────────────────────────┐
│  Create missing quotas:                    │
│  FOR i = 9 TO 12:                         │
│    - numero_cuota = i                     │
│    - monto = 1500.00                      │
│    - fecha_vencimiento = fecha_inicio + i │
│    - estado = 'pendiente'                 │
└────────────────────────────────────────────┘
            ↓
┌────────────────────────────────────────────┐
│  Result: Total 12 quotas now exist         │
│  - 8 old quotas (unchanged)               │
│  - 4 new quotas (created)                 │
└────────────────────────────────────────────┘
```

## Example Scenario

### Student Data:
```
estudiante_programa:
  duracion_meses = 12
  cuota_mensual = 1500.00
  fecha_inicio = 2024-01-01

Existing quotas: 5 (quotas #1-#5)
```

### Excel Import:
```
Row 1: tipo_pago=MENSUAL,     monto=1500, fecha=2024-06-15
Row 2: tipo_pago=MENSUAL,     monto=1500, fecha=2024-07-15
Row 3: tipo_pago=ESPECIAL,    monto=500,  fecha=2024-08-15
Row 4: tipo_pago=INSCRIPCION, monto=300,  fecha=2024-09-15
```

### Processing:
```
Step 1: Check quotas
  - Expected: 12 quotas
  - Existing: 5 quotas
  - Missing: 7 quotas
  → Creates quotas #6-#12 ✅

Step 2: Process Row 1 (MENSUAL)
  → Assigns to Quota #6 ✅
  → Updates quota to 'pagado' ✅

Step 3: Process Row 2 (MENSUAL)
  → Assigns to Quota #7 ✅
  → Updates quota to 'pagado' ✅

Step 4: Process Row 3 (ESPECIAL)
  → Creates Kardex only ✅
  → NO quota assignment ✅

Step 5: Process Row 4 (INSCRIPCION)
  → Creates Kardex only ✅
  → NO quota assignment ✅
```

### Final State:
```
Quotas:
  #1-#5: pendiente (pre-existing)
  #6: pagado (assigned to Row 1)
  #7: pagado (assigned to Row 2)
  #8-#12: pendiente (auto-generated)

Kardex Entries:
  - 4 entries created (one per row)
  - 2 with quota assignment
  - 2 without quota assignment
```

## Benefits Visualization

```
┌──────────────────────────────────────────────────────────┐
│                    OLD APPROACH                          │
├──────────────────────────────────────────────────────────┤
│ ❌ All payments treated equally                          │
│ ❌ Creates all quotas from scratch                       │
│ ❌ May create duplicate quotas                           │
│ ❌ No payment type distinction                           │
│ ❌ Limited Excel structure                               │
└──────────────────────────────────────────────────────────┘

                        ↓ UPGRADE

┌──────────────────────────────────────────────────────────┐
│                    NEW APPROACH                          │
├──────────────────────────────────────────────────────────┤
│ ✅ Smart payment type handling                           │
│ ✅ Only creates missing quotas                           │
│ ✅ No duplicate quota creation                           │
│ ✅ Distinguishes monthly vs special                      │
│ ✅ Enhanced Excel structure                              │
│ ✅ Backwards compatible                                  │
└──────────────────────────────────────────────────────────┘
```

## Key Decision Points

```
┌──────────────────────────────────────────────────┐
│ Should this payment be assigned to a quota?      │
└──────────────────────────────────────────────────┘
                    ↓
    ┌───────────────┴───────────────┐
    │                               │
    tipo_pago = MENSUAL        tipo_pago = ESPECIAL
    tipo_pago = CUOTA          tipo_pago = INSCRIPCION
    tipo_pago = MENSUALIDAD    tipo_pago = RECARGO
    │                               │
    ↓ YES                          ↓ NO
Assign to quota              Create Kardex only


┌──────────────────────────────────────────────────┐
│ Should we create more quotas?                    │
└──────────────────────────────────────────────────┘
                    ↓
    ┌───────────────┴───────────────┐
    │                               │
    Existing < Expected        Existing >= Expected
    (e.g., 8 < 12)            (e.g., 12 >= 12)
    │                               │
    ↓ YES                          ↓ NO
Create missing quotas         No action needed
(quotas #9-#12)
```
