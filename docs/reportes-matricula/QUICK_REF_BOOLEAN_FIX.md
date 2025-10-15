# Quick Reference: PostgreSQL Boolean Comparison Fix

## Problem
```
SQLSTATE[42883]: el operador no existe: boolean = integer
```

## Root Cause
Comparing a PostgreSQL `BOOLEAN` field with an integer value (`1` or `0`) instead of boolean literals (`TRUE` or `FALSE`).

## Solution

### ❌ Incorrect (causes error in PostgreSQL)
```php
DB::raw("CASE WHEN column_name = 1 THEN 'Yes' ELSE 'No' END")
```

### ✅ Correct (works in all databases)
```php
DB::raw("CASE WHEN column_name = TRUE THEN 'Yes' ELSE 'No' END")
```

## Alternative Solutions

### Option 1: Direct Boolean (simplest)
```php
DB::raw("CASE WHEN column_name THEN 'Yes' ELSE 'No' END")
```

### Option 2: Cast to Integer (PostgreSQL specific)
```php
DB::raw("CASE WHEN column_name::integer = 1 THEN 'Yes' ELSE 'No' END")
```

### Option 3: IS TRUE/IS FALSE (explicit)
```php
DB::raw("CASE WHEN column_name IS TRUE THEN 'Yes' ELSE 'No' END")
```

## Database Compatibility

| Solution | PostgreSQL | MySQL | SQLite | MariaDB |
|----------|------------|-------|--------|---------|
| `= TRUE` | ✅ | ✅ | ✅ | ✅ |
| `= 1` | ❌ | ✅ | ✅ | ✅ |
| Direct boolean | ✅ | ✅ | ✅ | ✅ |
| `::integer` | ✅ | ❌ | ❌ | ❌ |
| `IS TRUE` | ✅ | ✅ | ✅ | ✅ |

## Laravel Migration Definition
```php
$table->boolean('column_name')->default(true);
```

This creates:
- **PostgreSQL**: `boolean` type (true/false)
- **MySQL**: `tinyint(1)` (0/1)
- **SQLite**: `integer` (0/1)

## When to Use Each Approach

### Use `= TRUE` when:
- Writing cross-database compatible code
- Need explicit boolean comparison
- Working with CASE statements
- Code readability is important

### Use direct boolean when:
- Simple true/false checks
- Don't need text transformation
- Want shortest syntax

### Use `IS TRUE` when:
- Need to handle NULL values explicitly
- Want most explicit syntax
- PostgreSQL-specific code

## Common Patterns

### Status Badge
```php
DB::raw("CASE WHEN active = TRUE THEN 'Active' ELSE 'Inactive' END as status")
```

### Yes/No Display
```php
DB::raw("CASE WHEN completed = TRUE THEN 'Yes' ELSE 'No' END as completed_text")
```

### Multiple Conditions
```php
DB::raw("CASE 
    WHEN active = TRUE AND verified = TRUE THEN 'Verified'
    WHEN active = TRUE THEN 'Active'
    ELSE 'Inactive'
END as status")
```

## Quick Checklist

When you see this error:
- [ ] Identify the boolean column in the error message
- [ ] Find the comparison in your code (`= 1` or `= 0`)
- [ ] Replace with `= TRUE` or `= FALSE`
- [ ] Test in your target database
- [ ] Verify cross-database compatibility if needed

## Related Files in This Fix
- `app/Http/Controllers/Api/AdministracionController.php` (line 932)
- `database/migrations/2025_07_23_164222_add_carnet_and_activo_to_prospectos_table.php`
- `FIX_POSTGRESQL_BOOLEAN_COMPARISON.md` (detailed documentation)

---

**Date**: 2025-10-13  
**Issue**: PostgreSQL boolean = integer error  
**Solution**: Use TRUE/FALSE instead of 1/0
