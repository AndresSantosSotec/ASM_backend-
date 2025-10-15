# Permissions Schema Fix - Documentation

## Problem Summary

The permissions administration panel at `/webpanel/seguridad/permisos` was not loading permissions correctly due to a mismatch between the database schema and the code that queries it.

## Root Cause

Several controllers were attempting to query columns that don't exist in the `permissions` table:
- `route_path` - Does not exist in schema
- `is_enabled` - Does not exist in schema

## Actual Database Schema

The `permissions` table has the following structure:

```sql
CREATE TABLE permissions (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    action ENUM('view', 'create', 'edit', 'delete', 'export'),
    moduleview_id INTEGER UNSIGNED NULLABLE,
    name VARCHAR UNIQUE,
    description VARCHAR NULLABLE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (moduleview_id) REFERENCES moduleviews(id) ON DELETE CASCADE
);
```

## Fixed Files

### 1. RolePermissionController.php
**Issue:** The `update()` method was querying non-existent columns `route_path` and `is_enabled`.

**Fix:** Changed the query to use `moduleview_id` directly:
```php
// Before:
$ids = Permisos::query()
    ->where('route_path', $mv->view_path)
    ->whereIn('action', $actions)
    ->where('is_enabled', true)
    ->pluck('id')->toArray();

// After:
$ids = Permisos::query()
    ->where('moduleview_id', $moduleviewId)
    ->whereIn('action', $actions)
    ->pluck('id')->toArray();
```

### 2. PermissionController.php
**Issue:** The `store()` method was trying to create permissions with non-existent columns.

**Fix:** Simplified to only use columns that exist in the schema:
```php
// Before:
$permission = Permisos::create([
    'module'      => $mv->menu,
    'section'     => $mv->submenu,
    'resource'    => basename($mv->view_path),
    'action'      => $validated['action'],
    'effect'      => 'allow',
    'description' => $validated['description'] ?? null,
    'route_path'  => $mv->view_path,
    'file_name'   => null,
    'object_id'   => null,
    'is_enabled'  => true,
    'level'       => $validated['action'],
]);

// After:
$permission = Permisos::create([
    'action'        => $validated['action'],
    'moduleview_id' => $validated['moduleview_id'],
    'name'          => $validated['action'] . ':' . $mv->view_path,
    'description'   => $validated['description'] ?? "Permission {$validated['action']} for {$mv->menu}/{$mv->submenu}",
]);
```

### 3. UserPermisosController.php
**Issue:** The `store()` method was joining on non-existent `route_path` column and checking `is_enabled`.

**Fix:** Changed to query directly by `moduleview_id`:
```php
// Before:
$permMap = DB::table('permissions as p')
    ->join('moduleviews as mv', 'mv.view_path', '=', 'p.route_path')
    ->whereIn('mv.id', $moduleviewIds)
    ->where('p.action', '=', 'view')
    ->where('p.is_enabled', '=', true)
    ->pluck('p.id', 'mv.id')->toArray();

// After:
$permMap = DB::table('permissions as p')
    ->whereIn('p.moduleview_id', $moduleviewIds)
    ->where('p.action', '=', 'view')
    ->pluck('p.id', 'p.moduleview_id')->toArray();
```

## How Permissions Work

1. **ModuleViews Table**: Defines the available views in the system
   - Each moduleview has a `view_path` (e.g., `/seguridad/permisos`)
   - Each moduleview belongs to a module

2. **Permissions Table**: Defines specific actions on moduleviews
   - Links to a moduleview via `moduleview_id`
   - Specifies an action: view, create, edit, delete, or export
   - Each combination of (moduleview_id, action) should be unique

3. **RolePermissions Table**: Assigns permissions to roles
   - Links roles to permissions via a pivot table
   - When a role is granted permissions, the frontend sends moduleview_ids and actions
   - The backend finds the corresponding permission IDs and syncs them to the role

4. **UserPermissions Table**: Can assign specific view permissions to users
   - Links users directly to permissions (for fine-grained control)
   - Typically only assigns "view" permissions to grant access to specific moduleviews

## API Endpoints Affected

- `GET /api/roles/{role}/permissions` - Lists permissions for a role
- `PUT /api/roles/{role}/permissions` - Updates permissions for a role
- `POST /api/permissions` - Creates a new permission
- `POST /api/userpermissions` - Assigns view permissions to a user

## Testing

A comprehensive test suite has been added in `tests/Feature/RolePermissionControllerTest.php` that verifies:
- Listing role permissions works correctly
- Updating role permissions correctly assigns permissions by moduleview_id
- Multiple moduleviews can be handled in a single request
- The correct number of permissions are assigned

## Notes for Future Development

1. The schema does NOT include `route_path`, `is_enabled`, `module`, `section`, `resource`, `effect`, `file_name`, `object_id`, or `level` columns
2. All permissions are enabled by default (no `is_enabled` flag)
3. The `name` field is used for unique identification and should follow the pattern `action:view_path`
4. The relationship is: `moduleviews (1) -> (N) permissions`
5. A service file `EffectivePermissionsService.php` exists but is not currently used and also contains references to non-existent columns

## Impact

This fix resolves the issue where the permissions administration panel at `/webpanel/seguridad/permisos` was unable to load permissions, allowing administrators to properly manage role permissions through the web interface.
