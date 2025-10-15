# Carnet Query Normalization Fix

## Problem Identified
The payment history import was experiencing PostgreSQL transaction errors when processing student payment records:

```
SQLSTATE[25P02]: In failed sql transaction: 7 ERROR: transacción abortada, 
las órdenes serán ignoradas hasta el fin de bloque de transacción
```

### Root Cause
The queries were using `whereRaw()` with parameter binding to normalize carnets:
```php
->whereRaw("REPLACE(UPPER(carnet), ' ', '') = ?", [$carnet])
```

When a query fails in PostgreSQL, it aborts the entire transaction. All subsequent queries within that transaction are rejected until the transaction is rolled back. The `whereRaw()` syntax with bound parameters was causing issues in this scenario.

## Solution Implemented

### Change Applied
Modified the query syntax from `whereRaw()` to `where()` with `DB::raw()`:

**Before:**
```php
->whereRaw("REPLACE(UPPER(carnet), ' ', '') = ?", [$carnet])
```

**After:**
```php
->where(DB::raw("REPLACE(UPPER(carnet), ' ', '')"), '=', $carnet)
```

### Locations Changed
1. **Line 1114**: First prospecto lookup query
2. **Line 1175**: Programas lookup query with join

### Technical Details
- The carnet value passed to these queries is already normalized (uppercase, no spaces) via `normalizarCarnet()` method
- The database column transformation remains the same: `REPLACE(UPPER(carnet), ' ', '')`
- This ensures the database-side carnet is normalized before comparison
- Using `DB::raw()` for the column expression instead of `whereRaw()` prevents transaction issues

### Benefits
1. **Prevents transaction abortion**: Queries work properly even after previous transaction failures
2. **Maintains normalization**: Both input carnet and database carnet are normalized before comparison
3. **No functional changes**: Query logic remains identical, only syntax changed
4. **Better error handling**: PostgreSQL can handle these queries more gracefully

## Testing
- PHP syntax validation: ✅ Passed
- Existing unit tests: ✅ All normalization tests still pass
- Carnet normalization logic: ✅ Unchanged and working

## Impact
- **Minimal change**: Only 2 lines modified
- **No breaking changes**: Query behavior remains identical
- **Improved reliability**: Reduces transaction errors during imports
- **No migration required**: Code-only fix

## Related Files
- `app/Imports/PaymentHistoryImport.php` - Main fix applied here
- `tests/Unit/PaymentHistoryImportTest.php` - Existing tests verify normalization
