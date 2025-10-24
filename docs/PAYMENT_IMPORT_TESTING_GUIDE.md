# Manual Testing Guide - Payment Import Tolerance Fixes

## Overview
This guide provides step-by-step instructions to manually test the payment import tolerance improvements.

## Prerequisites
- Access to Laravel application
- Test Excel file with payment history
- Database with test data

## Test Scenarios

### Scenario 1: Missing Student (ESTUDIANTE_NO_ENCONTRADO)

**Setup**:
1. Create Excel file with a carnet that doesn't exist in `prospectos` table
2. Include other valid records in the same file

**Expected Result**:
- ✅ Error logged: `ESTUDIANTE_NO_ENCONTRADO`
- ✅ Other valid records processed successfully
- ✅ No transaction abort error (SQLSTATE[25P02])

**How to Verify**:
```bash
# Check Laravel logs
tail -f storage/logs/laravel.log | grep "ESTUDIANTE_NO_ENCONTRADO"

# Expected output:
# ❌ PASO 1 FALLIDO: Prospecto no encontrado
# tipo: ESTUDIANTE_NO_ENCONTRADO
# solucion: Verifica que el carnet esté registrado en el sistema
```

**SQL to Create Test Data**:
```sql
-- Check which carnets don't exist
SELECT 'TEST-999' as carnet
WHERE NOT EXISTS (
    SELECT 1 FROM prospectos WHERE carnet = 'TEST-999'
);
```

---

### Scenario 2: Missing Quotas - Auto Generation

**Setup**:
1. Create student with program in `estudiante_programa`
2. Ensure program has price in `precio_programa`
3. Ensure NO quotas exist in `cuotas_programa_estudiante` for this student
4. Import payment for this student

**Expected Result**:
- ✅ System finds precio_programa
- ✅ Quotas auto-generated based on program duration
- ✅ Payment assigned to generated quota
- ✅ Kardex created successfully

**How to Verify**:
```bash
# Check logs for auto-generation
tail -f storage/logs/laravel.log | grep "generando cuotas automáticamente"

# Expected output:
# 🔧 Generando cuotas automáticamente desde precio_programa
# ✅ Cuotas generadas automáticamente (total: 12)
# ✅ Cuotas disponibles después de generación automática
```

**SQL to Create Test Data**:
```sql
-- Find student with program but no quotas
SELECT ep.id as estudiante_programa_id, ep.programa_id
FROM estudiante_programa ep
LEFT JOIN cuotas_programa_estudiante cpe ON ep.id = cpe.estudiante_programa_id
WHERE cpe.id IS NULL
AND EXISTS (
    SELECT 1 FROM precio_programa pp WHERE pp.programa_id = ep.programa_id
)
LIMIT 1;
```

---

### Scenario 3: Duplicate Payment (DUPLICADO)

**Setup**:
1. Import a payment file successfully
2. Import the same file again (or specific payment)

**Expected Result**:
- ✅ Warning logged: `DUPLICADO`
- ✅ No database error
- ✅ Original kardex_id referenced in warning
- ✅ Import continues with other records

**How to Verify**:
```bash
# Check logs for duplicate detection
tail -f storage/logs/laravel.log | grep "Kardex duplicado detectado"

# Expected output:
# ⚠️ Kardex duplicado detectado
# kardex_id: 1234
# boleta: BOL-2024-001
# accion: omitido
```

**SQL to Check**:
```sql
-- Verify no duplicate kardex records created
SELECT numero_boleta, estudiante_programa_id, COUNT(*) as count
FROM kardex_pagos
GROUP BY numero_boleta, estudiante_programa_id
HAVING COUNT(*) > 1;

-- Should return 0 rows
```

---

### Scenario 4: Duplicate Reconciliation (CONCILIACION_DUPLICADA)

**Setup**:
1. Import payment that creates reconciliation
2. Try to import same payment again

**Expected Result**:
- ✅ Warning logged: `CONCILIACION_DUPLICADA`
- ✅ No duplicate reconciliation created
- ✅ No constraint violation error

**How to Verify**:
```bash
# Check logs
tail -f storage/logs/laravel.log | grep "Conciliación duplicada detectada"

# Expected output:
# ⚠️ Conciliación duplicada detectada, omitiendo creación
# fingerprint: abc123...
# accion: omitido
```

**SQL to Check**:
```sql
-- Verify no duplicate reconciliations
SELECT fingerprint, COUNT(*) as count
FROM reconciliation_records
GROUP BY fingerprint
HAVING COUNT(*) > 1;

-- Should return 0 rows
```

---

### Scenario 5: Transaction Error Handling

**Setup**:
1. Create payment record with invalid data that will cause transaction error
2. Include valid records before and after the problematic record

**Expected Result**:
- ✅ Error logged: `ERROR_PROCESAMIENTO_PAGO`
- ✅ Valid records before error processed
- ✅ Valid records after error processed
- ✅ No transaction abort

**How to Verify**:
```bash
# Check that processing continued after error
tail -f storage/logs/laravel.log | grep -A5 "ERROR_PROCESAMIENTO_PAGO"

# Should see:
# ❌ Error en transacción fila X
# tipo: ERROR_PROCESAMIENTO_PAGO
# recomendacion: Revisar este pago manualmente
# [Then continues with next rows]
```

---

### Scenario 6: Carnet Normalization

**Setup**:
1. Create test data with carnets in different formats:
   - "ASM 2024 001"
   - "asm2024001"
   - " ASM2024001 "
2. Import payments for these carnets

**Expected Result**:
- ✅ All variations normalized to "ASM2024001"
- ✅ Successfully matched to same prospecto
- ✅ No transaction errors

**How to Verify**:
```bash
# Check normalization logs
tail -f storage/logs/laravel.log | grep "Carnet normalizado"

# Expected output:
# 🎫 Carnet normalizado
# original: "ASM 2024 001"
# normalizado: "ASM2024001"
```

---

## Complete Import Test

### Full Test Excel Structure
```
carnet,nombre_estudiante,numero_boleta,monto,fecha_pago,mensualidad_aprobada,banco,concepto
ASM2024001,Juan Perez,BOL-001,1000,2024-01-15,1000,BAC,Cuota mensual
ASM2024002,Maria Lopez,BOL-002,1200,2024-01-16,1200,BI,Cuota mensual
NOTEXIST,Ghost Student,BOL-003,1000,2024-01-17,1000,BAC,Cuota mensual
ASM2024001,Juan Perez,BOL-001,1000,2024-01-15,1000,BAC,Cuota mensual
```

### Expected Results
```
✅ Row 1: Success - Juan Perez payment recorded
✅ Row 2: Success - Maria Lopez payment recorded
❌ Row 3: Error - ESTUDIANTE_NO_ENCONTRADO (Ghost Student)
⚠️ Row 4: Warning - DUPLICADO (Juan Perez duplicate)
```

### Run Import
```php
// In tinker or controller
use App\Imports\PaymentHistoryImport;
use Maatwebsite\Excel\Facades\Excel;

$import = new PaymentHistoryImport(1);
Excel::import($import, 'test-payment-import.xlsx');

// Check results
dump($import->procesados); // Should be 2
dump($import->kardexCreados); // Should be 2
dump(count($import->errores)); // Should be 1
dump(count($import->advertencias)); // Should be 1
```

---

## Performance Test

### Large File Import
1. Create Excel with 1000+ records
2. Include mix of valid, invalid, duplicate records
3. Monitor import time and memory usage

**Expected**:
- ✅ No timeout errors
- ✅ Memory usage stable
- ✅ All records processed
- ✅ Detailed error/warning report

**Monitor**:
```bash
# Watch logs in real-time
tail -f storage/logs/laravel.log | grep "RESUMEN FINAL"

# Check memory usage
php artisan tinker
memory_get_peak_usage(true) / 1024 / 1024; // MB
```

---

## Verification Queries

### 1. Check Kardex Creation
```sql
SELECT 
    COUNT(*) as total_kardex,
    COUNT(cuota_id) as with_quota,
    COUNT(*) - COUNT(cuota_id) as without_quota,
    SUM(monto_pagado) as total_amount
FROM kardex_pagos
WHERE created_at >= NOW() - INTERVAL '1 hour';
```

### 2. Check Quota Generation
```sql
SELECT 
    ep.id,
    p.carnet,
    p.nombre_completo,
    COUNT(cpe.id) as total_quotas,
    SUM(CASE WHEN cpe.estado = 'pagado' THEN 1 ELSE 0 END) as paid_quotas
FROM estudiante_programa ep
JOIN prospectos p ON ep.prospecto_id = p.id
LEFT JOIN cuotas_programa_estudiante cpe ON ep.id = cpe.estudiante_programa_id
WHERE ep.created_at >= NOW() - INTERVAL '1 hour'
GROUP BY ep.id, p.carnet, p.nombre_completo;
```

### 3. Check for Transaction Errors in Logs
```bash
# Should return empty
grep "SQLSTATE\[25P02\]" storage/logs/laravel.log
grep "transacción abortada" storage/logs/laravel.log
```

### 4. Verify No Constraint Violations
```bash
# Should return empty
grep "duplicate key" storage/logs/laravel.log
grep "Duplicate entry" storage/logs/laravel.log
```

---

## Success Criteria

All tests pass if:
- ✅ No SQLSTATE[25P02] errors in logs
- ✅ All valid payments recorded in kardex_pagos
- ✅ Missing quotas auto-generated from precio_programa
- ✅ Duplicates logged as warnings, not errors
- ✅ Individual errors don't abort entire import
- ✅ Comprehensive error/warning reports generated
- ✅ No data loss for valid records
- ✅ Database remains consistent (no orphaned records)

---

## Rollback Test

If needed, test rollback:
```bash
# Revert changes
git revert <commit-hash>

# Re-run tests with old code
# Expected: Transaction errors, import failures

# Re-apply changes
git revert <revert-commit-hash>
```

---

## Support Commands

### View Recent Imports
```sql
SELECT 
    DATE(created_at) as import_date,
    COUNT(*) as payments_imported,
    SUM(monto_pagado) as total_amount
FROM kardex_pagos
WHERE created_at >= NOW() - INTERVAL '7 days'
GROUP BY DATE(created_at)
ORDER BY import_date DESC;
```

### Find Problem Records
```sql
-- Kardex without quotas
SELECT * FROM kardex_pagos WHERE cuota_id IS NULL;

-- Students without quotas
SELECT ep.* FROM estudiante_programa ep
LEFT JOIN cuotas_programa_estudiante cpe ON ep.id = cpe.estudiante_programa_id
WHERE cpe.id IS NULL;
```

### Clear Test Data
```sql
-- Be careful - only in test environment!
DELETE FROM kardex_pagos WHERE created_at >= NOW() - INTERVAL '1 hour';
DELETE FROM cuotas_programa_estudiante WHERE created_at >= NOW() - INTERVAL '1 hour';
DELETE FROM reconciliation_records WHERE created_at >= NOW() - INTERVAL '1 hour';
```
