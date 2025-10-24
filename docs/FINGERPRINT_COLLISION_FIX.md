# Fix: Duplicate Key Violations on kardex_pagos.boleta_fingerprint

## 📋 Problem Summary

During historical payment data import, the system was encountering duplicate key violations on the `boleta_fingerprint` unique constraint in the `kardex_pagos` table. This prevented valid payments from being imported.

### Error Example
```
SQLSTATE[23505]: Unique violation: 7 ERROR: llave duplicada viola restricción de unicidad «kardex_pagos_boleta_fingerprint_unique»
DETAIL: Ya existe la llave (boleta_fingerprint)=(0a0651910d7aa81c2baf8414663c6e5e0dc8ffef900c1f3a1cadb48c6d4da61b).
```

### Affected Scenario
- Student AMS2020130: Row 126, 19 payments succeeded, 2 failed
- Receipts: 652002 and 901002
- Bank: "No especificado"
- These receipt numbers were also used by other students (e.g., ASM2020103)

## 🔍 Root Cause Analysis

### Old Fingerprint Calculation
```php
// app/Models/KardexPago.php (before fix)
$model->boleta_fingerprint = hash('sha256', 
    $model->banco_normalizado . '|' . $model->numero_boleta_normalizada
);
```

**Problem**: This fingerprint only considers:
- `banco_normalizado` (e.g., "NO ESPECIFICADO")
- `numero_boleta_normalizada` (e.g., "652002")

**Result**: When different students use the same receipt number with the same bank, they generate identical fingerprints, causing database constraint violations.

### Collision Example
```
Student A: banco=NO ESPECIFICADO, boleta=652002
→ fingerprint = 0a0651910d7aa81c2baf8414663c6e5e0dc8ffef900c1f3a1cadb48c6d4da61b

Student B: banco=NO ESPECIFICADO, boleta=652002
→ fingerprint = 0a0651910d7aa81c2baf8414663c6e5e0dc8ffef900c1f3a1cadb48c6d4da61b
❌ COLLISION!
```

## ✅ Solution Implemented

### New Fingerprint Calculation
```php
// app/Models/KardexPago.php (after fix)
$estudiante = $model->estudiante_programa_id ?? 'UNKNOWN';
$fecha = $model->fecha_pago ? 
    (is_string($model->fecha_pago) ? $model->fecha_pago : $model->fecha_pago->format('Y-m-d')) : 
    'UNKNOWN';

$model->boleta_fingerprint = hash('sha256', 
    $model->banco_normalizado . '|' . 
    $model->numero_boleta_normalizada . '|' . 
    $estudiante . '|' . 
    $fecha
);
```

**Now includes**:
- `banco_normalizado` (bank)
- `numero_boleta_normalizada` (receipt number)
- `estudiante_programa_id` (student ID) ✨ NEW
- `fecha_pago` (payment date) ✨ NEW

### No More Collisions
```
Student A (ID=5): banco=NO ESPECIFICADO, boleta=652002, date=2020-08-01
→ fingerprint = e9f39a2090a3a3d7...

Student B (ID=162): banco=NO ESPECIFICADO, boleta=652002, date=2020-08-01
→ fingerprint = 431c127037e4ea5f...
✅ UNIQUE!
```

## 📝 Changes Made

### 1. Model Update (`app/Models/KardexPago.php`)
- Updated `booted()` method to include `estudiante_programa_id` and `fecha_pago` in fingerprint
- Handles both string and Carbon date objects
- Gracefully handles missing data with 'UNKNOWN' placeholder

### 2. Import Logic Update (`app/Imports/PaymentHistoryImport.php`)
- Added dual duplicate check:
  1. By `numero_boleta` + `estudiante_programa_id` (fast check)
  2. By `boleta_fingerprint` (comprehensive check with date)
- Both checks skip duplicates gracefully with warnings instead of throwing exceptions
- Logs detailed information about duplicate detection

```php
// Check 1: By boleta + student
$kardexExistente = KardexPago::where('numero_boleta', $boleta)
    ->where('estudiante_programa_id', $programaAsignado->estudiante_programa_id)
    ->first();

// Check 2: By fingerprint (includes date)
$fingerprint = hash('sha256', 
    $bancoNormalizado.'|'.$boletaNormalizada.'|'.$programaAsignado->estudiante_programa_id.'|'.$fechaYmd);
$kardexPorFingerprint = KardexPago::where('boleta_fingerprint', $fingerprint)->first();
```

### 3. Controller Update (`app/Http/Controllers/Api/EstudiantePagosController.php`)
- Updated `subirReciboPago()` to calculate fingerprint with student + date
- Modified `prevalidarRecibo()` to search by banco+boleta (conservative pre-check)
- Ensures consistency across manual and bulk imports

### 4. Database Migration (`database/migrations/2025_10_04_000000_update_kardex_pagos_fingerprint_to_include_student_and_date.php`)
- Recalculates fingerprints for ALL existing records
- Processes in chunks (500 records at a time) to avoid memory issues
- Temporarily drops unique constraint during update, then re-adds it
- Includes rollback logic (with caveat about duplicate detection)

### 5. Comprehensive Tests
- Added tests in `tests/Unit/PaymentHistoryImportTest.php`
- Created `tests/Unit/KardexPagoTest.php` with collision prevention tests
- Validates:
  - Different students get different fingerprints
  - Different dates get different fingerprints
  - Normalization functions work correctly
  - No collisions in real-world scenarios

## 🧪 Test Results

### Fingerprint Calculation Test
```
OLD FORMAT (banco | boleta):
Case 1: Student=AMS2020130 Boleta=652002 => 0a0651910d7aa81c...
Case 2: Student=AMS2020130 Boleta=901002 => f25460ed691c42ed...
Case 3: Student=ASM2020103 Boleta=652002 => 0a0651910d7aa81c...
❌ OLD FORMAT COLLISION: Student ASM2020103 has same fingerprint as Student AMS2020130

NEW FORMAT (banco | boleta | student_id | date):
Case 1: Student=AMS2020130 Boleta=652002 Date=2020-08-01 => e9f39a2090a3a3d7...
Case 2: Student=AMS2020130 Boleta=901002 Date=2020-09-01 => 68dcda2b3832c8b7...
Case 3: Student=ASM2020103 Boleta=652002 Date=2020-08-01 => 431c127037e4ea5f...
✅ No collisions detected with NEW format
```

## 📊 Impact Assessment

### Positive Impacts
1. ✅ **Prevents false duplicates**: Different students can now use the same receipt number
2. ✅ **Historical import support**: Allows importing old data where receipt numbers were reused
3. ✅ **Better audit trail**: Fingerprint now uniquely identifies each payment transaction
4. ✅ **Graceful failure**: Duplicate payments are logged as warnings, not errors

### Migration Considerations
1. **Existing data**: Migration will recalculate ALL existing fingerprints
2. **Downtime**: Brief lock during constraint drop/re-add
3. **Rollback**: Can be reversed, but may fail if old format had hidden duplicates
4. **Testing**: Recommended to test on staging environment first

## 🚀 Deployment Steps

### 1. Pre-Deployment
```bash
# Backup the database
pg_dump -U postgres -d asm_database -F c -b -v -f backup_before_fingerprint_fix.backup

# Check current fingerprint values (optional)
SELECT COUNT(*), COUNT(DISTINCT boleta_fingerprint) 
FROM kardex_pagos;
```

### 2. Deployment
```bash
# Pull latest changes
git pull origin main

# Run migration
php artisan migrate

# Check migration success
php artisan migrate:status
```

### 3. Verification
```bash
# Verify fingerprints were updated
SELECT COUNT(*) as total,
       COUNT(DISTINCT boleta_fingerprint) as unique_fingerprints,
       COUNT(*) - COUNT(DISTINCT boleta_fingerprint) as potential_duplicates
FROM kardex_pagos;

# Expected: potential_duplicates = 0
```

### 4. Test Import
```bash
# Try importing the problematic file again
# Should now succeed without duplicate key violations
```

## 🔄 Rollback Plan

If issues arise:

```bash
# Rollback migration
php artisan migrate:rollback --step=1

# Restore from backup
pg_restore -U postgres -d asm_database -v backup_before_fingerprint_fix.backup
```

**Note**: Rollback will restore old fingerprint format, which may cause previous collision issues to reappear.

## 📚 Related Files

- `app/Models/KardexPago.php` - Model with fingerprint calculation
- `app/Imports/PaymentHistoryImport.php` - Import logic with duplicate checks
- `app/Http/Controllers/Api/EstudiantePagosController.php` - Manual payment submission
- `database/migrations/2025_10_04_000000_update_kardex_pagos_fingerprint_to_include_student_and_date.php` - Migration
- `tests/Unit/KardexPagoTest.php` - Model tests
- `tests/Unit/PaymentHistoryImportTest.php` - Import tests

## 🎯 Success Criteria

✅ Import completes without duplicate key violations
✅ Different students can use the same receipt numbers
✅ All existing payments remain intact
✅ New fingerprints are unique per transaction
✅ Tests pass successfully

## 📞 Support

If you encounter issues:
1. Check logs: `storage/logs/laravel.log`
2. Verify migration ran: `php artisan migrate:status`
3. Check for duplicate fingerprints: `SELECT boleta_fingerprint, COUNT(*) FROM kardex_pagos GROUP BY boleta_fingerprint HAVING COUNT(*) > 1;`
4. Contact development team with error details
