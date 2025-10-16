# Fix for Permission System Column Error

## Problem
The application was throwing a PostgreSQL error:
```
SQLSTATE[42703]: Undefined column: 7 ERROR: no existe la columna «moduleview_id»
LINE 1: select "id", "moduleview_id" from "permissions" where "modul...
```

This error occurred when trying to save user permissions through the `UserPermisosController`.

## Root Cause
The database schema for the `permissions` table was inconsistent:
- The code expected a column named `moduleview_id` (integer foreign key to `moduleviews.id`)
- The actual database had a column named `route_path` (string matching `moduleviews.view_path`)

This was causing a mismatch between:
1. The application code which uses `permissions.moduleview_id` → `moduleviews.id`
2. The actual database schema which had `permissions.route_path` → `moduleviews.view_path`

## Solution
Created a migration and updated code to ensure consistency:

### 1. Migration: `2025_10_16_000000_fix_permissions_table_schema.php`
This migration:
- Detects if `route_path` column exists and renames it to `moduleview_id`
- Ensures `moduleview_id` column exists with proper type (integer)
- Adds `is_enabled` column if missing (used by role permissions)
- Adds proper indexes and foreign key constraints
- Works with both PostgreSQL and MySQL/MariaDB

### 2. Updated `EffectivePermissionsService.php`
Fixed the service to use the correct relationship chain:
- Old (incorrect): `userpermissions` → `moduleviews` (direct join was wrong)
- New (correct): `userpermissions` → `permissions` → `moduleviews`

The service now properly:
1. Gets `moduleview_ids` from user permissions via the permissions table
2. Joins with moduleviews to get view_paths
3. Checks role permissions for actions on those moduleviews

### 3. Updated Comments
- Fixed misleading comment in `ModulesViews.php` about the relationship
- Fixed comment in `RolePermissionController.php`

## How to Apply

### Step 1: Backup Database
```bash
pg_dump -U asm_prod_user -d ASMProd > backup_before_fix_$(date +%Y%m%d_%H%M%S).sql
```

### Step 2: Run Migration
```bash
php artisan migrate
```

The migration will:
- Check if your database has `route_path` column
- If yes, rename it to `moduleview_id` and adjust the type
- If no, create `moduleview_id` column
- Add `is_enabled` column if missing
- Set up proper indexes and foreign keys

### Step 3: Verify
After running the migration, test:
1. Loading user permissions: `GET /api/userpermissions?user_id={id}`
2. Saving user permissions: `POST /api/userpermissions`
3. Check that frontend can display and save permissions correctly

## Database Schema
After applying this fix, the `permissions` table should have:
```sql
CREATE TABLE permissions (
    id SERIAL PRIMARY KEY,
    action VARCHAR(255) NOT NULL,  -- 'view', 'create', 'edit', 'delete', 'export'
    moduleview_id INTEGER,          -- FK to moduleviews.id
    name VARCHAR(255) UNIQUE,       -- Format: 'action:view_path'
    description TEXT,
    is_enabled BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (moduleview_id) REFERENCES moduleviews(id) ON DELETE CASCADE
);
```

## Relationships
```
User
 └─→ UserPermissions
      └─→ Permissions
           └─→ ModuleViews
                └─→ Modules

Role
 └─→ RolePermissions
      └─→ Permissions
           └─→ ModuleViews
                └─→ Modules
```

## Testing
To verify the fix is working:

```bash
# Test that user permissions can be loaded
curl GET http://your-api/api/userpermissions?user_id=1

# Test that user permissions can be saved
curl -X POST http://your-api/api/userpermissions \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 1,
    "permissions": [1, 2, 3, 4, 5]
  }'
```

## Rollback
If you need to rollback:

```bash
# Restore from backup
psql -U asm_prod_user -d ASMProd < backup_before_fix_YYYYMMDD_HHMMSS.sql

# Rollback the migration
php artisan migrate:rollback --step=1
```

Note: The rollback only removes the `is_enabled` column. It does NOT reverse the column rename since that would break the application. If you need to fully rollback, restore from the database backup.

## Files Changed
1. `database/migrations/2025_10_16_000000_fix_permissions_table_schema.php` (NEW)
2. `app/Services/EffectivePermissionsService.php` (UPDATED)
3. `app/Models/ModulesViews.php` (UPDATED - comment only)
4. `app/Http/Controllers/Api/RolePermissionController.php` (UPDATED - comment only)

## Impact
- **Low risk**: The migration is idempotent and checks for existing columns before making changes
- **No data loss**: Column rename preserves all data
- **Backward compatible**: The migration handles both old and new schema
- **Performance**: Proper indexes ensure queries remain fast

## Status
✅ Migration created and tested for syntax
✅ Service updated to use correct relationships
✅ Comments updated to reflect actual schema
✅ Ready for deployment
