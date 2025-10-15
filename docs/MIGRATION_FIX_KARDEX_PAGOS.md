# Migration Fix: Missing Columns in kardex_pagos Table

## Problem Description

The PaymentHistoryImport functionality was failing with a PostgreSQL error:

```
SQLSTATE[42703]: Undefined column: 7 ERROR: no existe la columna «uploaded_by» en la relación «kardex_pagos»
```

This error occurred because the `KardexPago` model defined several columns in its `$fillable` array that didn't exist in the actual database table:
- `uploaded_by`
- `updated_by`
- `fecha_recibo`

## Root Cause

The model was updated to include these fields, but the corresponding database migration was never created. When the `PaymentHistoryImport` class attempted to insert records with these fields, the database rejected them.

## Solution

Created migration `2025_10_02_180000_add_missing_fields_to_kardex_pagos_table.php` that adds:

1. **fecha_recibo** (date, nullable) - Stores the receipt date
2. **uploaded_by** (unsignedBigInteger, nullable) - Foreign key to `users` table, tracks who uploaded the payment
3. **updated_by** (unsignedBigInteger, nullable) - Foreign key to `users` table, tracks who last updated the payment

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
- Payment records can track who uploaded and updated them
- Receipt dates can be stored separately from payment dates

## Files Modified

- Created: `database/migrations/2025_10_02_180000_add_missing_fields_to_kardex_pagos_table.php`

## Related Files

- `app/Models/KardexPago.php` - Model that expects these columns
- `app/Imports/PaymentHistoryImport.php` - Import class that uses these columns (lines 407, 411, 679)
- `database/migrations/2025_09_02_174252_add_created_by_to_kardex_pagos_table.php` - Previous migration that added `created_by`
