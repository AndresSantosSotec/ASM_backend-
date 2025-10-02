# Migration Fix: Missing Columns in kardex_pagos Table

## Problem Description

The PaymentHistoryImport functionality was failing with a PostgreSQL error:

```
SQLSTATE[42703]: Undefined column: 7 ERROR: no existe la columna «created_by» en la relación «kardex_pagos»
```

This error occurred because the `KardexPago` model defined several columns in its `$fillable` array that didn't exist in the actual database table:
- `created_by`
- `uploaded_by`
- `updated_by`
- `fecha_recibo`

## Root Cause

The model was updated to include these fields, but there was a migration dependency issue:

1. Migration `2025_09_02_174252_add_created_by_to_kardex_pagos_table.php` was supposed to add the `created_by` column
2. Migration `2025_10_02_180000_add_missing_fields_to_kardex_pagos_table.php` attempted to add `uploaded_by` and `updated_by` columns **after** `created_by`

However, if the first migration failed or wasn't run, the second migration would also fail because it tried to position columns relative to a non-existent `created_by` column.

## Solution

Updated both migrations to be more robust:

### Modified: `2025_09_02_174252_add_created_by_to_kardex_pagos_table.php`
- Added check: Only adds `created_by` column if it doesn't already exist
- Prevents duplicate column errors on re-run

### Modified: `2025_10_02_180000_add_missing_fields_to_kardex_pagos_table.php`
- Added checks for all columns before adding them
- Includes `created_by` column creation as fallback (in case earlier migration was skipped)
- Removes dependency on column positioning that might not exist
- All columns (`fecha_recibo`, `created_by`, `uploaded_by`, `updated_by`) are now added safely

This ensures the migration can run successfully regardless of which previous migrations were executed.

## How to Apply

Run the migration:
```bash
php artisan migrate
```

To rollback if needed:
```bash
php artisan migrate:rollback
```

## Impact

After applying this migration:
- PaymentHistoryImport will work correctly
- Payment records can track who created, uploaded, and updated them
- Receipt dates can be stored separately from payment dates
- Migration is idempotent - can be run multiple times safely

## Files Modified

- Modified: `database/migrations/2025_09_02_174252_add_created_by_to_kardex_pagos_table.php`
- Modified: `database/migrations/2025_10_02_180000_add_missing_fields_to_kardex_pagos_table.php`

## Related Files

- `app/Models/KardexPago.php` - Model that expects these columns
- `app/Imports/PaymentHistoryImport.php` - Import class that uses these columns (lines 407, 411, 679)

