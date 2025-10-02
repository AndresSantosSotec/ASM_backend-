# Payment History Import - Quota Matching Fix

## Problem Description

The payment history import was failing to match payments with their corresponding quotas (cuotas) for students, particularly when:
1. Students were enrolled in programs that are now marked as inactive (`tb_programas.activo = false`)
2. The quota amounts didn't match exactly with payment amounts within a very strict tolerance

### Example Case
- **Carnet**: ASM2020158
- **Prospecto ID**: 42
- **Estudiante Programa ID**: 46 (prospecto_id = 42, programa_id = 5)
- **Cuotas**: IDs 68317-68320 (estudiante_programa_id = 46, estado = 'pendiente')

**Expected Flow**:
```
carnet (ASM2020158) 
  → prospectos.id (42) 
  → estudiante_programa.prospecto_id (42)
  → estudiante_programa.id (46)
  → cuotas_programa_estudiante.estudiante_programa_id (46)
  → Match and mark quotas as 'pagado'
```

## Root Causes

### 1. Filtering Out Inactive Programs
**Location**: `app/Imports/PaymentHistoryImport.php`, method `obtenerProgramasEstudiante()`, line ~1050

**Problem**: The query filtered programs with `->where('prog.activo', '=', true)`, which excluded:
- Programs that are currently inactive but have valid historical quotas
- Historical payments made when programs were active
- Students who were properly enrolled but whose programs were later deactivated

**Impact**: Students with inactive programs would not have their programs found, causing the import to fail with "ESTUDIANTE_NO_ENCONTRADO" or "PROGRAMA_NO_IDENTIFICADO" errors.

### 2. Strict Quota Matching Tolerance
**Location**: `app/Imports/PaymentHistoryImport.php`, method `buscarCuotaFlexible()`, lines ~625-656

**Problem**: Matching tolerances were too strict:
- Priority 1 (mensualidad_aprobada): Only Q100 tolerance
- Priority 2 (monto_pago): Only Q500 fixed tolerance

**Impact**: Quotas with amounts that differed by more than the tolerance wouldn't be matched, even if they were logically the correct quotas to pay.

## Solution Implemented

### 1. Remove prog.activo Filter for Historical Imports

**Change**: Commented out the `->where('prog.activo', '=', true)` filter

```php
// BEFORE: Only active programs
->where('prog.activo', '=', true)

// AFTER: All programs (active and inactive)
// ✅ NO filtrar por activo en importación histórica
// ->where('prog.activo', '=', true)
```

**Rationale**: 
- Historical payments belong to programs that may now be inactive
- Payments were valid when made, regardless of current program status
- Quotas still exist and need to be marked as paid

**Enhanced Logging**: 
- Now tracks and logs both active and inactive program counts
- Clearer error messages distinguish between "no programs found" vs "programs exist but all inactive"

### 2. Increase Quota Matching Tolerance

**Changes to Priority 1 (mensualidad_aprobada matching)**:
```php
// BEFORE: Fixed Q100 tolerance
$diferencia = abs($cuota->monto - $mensualidadAprobada);
return $diferencia <= 100;

// AFTER: Dynamic 15% or minimum Q200 tolerance
$tolerancia = max(200, $mensualidadAprobada * 0.15);
$diferencia = abs($cuota->monto - $mensualidadAprobada);
return $diferencia <= $tolerancia;
```

**Changes to Priority 2 (monto_pago matching)**:
```php
// BEFORE: Fixed Q500 tolerance
$diferencia = abs($cuota->monto - $montoPago);
return $diferencia <= 500;

// AFTER: Dynamic 20% or minimum Q500 tolerance
$tolerancia = max(500, $montoPago * 0.20);
$diferencia = abs($cuota->monto - $montoPago);
return $diferencia <= $tolerancia;
```

**Rationale**:
- Percentage-based tolerance handles different payment scales better
- Q200 minimum for Priority 1 catches small amount variations
- Q500 minimum for Priority 2 maintains the original minimum tolerance
- 15-20% allows for minor accounting adjustments, rounding, or promotional discounts

**Enhanced Debug Logging**:
- Logs when quotas aren't found by each matching strategy
- Shows available quota amounts to help troubleshoot matching issues
- Displays the tolerance used in successful matches

## Expected Outcomes

### ✅ Fixed Issues
1. **Students with inactive programs**: Now found and processed correctly
2. **Historical payments**: Can be imported regardless of current program status
3. **Better quota matching**: More flexible tolerances handle amount variations
4. **Improved debugging**: Enhanced logging helps identify why quotas aren't matched

### 📊 Import Success Rates
- **Before**: Only succeeded for students with active programs and exact amount matches
- **After**: Succeeds for all students with valid quotas, regardless of program status

### 🔍 Logging Improvements
The enhanced logging now shows:
- Total programs found (active + inactive)
- Count of active vs inactive programs
- Why quotas weren't matched (amount tolerance, no pending quotas, etc.)
- Tolerance values used in successful matches
- Available quota amounts when matching fails

## Testing

### Unit Tests
All existing unit tests pass:
```bash
php artisan test tests/Unit/PaymentHistoryImportTest.php
```
- ✅ 7 tests, 20 assertions, all passing
- Tests cover: carnet normalization, amount normalization, date parsing, receipt normalization, column validation

### Manual Testing Recommended
Test with the example case:
1. Import payments for carnet ASM2020158
2. Verify programa_id 5 is included even if inactive
3. Confirm quotas 68317-68320 are matched and marked as 'pagado'
4. Check that kardex entries are created with correct cuota_id links

## Backward Compatibility

### ✅ Safe Changes
- No database schema changes
- No breaking API changes
- Only affects historical payment imports
- Existing functionality preserved

### ⚠️ Behavior Changes
- **More permissive**: Now includes inactive programs in imports
- **More flexible**: Wider tolerance for amount matching
- **More verbose**: Additional logging for troubleshooting

## Related Files

### Modified
- `app/Imports/PaymentHistoryImport.php` - Main fix implementation

### Related (No Changes)
- `app/Models/CuotaProgramaEstudiante.php` - Quota model
- `app/Models/KardexPago.php` - Payment record model
- `database/migrations/2025_06_25_160510_create_cuotas_programa_estudiante_table.php` - Quota table structure

## Deployment Notes

1. **No migration required** - Only code changes
2. **No cache clearing required** - No cached queries affected
3. **Re-import safe** - Duplicate detection still works
4. **Rollback safe** - Can revert changes without data loss

## Monitoring

After deployment, monitor:
- Import success rates (should increase)
- Quota matching rates (should increase)
- Log files for "PASO 3 EXITOSO" messages showing inactive program counts
- Warnings for quotas not found (should decrease)

## Future Improvements

Consider:
1. Add configuration option to toggle inactive program inclusion
2. Add UI indicator for payments linked to inactive programs
3. Create report showing inactive programs with recent payments
4. Add audit trail for quota matching decisions
