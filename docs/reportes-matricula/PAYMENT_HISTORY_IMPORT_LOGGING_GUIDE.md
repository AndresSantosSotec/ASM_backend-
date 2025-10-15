# Payment History Import - Logging and Debugging Guide

## Overview

The PaymentHistoryImport class now includes comprehensive logging at every step of the import process. This guide explains how to use the logs to debug issues when importing historical payment data.

## Data Flow Chain

The import process follows this chain:

```
Excel File → Carnet → Prospecto → EstudiantePrograma → Cuotas → Kardex → Reconciliation
```

Each step is logged with detailed information to help identify where failures occur.

## Logging Steps

### PASO 1: Finding Prospecto by Carnet

**What it does:** Searches for a student record in the `prospectos` table using the carnet from the Excel file.

**Success Log:**
```
✅ PASO 1 EXITOSO: Prospecto encontrado
{
    "carnet": "ASM20221234",
    "prospecto_id": 123,
    "nombre_completo": "Juan Pérez"
}
```

**Failure Log:**
```
❌ PASO 1 FALLIDO: Prospecto no encontrado
{
    "carnet": "ASM20221234",
    "problema": "No existe un registro en la tabla prospectos con este carnet"
}
```

**What to check if it fails:**
- Verify the carnet exists in the `prospectos` table
- Check for typos or spacing differences in the carnet
- Ensure the carnet format matches (e.g., ASM20221234)

### PASO 2: Finding Student Programs

**What it does:** Searches for programs associated with the prospecto in the `estudiante_programa` table.

**Success Log:**
```
✅ PASO 2 EXITOSO: Programas encontrados
{
    "prospecto_id": 123,
    "cantidad_programas": 2,
    "programa_ids": [45, 67]
}
```

**Failure Log:**
```
❌ PASO 2 FALLIDO: No hay programas para este prospecto
{
    "carnet": "ASM20221234",
    "prospecto_id": 123,
    "problema": "No existe ningún registro en estudiante_programa para este prospecto_id"
}
```

**What to check if it fails:**
- Verify records exist in `estudiante_programa` table for this `prospecto_id`
- Check if the student has been enrolled in any programs
- Verify the `prospecto_id` foreign key relationship

### PASO 3: Getting Active Program Details

**What it does:** Joins with `tb_programas` to get program names and filters for active programs only.

**Success Log:**
```
✅ PASO 3 EXITOSO: Programas activos obtenidos
{
    "carnet": "ASM20221234",
    "cantidad_programas_activos": 1,
    "programas": [
        {
            "estudiante_programa_id": 45,
            "programa": "Maestría en Marketing",
            "activo": true
        }
    ]
}
```

**Failure Log:**
```
❌ PASO 3 FALLIDO: No hay programas activos
{
    "carnet": "ASM20221234",
    "prospecto_id": 123,
    "problema": "Los programas existen pero ninguno está activo (prog.activo = false) o no tienen programa_id válido"
}
```

**What to check if it fails:**
- Verify the `programa_id` in `estudiante_programa` points to a valid record in `tb_programas`
- Check that the program has `activo = true` in `tb_programas`
- Verify the join between tables is correct

### PASO 4: Finding Cuotas

**What it does:** Searches for payment quotas in the `cuotas_programa_estudiante` table for the student program.

**Success Log:**
```
✅ PASO 4 EXITOSO: Cuotas encontradas
{
    "estudiante_programa_id": 45,
    "total_cuotas": 12,
    "cuotas_pendientes": 3,
    "cuotas_pagadas": 9,
    "resumen_cuotas": [
        {
            "id": 100,
            "numero": 1,
            "monto": 1000.00,
            "estado": "pagado",
            "vencimiento": "2022-01-15"
        },
        // ... more cuotas
    ]
}
```

**Failure Log:**
```
❌ PASO 4: No hay cuotas para este programa
{
    "estudiante_programa_id": 45,
    "problema": "No existen cuotas en cuotas_programa_estudiante para este estudiante_programa_id"
}
```

**What to check if it fails:**
- Verify cuotas were created for this `estudiante_programa_id`
- Check the payment plan generation process
- Ensure the student program enrollment included cuota creation

### PASO 5: Updating Cuota Status

**What it does:** Marks a cuota as "pagado" and records the payment timestamp.

**Success Log:**
```
✅ PASO 5 EXITOSO: Cuota marcada como pagada
{
    "cuota_id": 100,
    "numero_cuota": 3,
    "estado_nuevo": "pagado",
    "paid_at": "2022-03-15"
}
```

**Additional Info for Discrepancies:**
```
💰 Cuota actualizada con diferencia
{
    "cuota_id": 100,
    "monto_cuota": 1000.00,
    "monto_pagado": 950.00,
    "diferencia": 50.00,
    "tipo": "PAGO_MENOR"
}
```

### PASO 6: Creating Reconciliation Record

**What it does:** Creates a reconciliation record linking the payment to the bank statement.

**Success Log:**
```
✅ PASO 6 EXITOSO: Conciliación creada
{
    "kardex_id": 789,
    "fingerprint": "abc123def456",
    "status": "conciliado"
}
```

**Failure Log:**
```
❌ PASO 6 FALLIDO: Error creando conciliación
{
    "error": "Duplicate entry for fingerprint",
    "kardex_id": 789,
    "fingerprint": "abc123def456",
    "trace": [...]
}
```

## Common Error Scenarios

### Scenario 1: Student Not Found (PASO 1 Fails)

**Error:**
```
❌ PASO 1 FALLIDO: Prospecto no encontrado
```

**Solution:**
1. Check if the carnet in Excel matches the format in database
2. Look for spacing or case sensitivity issues
3. Verify the student exists in the `prospectos` table
4. Check if carnet was generated correctly when the student was created

### Scenario 2: Student Has No Programs (PASO 2 Fails)

**Error:**
```
❌ PASO 2 FALLIDO: No hay programas para este prospecto
```

**Solution:**
1. Check if the student was properly enrolled in a program
2. Verify `estudiante_programa` records exist
3. Check if enrollment process completed successfully
4. Look at `prospecto_id` foreign key in `estudiante_programa`

### Scenario 3: All Programs Are Inactive (PASO 3 Fails)

**Error:**
```
❌ PASO 3 FALLIDO: No hay programas activos
```

**Solution:**
1. Check `activo` field in `tb_programas` table
2. Verify the program wasn't deactivated
3. Update program status if needed
4. Check if `programa_id` foreign key is valid

### Scenario 4: No Cuotas Created (PASO 4 Fails)

**Error:**
```
❌ PASO 4: No hay cuotas para este programa
```

**Solution:**
1. Check if payment plan was generated during enrollment
2. Verify cuotas exist in `cuotas_programa_estudiante`
3. Run the cuota generation process if missing
4. Check enrollment completion status

### Scenario 5: No Matching Cuota Found

**Warning (not error):**
```
⚠️ No se encontró cuota pendiente para este pago
```

**Note:** The system will still create a Kardex record without assigning it to a specific cuota.

**Solution:**
1. Review if cuotas match expected payment amounts
2. Check if payment dates align with cuota due dates
3. Consider if there are custom payment arrangements
4. Review the `mensualidad_aprobada` value in Excel

## Error Summary Reports

At the end of processing, the system generates summary reports:

### Error Summary
```
📊 RESUMEN DE ERRORES POR TIPO
{
    "total_errores": 5,
    "tipos": {
        "ESTUDIANTE_NO_ENCONTRADO": {
            "cantidad": 3,
            "ejemplos": [
                "No se encontró ningún programa activo para este carnet",
                // ... more examples
            ]
        },
        "PROGRAMA_NO_IDENTIFICADO": {
            "cantidad": 2,
            "ejemplos": [...]
        }
    }
}
```

### Warning Summary
```
📊 RESUMEN DE ADVERTENCIAS POR TIPO
{
    "total_advertencias": 12,
    "tipos": {
        "SIN_CUOTA": {"cantidad": 5},
        "PAGO_PARCIAL": {"cantidad": 4},
        "DIFERENCIA_MONTO": {"cantidad": 3}
    }
}
```

## How to Read the Logs

### 1. Check Laravel Logs

Logs are written to Laravel's default log file:
```bash
tail -f storage/logs/laravel.log
```

### 2. Filter by Import Session

Each import has a timestamp in the initial log:
```
=== 🚀 INICIANDO PROCESAMIENTO ===
{
    "total_rows": 100,
    "timestamp": "2024-01-15 10:30:00"
}
```

### 3. Follow a Specific Student

Search for logs by carnet:
```bash
grep "ASM20221234" storage/logs/laravel.log
```

### 4. Find All Failures

Search for failure markers:
```bash
grep "❌" storage/logs/laravel.log
```

### 5. Check Summary at the End

Look for the completion marker:
```
=== ✅ PROCESAMIENTO COMPLETADO ===
```

## Debugging Workflow

### Step 1: Identify the Problem
1. Look at the completion summary to see error counts
2. Check the error summary to see error types
3. Identify which PASO is failing most often

### Step 2: Drill Down
1. Pick one failing carnet from the error log
2. Follow its logs through all PASOs
3. Identify exactly where the chain breaks

### Step 3: Fix the Data
1. Based on the PASO that failed, fix the underlying data
2. Common fixes:
   - PASO 1: Fix carnet in prospectos table
   - PASO 2: Create estudiante_programa record
   - PASO 3: Activate the program in tb_programas
   - PASO 4: Generate cuotas for the program

### Step 4: Re-import
1. Re-run the import for the fixed records
2. Verify all PASOs complete successfully
3. Check that Kardex and Reconciliation records are created

## Performance Tips

### Cache Usage
The system caches lookups to improve performance:
```
📋 Usando cache para carnet
```

If you see many cache hits, the import is running efficiently.

### Batch Processing
Payments are grouped by student (carnet) for efficient processing:
```
📊 Pagos agrupados por carnet
{
    "total_carnets": 50,
    "carnets_muestra": ["ASM20221234", "ASM20221235", ...]
}
```

## Example: Successful Import Flow

```
🚀 INICIANDO PROCESAMIENTO
  ├─ 👤 PROCESANDO ESTUDIANTE ASM20221234
  │   ├─ 🔍 PASO 1: Buscando prospecto por carnet
  │   ├─ ✅ PASO 1 EXITOSO: Prospecto encontrado
  │   ├─ 🔍 PASO 2: Buscando programas del estudiante
  │   ├─ ✅ PASO 2 EXITOSO: Programas encontrados
  │   ├─ 🔍 PASO 3: Obteniendo detalles de programas activos
  │   ├─ ✅ PASO 3 EXITOSO: Programas activos obtenidos
  │   ├─ 🔍 PASO 4: Buscando cuotas del programa
  │   ├─ ✅ PASO 4 EXITOSO: Cuotas encontradas
  │   ├─ 🔍 Buscando cuota para asignar al pago
  │   ├─ ✅ Cuota asignada al pago
  │   ├─ 🔍 Creando registro en kardex_pagos
  │   ├─ ✅ Kardex creado exitosamente
  │   ├─ 🔄 PASO 5: Actualizando estado de cuota
  │   ├─ ✅ PASO 5 EXITOSO: Cuota marcada como pagada
  │   ├─ 🔍 PASO 6: Creando registro de conciliación
  │   └─ ✅ PASO 6 EXITOSO: Conciliación creada
  │
  └─ 👤 PROCESANDO ESTUDIANTE ASM20221235
      └─ ... (same flow)

✅ PROCESAMIENTO COMPLETADO
```

## Additional Resources

- **DATA_FLOW_DIAGRAM.md**: Visual representation of the data flow
- **IMPLEMENTATION_SUMMARY.md**: Technical details of the implementation
- **Tests**: See `tests/Unit/PaymentHistoryImportTest.php` for example usage
