# Cuota 0 (Inscripción) - Test Cases and Validation Checklist

## Pre-Implementation Checklist

Before testing the Cuota 0 feature, ensure:

- [ ] Database has `tb_precios_programa` table
- [ ] Database has `estudiante_programa` table with `inscripcion` column
- [ ] Database has `cuotas_programa_estudiante` table
- [ ] Laravel application is running without errors
- [ ] Logs directory is writable: `storage/logs/`

## Test Case 1: Basic Inscription with Price Program

### Setup

```sql
-- Create or update a program price with inscription
INSERT INTO tb_precios_programa (programa_id, inscripcion, cuota_mensual, meses)
VALUES (1, 500.00, 800.00, 12)
ON DUPLICATE KEY UPDATE inscripcion = 500.00, cuota_mensual = 800.00, meses = 12;

-- Verify
SELECT * FROM tb_precios_programa WHERE programa_id = 1;
```

### Excel Data (test_inscripcion_basic.xlsx)

```csv
carnet,nombre_estudiante,numero_boleta,monto,fecha_pago,mensualidad_aprobada,concepto,plan_estudios
TEST001,Test Student 1,BOL001,500.00,2024-01-15,800.00,Inscripción,PROG1
TEST001,Test Student 1,BOL002,800.00,2024-02-15,800.00,Cuota Febrero,PROG1
TEST001,Test Student 1,BOL003,800.00,2024-03-15,800.00,Cuota Marzo,PROG1
```

### Import Code

```php
use App\Imports\PaymentHistoryImport;
use Maatwebsite\Excel\Facades\Excel;

$import = new PaymentHistoryImport(
    uploaderId: 1,
    tipoArchivo: 'cardex_directo',
    modoReemplazo: true  // Important: Use replace mode
);

Excel::import($import, storage_path('app/test_inscripcion_basic.xlsx'));
```

### Expected Results

**1. Cuotas Created:**
```sql
SELECT numero_cuota, monto, estado, fecha_vencimiento
FROM cuotas_programa_estudiante cpe
JOIN estudiante_programa ep ON cpe.estudiante_programa_id = ep.id
JOIN prospectos p ON ep.prospecto_id = p.id
WHERE p.carnet = 'TEST001'
ORDER BY numero_cuota;

-- Expected:
-- numero_cuota | monto  | estado  | fecha_vencimiento
-- 0            | 500.00 | pagado  | 2024-01-15
-- 1            | 800.00 | pagado  | 2024-01-15
-- 2            | 800.00 | pagado  | 2024-02-15
-- 3            | 800.00 | pendiente| 2024-03-15
-- ...
```

**2. Kardex Records:**
```sql
SELECT k.id, k.numero_boleta, k.monto_pagado, c.numero_cuota
FROM kardex_pagos k
JOIN cuotas_programa_estudiante c ON k.cuota_id = c.id
JOIN estudiante_programa ep ON k.estudiante_programa_id = ep.id
JOIN prospectos p ON ep.prospecto_id = p.id
WHERE p.carnet = 'TEST001'
ORDER BY k.fecha_pago;

-- Expected:
-- numero_boleta | monto_pagado | numero_cuota
-- BOL001        | 500.00       | 0
-- BOL002        | 800.00       | 1
-- BOL003        | 800.00       | 2
```

**3. Logs to Check:**
```bash
grep "TEST001" storage/logs/laravel.log | grep -i inscripcion
```

Should contain:
- `🔧 [Replace] Rebuild cuotas` with `"inscripcion": 500.00`
- `✅ Cuota 0 (inscripción) detectada como match`
- `✅ [Replace] Malla reconstruida (incluye cuota 0 si aplica)`

### Validation Checklist

- [ ] Cuota 0 was created with `numero_cuota = 0`
- [ ] Cuota 0 has correct amount (500.00)
- [ ] Payment BOL001 was assigned to Cuota 0
- [ ] Cuota 0 is marked as `pagado`
- [ ] Regular cuotas (1, 2, 3...) were created
- [ ] Payments BOL002 and BOL003 assigned to cuotas 1 and 2
- [ ] Logs show "inscripcion" detected
- [ ] No errors in logs

---

## Test Case 2: Inscription Detection by Concept

### Setup

Same as Test Case 1.

### Excel Data (test_inscripcion_concepto.xlsx)

```csv
carnet,nombre_estudiante,numero_boleta,monto,fecha_pago,mensualidad_aprobada,concepto,plan_estudios
TEST002,Test Student 2,BOL010,500.00,2024-01-15,800.00,Inscripcion Inicial,PROG1
TEST002,Test Student 2,BOL011,800.00,2024-02-15,800.00,Mensualidad,PROG1
```

### Expected Results

**Detection Log:**
```
✅ Cuota 0 (inscripción) detectada como match
{
    "cuota_id": X,
    "monto_cuota": 500.00,
    "monto_pago": 500.00,
    "por_concepto": true,  // ← Important!
    "por_monto": true,
    "tolerancia": 150.00
}
```

### Validation Checklist

- [ ] Payment detected as inscription by concept
- [ ] Log shows `"por_concepto": true`
- [ ] Payment assigned to Cuota 0 correctly
- [ ] No warnings or errors

---

## Test Case 3: Inscription Detection by Amount (No Explicit Concept)

### Setup

Same as Test Case 1.

### Excel Data (test_inscripcion_monto.xlsx)

```csv
carnet,nombre_estudiante,numero_boleta,monto,fecha_pago,mensualidad_aprobada,concepto,plan_estudios
TEST003,Test Student 3,BOL020,500.00,2024-01-15,800.00,Pago Inicial,PROG1
TEST003,Test Student 3,BOL021,800.00,2024-02-15,800.00,Cuota 1,PROG1
```

Note: "Pago Inicial" doesn't contain "inscrip"

### Expected Results

**Detection Log:**
```
✅ Cuota 0 (inscripción) detectada como match
{
    "cuota_id": X,
    "monto_cuota": 500.00,
    "monto_pago": 500.00,
    "por_concepto": false,  // ← No concept match
    "por_monto": true,      // ← Detected by amount!
    "tolerancia": 150.00
}
```

### Validation Checklist

- [ ] Payment detected as inscription by amount only
- [ ] Log shows `"por_concepto": false, "por_monto": true`
- [ ] Tolerance calculation is correct (max(100, 500*0.30) = 150)
- [ ] Payment assigned to Cuota 0

---

## Test Case 4: Partial Inscription Payment

### Setup

Same as Test Case 1.

### Excel Data (test_inscripcion_parcial.xlsx)

```csv
carnet,nombre_estudiante,numero_boleta,monto,fecha_pago,mensualidad_aprobada,concepto,plan_estudios
TEST004,Test Student 4,BOL030,300.00,2024-01-15,800.00,Inscripción,PROG1
TEST004,Test Student 4,BOL031,800.00,2024-02-15,800.00,Cuota 1,PROG1
```

Note: Only 300 of 500 inscription paid (60%)

### Expected Results

**Warning:**
```
⚠️ PAGO_PARCIAL_INSCRIPCION
{
    "tipo": "PAGO_PARCIAL_INSCRIPCION",
    "fila": X,
    "advertencia": "Pago parcial de inscripción: Q300.00 de Q500.00 (60.0%)",
    "cuota_id": X
}
```

**Cuota Status:**
```sql
SELECT estado, monto FROM cuotas_programa_estudiante WHERE numero_cuota = 0 AND ...;
-- estado: pagado (even though partial)
-- monto: 500.00
```

### Validation Checklist

- [ ] Warning generated for partial payment
- [ ] Warning shows correct percentage (60%)
- [ ] Cuota 0 is marked as `pagado`
- [ ] `$this->pagosParciales` counter incremented
- [ ] `$this->totalDiscrepancias` includes 200.00
- [ ] Payment still assigned to Cuota 0

---

## Test Case 5: No Inscription in Program

### Setup

```sql
-- Set inscription to 0
UPDATE tb_precios_programa SET inscripcion = 0 WHERE programa_id = 1;
```

### Excel Data (test_sin_inscripcion.xlsx)

```csv
carnet,nombre_estudiante,numero_boleta,monto,fecha_pago,mensualidad_aprobada,concepto,plan_estudios
TEST005,Test Student 5,BOL040,800.00,2024-01-15,800.00,Cuota 1,PROG1
TEST005,Test Student 5,BOL041,800.00,2024-02-15,800.00,Cuota 2,PROG1
```

### Expected Results

**Cuotas Created:**
```sql
SELECT numero_cuota, monto FROM cuotas_programa_estudiante WHERE ...
ORDER BY numero_cuota;

-- Expected:
-- numero_cuota | monto
-- 1            | 800.00
-- 2            | 800.00
-- 3            | 800.00
-- ... (NO cuota 0)
```

**Logs:**
```
🔧 [Replace] Rebuild cuotas
{
    "inscripcion": null  // ← No inscription detected
}
```

### Validation Checklist

- [ ] NO Cuota 0 created
- [ ] Only regular cuotas (1, 2, 3...) exist
- [ ] Payments assigned to cuotas 1 and 2
- [ ] No inscription-related logs or warnings
- [ ] System behaves like before (backward compatible)

---

## Test Case 6: Inscription from Excel Inference

### Setup

```sql
-- Set inscription to 0 in price_programa
UPDATE tb_precios_programa SET inscripcion = 0 WHERE programa_id = 1;
```

### Excel Data (test_inscripcion_inferida.xlsx)

```csv
carnet,nombre_estudiante,numero_boleta,monto,fecha_pago,mensualidad_aprobada,concepto,plan_estudios
TEST006,Test Student 6,BOL050,500.00,2024-01-15,800.00,Inscripción,PROG1
TEST006,Test Student 6,BOL051,500.00,2024-01-20,800.00,Inscripción,PROG1
TEST006,Test Student 6,BOL052,800.00,2024-02-15,800.00,Cuota 1,PROG1
```

Note: Multiple payments with "Inscripción" concept → should infer 500.00

### Expected Results

**Inference Log:**
```
🔧 [Replace] Rebuild cuotas
{
    "inscripcion": 500.00  // ← Inferred from Excel!
}
```

**Cuotas:**
```sql
SELECT numero_cuota, monto FROM cuotas_programa_estudiante WHERE ...;

-- Expected:
-- numero_cuota | monto
-- 0            | 500.00  ← Created from inference!
-- 1            | 800.00
-- 2            | 800.00
```

### Validation Checklist

- [ ] `inferInscripcion()` detected 500.00 from Excel
- [ ] Cuota 0 created even without precio_programa.inscripcion
- [ ] Both BOL050 and BOL051 assigned to Cuota 0
- [ ] Moda (most frequent value) logic works correctly

---

## Test Case 7: TEMP Program to Real with Inscription

### Setup

```sql
-- Create TEMP program if doesn't exist
INSERT INTO programas (abreviatura, nombre_del_programa) 
VALUES ('TEMP', 'Programa Pendiente');

-- Set inscription for real program
UPDATE tb_precios_programa SET inscripcion = 500.00 WHERE programa_id = 1;
```

### Excel Data (test_temp_to_real.xlsx)

```csv
carnet,nombre_estudiante,numero_boleta,monto,fecha_pago,mensualidad_aprobada,concepto,plan_estudios
TEST007,Test Student 7,BOL060,500.00,2024-01-15,800.00,Inscripción,PROG1
TEST007,Test Student 7,BOL061,800.00,2024-02-15,800.00,Cuota 1,PROG1
```

Note: Student initially has TEMP program, Excel has plan_estudios = PROG1

### Expected Results

**Program Update:**
- Student starts with TEMP program
- System detects `plan_estudios` in Excel
- Updates to PROG1
- Purge + Rebuild with PROG1 data
- Creates Cuota 0 from PROG1's inscription

**Logs:**
```
Actualizando programa de TEMP a real...
🧹 [Replace] PURGE EP ...
🔧 [Replace] Rebuild cuotas
{
    "inscripcion": 500.00  // ← From PROG1
}
```

### Validation Checklist

- [ ] Student's program updated from TEMP to real
- [ ] Cuota 0 created with real program's inscription
- [ ] Payments assigned correctly after update
- [ ] No duplicate cuotas or errors

---

## Test Case 8: Multiple Students, Mixed Scenarios

### Excel Data (test_multiple_students.xlsx)

```csv
carnet,nombre_estudiante,numero_boleta,monto,fecha_pago,mensualidad_aprobada,concepto,plan_estudios
TEST008,Student With Inscription,BOL070,500.00,2024-01-15,800.00,Inscripción,PROG1
TEST008,Student With Inscription,BOL071,800.00,2024-02-15,800.00,Cuota 1,PROG1
TEST009,Student No Inscription,BOL080,800.00,2024-01-15,800.00,Cuota 1,PROG2
TEST009,Student No Inscription,BOL081,800.00,2024-02-15,800.00,Cuota 2,PROG2
TEST010,Student Partial Inscription,BOL090,300.00,2024-01-15,800.00,Inscripción,PROG1
```

Setup: PROG1 has inscription 500, PROG2 has inscription 0

### Validation Checklist

- [ ] TEST008: Cuota 0 created and assigned
- [ ] TEST009: NO Cuota 0 created
- [ ] TEST010: Cuota 0 with partial payment warning
- [ ] All three students processed independently
- [ ] No cross-contamination between students

---

## Performance Tests

### Large Import (100+ students)

Create Excel with 100 students, each with inscription + 3 payments

### Validation Checklist

- [ ] Import completes without timeout
- [ ] Memory usage stays under 2GB
- [ ] All Cuota 0 created correctly
- [ ] No missing payments
- [ ] Logs don't show performance warnings

---

## Regression Tests (Ensure Nothing Broke)

### Test: Normal Import Without Replace Mode

```php
$import = new PaymentHistoryImport(
    uploaderId: 1,
    tipoArchivo: 'cardex_directo',
    modoReemplazo: false  // Normal mode
);
```

### Validation Checklist

- [ ] Existing cuotas NOT deleted
- [ ] New payments assigned to existing cuotas
- [ ] No rebuild triggered
- [ ] If Cuota 0 exists manually, it can still be assigned

### Test: Import Without Inscription Column

Excel without "concepto" column

### Validation Checklist

- [ ] No errors thrown
- [ ] Payments still processed
- [ ] Inscription detected by amount only (if applicable)
- [ ] System degrades gracefully

---

## Manual Verification Steps

After running test cases, manually verify in database:

```sql
-- 1. Check all Cuota 0 entries
SELECT 
    p.carnet,
    pr.nombre_del_programa,
    c.numero_cuota,
    c.monto,
    c.estado,
    c.fecha_vencimiento
FROM cuotas_programa_estudiante c
JOIN estudiante_programa ep ON c.estudiante_programa_id = ep.id
JOIN prospectos p ON ep.prospecto_id = p.id
JOIN programas pr ON ep.programa_id = pr.id
WHERE c.numero_cuota = 0
ORDER BY p.carnet;

-- 2. Verify payments assigned to Cuota 0
SELECT 
    p.carnet,
    k.numero_boleta,
    k.monto_pagado,
    k.observaciones,
    c.numero_cuota,
    c.monto as monto_cuota
FROM kardex_pagos k
JOIN estudiante_programa ep ON k.estudiante_programa_id = ep.id
JOIN prospectos p ON ep.prospecto_id = p.id
JOIN cuotas_programa_estudiante c ON k.cuota_id = c.id
WHERE c.numero_cuota = 0
ORDER BY p.carnet;

-- 3. Check for orphaned payments (cuota_id NULL)
SELECT COUNT(*) as orphaned_count
FROM kardex_pagos
WHERE cuota_id IS NULL;

-- 4. Verify no duplicate Cuota 0 for same student
SELECT 
    estudiante_programa_id,
    COUNT(*) as cuota_0_count
FROM cuotas_programa_estudiante
WHERE numero_cuota = 0
GROUP BY estudiante_programa_id
HAVING COUNT(*) > 1;
```

---

## Sign-off Checklist

Before considering the feature complete:

- [ ] All 8 test cases executed successfully
- [ ] Performance tests passed
- [ ] Regression tests show no breaking changes
- [ ] Manual database verification completed
- [ ] Logs reviewed for warnings/errors
- [ ] Documentation reviewed and accurate
- [ ] Code reviewed for best practices
- [ ] No PHP syntax errors (`php -l`)
- [ ] Compatible with existing flows

---

## Rollback Plan (If Needed)

If critical issues are found:

```bash
# Revert commits
git revert bef05e7 19e57c9 4d659a9

# Or restore from backup files
git checkout HEAD~3 -- app/Imports/PaymentReplaceService.php
git checkout HEAD~3 -- app/Imports/PaymentHistoryImport.php
```

---

**Test Results Summary**

| Test Case | Status | Notes |
|-----------|--------|-------|
| 1. Basic Inscription | ⬜ | |
| 2. Detection by Concept | ⬜ | |
| 3. Detection by Amount | ⬜ | |
| 4. Partial Payment | ⬜ | |
| 5. No Inscription | ⬜ | |
| 6. Excel Inference | ⬜ | |
| 7. TEMP to Real | ⬜ | |
| 8. Multiple Students | ⬜ | |
| Performance | ⬜ | |
| Regression | ⬜ | |

✅ = Passed | ❌ = Failed | ⬜ = Not Tested
