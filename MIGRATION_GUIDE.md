# Migration Guide: Fixing kardex_pagos Columns

## Overview
This guide explains how to apply the fixed migrations to resolve the `created_by` column error.

## The Problem
Your application was throwing this error:
```
SQLSTATE[42703]: Undefined column: 7 ERROR: no existe la columna «created_by» en la relación «kardex_pagos»
```

This happened because:
1. The `KardexPago` model expected columns that didn't exist in the database
2. Migration dependencies weren't properly handled
3. Some migrations might have been skipped or failed

## The Solution
We've made the migrations **idempotent** (safe to run multiple times) and **self-healing** (they create missing columns from earlier migrations).

## How to Apply the Fix

### Step 1: Backup Your Database
**IMPORTANT**: Always backup before running migrations in production!

```bash
# PostgreSQL backup
pg_dump -U postgres -d ASM_database -F c -f backup_before_migration_$(date +%Y%m%d_%H%M%S).dump

# Or using Laravel
php artisan backup:run  # if you have backup package installed
```

### Step 2: Check Current Migration Status
```bash
php artisan migrate:status
```

Look for these migrations:
- `2025_09_02_174252_add_created_by_to_kardex_pagos_table`
- `2025_10_02_180000_add_missing_fields_to_kardex_pagos_table`

### Step 3: Run the Migrations
```bash
# Run all pending migrations
php artisan migrate

# Or run them one by one for better control
php artisan migrate --step
```

### Step 4: Verify the Columns Were Added
Connect to your database and verify:

```sql
-- PostgreSQL
\d kardex_pagos

-- Or using SQL
SELECT column_name, data_type, is_nullable 
FROM information_schema.columns 
WHERE table_name = 'kardex_pagos' 
ORDER BY ordinal_position;
```

Expected columns should include:
- `created_by` (bigint, nullable, foreign key to users)
- `uploaded_by` (bigint, nullable, foreign key to users)
- `updated_by` (bigint, nullable, foreign key to users)
- `fecha_recibo` (date, nullable)

### Step 5: Test Payment Import
Try importing a payment history file to verify everything works:

```bash
# Run your payment import test
php artisan tinker
>>> $import = new \App\Imports\PaymentHistoryImport(1);
>>> // Test with a small file first
```

## Rollback Plan (If Needed)

If something goes wrong, you can rollback:

```bash
# Rollback the last migration
php artisan migrate:rollback --step=1

# Or rollback multiple steps
php artisan migrate:rollback --step=2

# Or restore from backup
pg_restore -U postgres -d ASM_database -c backup_before_migration_*.dump
```

## What Changed in the Migrations

### Migration 1: `add_created_by_to_kardex_pagos_table.php`
**Before:**
```php
$table->unsignedBigInteger('created_by')->nullable()->after('observaciones');
$table->foreign('created_by')->references('id')->on('users');
```

**After:**
```php
if (!Schema::hasColumn('kardex_pagos', 'created_by')) {
    $table->unsignedBigInteger('created_by')->nullable()->after('observaciones');
    $table->foreign('created_by')->references('id')->on('users');
}
```

### Migration 2: `add_missing_fields_to_kardex_pagos_table.php`
**Before:**
```php
$table->unsignedBigInteger('uploaded_by')->nullable()->after('created_by');
$table->foreign('uploaded_by')->references('id')->on('users');
```

**After:**
```php
// Adds created_by as fallback if it doesn't exist
if (!Schema::hasColumn('kardex_pagos', 'created_by')) {
    $table->unsignedBigInteger('created_by')->nullable()->after('observaciones');
    $table->foreign('created_by')->references('id')->on('users');
}

// Then adds uploaded_by without dependency on created_by position
if (!Schema::hasColumn('kardex_pagos', 'uploaded_by')) {
    $table->unsignedBigInteger('uploaded_by')->nullable()->after('observaciones');
    $table->foreign('uploaded_by')->references('id')->on('users');
}
```

## Key Improvements

1. **Idempotent**: Running migrations multiple times won't cause errors
2. **Self-Healing**: Missing columns from earlier migrations are automatically added
3. **No Position Dependencies**: Columns don't depend on other columns existing
4. **Safe Rollback**: Checks before dropping columns and foreign keys

## Troubleshooting

### Error: "column already exists"
✅ **Solution**: This is now handled! The migrations check before adding columns.

### Error: "relation does not exist"
❌ **Cause**: The `users` table doesn't exist
✅ **Solution**: Run all migrations from scratch: `php artisan migrate:fresh` (⚠️ WARNING: This deletes all data!)

### Error: "cannot drop foreign key"
✅ **Solution**: The rollback now checks if foreign keys exist before dropping them.

## Testing in Development

Before applying in production, test in a development environment:

```bash
# 1. Clone your production database to dev
# 2. Run the migrations
php artisan migrate

# 3. Test payment import
php artisan test --filter=PaymentHistoryImportTest

# 4. Manually test the import flow
```

## Support

If you encounter any issues:
1. Check the Laravel log: `storage/logs/laravel.log`
2. Check PostgreSQL logs
3. Run `php artisan migrate:status` to see migration state
4. Check column existence: `php artisan tinker` then `Schema::hasColumn('kardex_pagos', 'created_by')`

## Success Indicators

✅ All migrations show "Ran" status
✅ Payment imports work without errors
✅ No "undefined column" errors in logs
✅ Foreign keys are properly created

---

**Last Updated**: 2025-10-02
**Status**: Ready for Production
