# Payment History Import - Tolerance and Error Handling Improvements

## Overview

This document describes the comprehensive improvements made to the payment history import system to handle inconsistencies in historical data and prevent transaction failures.

## Problems Solved

### 1. Transaction Abort Errors (SQLSTATE[25P02])
**Problem**: PostgreSQL was aborting entire transactions when a single query failed, blocking all subsequent operations.

**Solution**: 
- Changed carnet lookup from `DB::raw()` to `whereRaw()` with bound parameters
- Added comprehensive try-catch blocks that don't re-throw exceptions
- Individual record failures no longer abort the entire import process

### 2. Student Not Found Errors
**Problem**: When a carnet in the Excel file didn't exist in the `prospectos` table, the entire import failed.

**Solution**:
- Log error type `ESTUDIANTE_NO_ENCONTRADO` with detailed information
- Continue processing remaining records instead of aborting
- Cache empty results to avoid repeated failed database lookups
- Provide actionable recommendations in error logs

### 3. Missing Quotas
**Problem**: Students with valid programs but no quotas in `cuotas_programa_estudiante` couldn't have payments recorded.

**Solution**:
- New method `generarCuotasAutomaticas()` creates quotas from `precio_programa`
- When no quotas exist:
  1. System looks up `precio_programa` for the program
  2. Auto-generates monthly quotas based on program duration and pricing
  3. Clears cache and retries quota assignment
  4. If generation fails, creates kardex without quota (with warning)
- Prevents data loss while flagging incomplete records for review

### 4. Duplicate Key Violations
**Problem**: Attempting to insert duplicate records in `kardex_pagos` or `reconciliation_record` caused transaction failures.

**Solution**:
- **Kardex Duplicates**: 
  - Check `numero_boleta` + `estudiante_programa_id` before insert
  - Log warning type `DUPLICADO` and skip record (no exception)
  - Record original kardex_id for reference
  
- **Reconciliation Duplicates**:
  - Check `fingerprint` before insert
  - Log warning type `CONCILIACION_DUPLICADA` and skip
  - Added catch block for database-level duplicate errors
  - Both checks and exception handling prevent transaction abort

## Technical Implementation Details

### Carnet Normalization

**Before**:
```php
->where(DB::raw("REPLACE(UPPER(carnet), ' ', '')"), '=', $carnet)
```

**After**:
```php
$carnetNormalizado = strtoupper(str_replace(' ', '', $carnet));
->whereRaw("REPLACE(UPPER(carnet), ' ', '') = ?", [$carnetNormalizado])
```

**Why**: Using bound parameters prevents SQL injection and reduces transaction errors in PostgreSQL.

### Automatic Quota Generation

```php
private function generarCuotasAutomaticas(int $estudianteProgramaId, $precioPrograma)
{
    // 1. Get estudiante_programa details
    $estudiantePrograma = DB::table('estudiante_programa')
        ->where('id', $estudianteProgramaId)
        ->first();
    
    // 2. Generate monthly quotas
    for ($i = 1; $i <= $precioPrograma->meses; $i++) {
        CuotaProgramaEstudiante::create([
            'estudiante_programa_id' => $estudianteProgramaId,
            'numero_cuota' => $i,
            'fecha_vencimiento' => $fechaInicio->copy()->addMonths($i - 1)->day(5),
            'monto' => $precioPrograma->cuota_mensual,
            'estado' => 'pendiente',
        ]);
    }
    
    // 3. Clear cache to force reload
    unset($this->cuotasPorEstudianteCache[$estudianteProgramaId]);
}
```

**Trigger**: Called automatically in `buscarCuotaFlexible()` when no quotas are found for a program.

### Duplicate Detection

**Kardex Check**:
```php
$kardexExistente = KardexPago::where('numero_boleta', $boleta)
    ->where('estudiante_programa_id', $programaAsignado->estudiante_programa_id)
    ->first();

if ($kardexExistente) {
    $this->advertencias[] = [
        'tipo' => 'DUPLICADO',
        'fila' => $numeroFila,
        'kardex_id' => $kardexExistente->id,
        'accion' => 'omitido'
    ];
    return; // Skip without exception
}
```

**Reconciliation Check**:
```php
if (ReconciliationRecord::where('fingerprint', $fingerprint)->exists()) {
    $this->advertencias[] = [
        'tipo' => 'CONCILIACION_DUPLICADA',
        'kardex_id' => $kardex->id,
        'accion' => 'omitido'
    ];
    return; // Skip without exception
}

try {
    ReconciliationRecord::create([...]);
} catch (\Throwable $e) {
    if (str_contains($e->getMessage(), 'duplicate')) {
        // Log as warning, don't throw
        $this->advertencias[] = [...];
    }
}
```

### Transaction Error Handling

**Before**:
```php
try {
    DB::transaction(function () { ... });
} catch (\Throwable $ex) {
    Log::error(...);
    throw $ex; // ❌ Aborts entire process
}
```

**After**:
```php
try {
    DB::transaction(function () { ... });
} catch (\Throwable $ex) {
    Log::error(...);
    $this->errores[] = [...]; // ✅ Log error
    // Don't re-throw - continue processing
}
```

## Error and Warning Types

### Errors (Block individual record, don't abort process)
- `ESTUDIANTE_NO_ENCONTRADO`: Carnet not found in prospectos
- `PROGRAMA_NO_IDENTIFICADO`: Cannot determine correct program for payment
- `DATOS_INCOMPLETOS`: Missing required fields (boleta, monto, fecha)
- `ERROR_PROCESAMIENTO_PAGO`: Unexpected error during payment processing

### Warnings (Record processed, flagged for review)
- `SIN_CUOTA`: No quota assigned (kardex created without cuota_id)
- `DUPLICADO`: Payment already registered in kardex_pagos
- `CONCILIACION_DUPLICADA`: Reconciliation record already exists
- `PAGO_PARCIAL`: Payment covers <100% of quota amount
- `DIFERENCIA_MONTO_EXTREMA`: Large difference between payment and quota
- `CUOTA_FORZADA`: Quota assigned as last resort without amount validation

## Processing Flow

```
1. Load Excel rows
2. Group by carnet
3. For each student:
   a. Normalize carnet
   b. Look up prospecto
      - If not found → ERROR, continue to next student
   c. Get student programs
      - If none → ERROR, continue to next student
   d. For each payment:
      i.   Validate data (boleta, monto, fecha)
      ii.  Check for duplicate kardex → WARNING if exists, skip
      iii. Get/generate quotas
           - Try to find existing quotas
           - If none, try precio_programa
           - Generate quotas automatically if possible
      iv.  Assign quota flexibly (±50% tolerance)
      v.   Create kardex (with or without quota)
      vi.  Update quota status if assigned
      vii. Create reconciliation
           - Check fingerprint first
           - Skip if duplicate (WARNING)
4. Generate comprehensive report
   - Successful payments
   - Errors by type
   - Warnings by type
```

## Expected Outcomes

### Before Fix
- ❌ Transaction aborted on first error
- ❌ No payments processed after error
- ❌ Missing quotas blocked all payments
- ❌ Duplicate attempts crashed import
- ❌ No actionable error information

### After Fix
- ✅ All processable payments recorded
- ✅ Individual errors don't block others
- ✅ Automatic quota generation from program pricing
- ✅ Duplicates logged as warnings, not errors
- ✅ Detailed error reports with recommendations
- ✅ No transaction abort errors (SQLSTATE[25P02])
- ✅ Historical payments always have a record (even if incomplete)

## Usage Example

### Successful Import with Warnings
```
📊 RESUMEN FINAL DE IMPORTACIÓN
✅ EXITOSOS
  - filas_procesadas: 150
  - kardex_creados: 150
  - cuotas_actualizadas: 145
  - conciliaciones_creadas: 145
  - monto_total: Q 450,000.00

⚠️ ADVERTENCIAS
  - sin_cuota: 5 (cuotas generadas automáticamente)
  - duplicados: 0
  - pagos_parciales: 3
  - diferencias_monto: 2

❌ ERRORES
  - estudiantes_no_encontrados: 2
  - programas_no_identificados: 1
  - datos_incompletos: 0
```

### Quota Auto-Generation Log
```
⚠️ No hay cuotas pendientes
💰 Precio de programa encontrado para validación
🔧 Generando cuotas automáticamente desde precio_programa
✅ Cuotas generadas automáticamente
✅ Cuotas disponibles después de generación automática (total: 12)
```

### Duplicate Detection Log
```
⚠️ Kardex duplicado detectado
  - kardex_id: 1234
  - boleta: BOL-2024-001
  - estudiante_programa_id: 56
  - accion: omitido
```

## Testing Recommendations

1. **Test with missing students**: Verify error logged, processing continues
2. **Test with missing quotas**: Verify auto-generation works
3. **Test with duplicate payments**: Verify warnings, no crashes
4. **Test with various data quality issues**: Ensure tolerance
5. **Check transaction logs**: No SQLSTATE[25P02] errors
6. **Verify data integrity**: All valid payments recorded

## Configuration

No configuration changes needed. The system is designed to be tolerant by default.

To adjust tolerance levels, modify the tolerance calculations in `buscarCuotaFlexible()`:
- Standard tolerance: `max(100, $monto * 0.50)` (50% or Q100 minimum)
- Partial payment threshold: `30%` of quota amount
- Extreme tolerance: `100%` of larger amount

## Migration Notes

- No database schema changes required
- Existing data remains unchanged
- New quotas generated only when missing
- Duplicates detected using existing unique constraints
- Compatible with all existing import files

## Support

For issues or questions:
1. Check Laravel logs for detailed error messages
2. Review `PAYMENT_HISTORY_IMPORT_LOGGING_GUIDE.md` for log interpretation
3. Examine warning types to understand data quality issues
4. Use error recommendations to fix source data
