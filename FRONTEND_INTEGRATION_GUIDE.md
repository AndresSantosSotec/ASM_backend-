# Frontend Integration Guide

## Changes Required in Frontend

The backend fix ensures that the permissions table uses `moduleview_id` (integer) instead of `route_path` (string). The frontend code you provided should work correctly with these changes, but here are some important notes:

## Current Frontend Implementation

Your frontend (`PermisosVistasTab`) already works correctly! Here's why:

### 1. Loading Permissions ✅
```typescript
const response = await axios.get(
  `${API_BASE_URL}/api/userpermissions?user_id=${selectedUsuario}`
);
```

The backend returns:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "user_id": 123,
      "permission_id": 456,
      "permission": {
        "id": 456,
        "moduleview_id": 19,
        "action": "view",
        "module_view": {
          "id": 19,
          "menu": "Dashboard",
          "submenu": "Overview",
          "view_path": "/dashboard"
        }
      }
    }
  ]
}
```

Your code extracts `moduleview_id`:
```typescript
const moduleViewIds = rows.map((row) => {
  return (
    row?.permission?.module_view?.id ??
    row?.permission?.moduleView?.id ??
    row?.permission?.module_view_id ??
    null
  );
}).filter((id: any) => typeof id === "number");
```

**Status**: ✅ This works correctly! It gets the `module_view.id` which is the `moduleview_id`.

### 2. Saving Permissions ✅
```typescript
const payload = {
  user_id: Number(selectedUsuario),
  permissions: selectedPermisos, // array of moduleview_id numbers
};
const response = await axios.post(`${API_BASE_URL}/api/userpermissions`, payload);
```

Your `selectedPermisos` state is an array of `moduleview_id` values (numbers):
```typescript
const [selectedPermisos, setSelectedPermisos] = useState<number[]>([]);
```

And you toggle them by moduleview id:
```typescript
const handleTogglePermiso = (id: number) => {
  setSelectedPermisos((prev) =>
    prev.includes(id) ? prev.filter((permId) => permId !== id) : [...prev, id]
  );
};
```

**Status**: ✅ This works correctly! You're sending moduleview_ids.

## Expected Behavior After Fix

### Loading Permissions
1. User selects a user from the dropdown
2. Frontend calls `GET /api/userpermissions?user_id={id}`
3. Backend returns permissions with proper relationships
4. Frontend extracts `moduleview_id` from each permission
5. Checkboxes are checked for those moduleview_ids

### Saving Permissions
1. User checks/unchecks moduleview checkboxes
2. State tracks array of `moduleview_id` numbers
3. User clicks "Save"
4. Frontend sends `{ user_id: number, permissions: number[] }`
5. Backend:
   - Validates moduleview_ids exist
   - Finds or creates 'view' permissions for those moduleviews
   - Deletes old user permissions
   - Creates new user permissions

### Auto-creation of Permissions
If a permission doesn't exist for a moduleview, the backend will automatically create it:
```php
// In UserPermisosController.php
if (!empty($missingMvIds)) {
    foreach ($missingMvIds as $mvId) {
        $moduleView = ModulesViews::find($mvId);
        if ($moduleView) {
            $perm = Permisos::create([
                'moduleview_id' => $mvId,
                'action' => 'view',
                'name' => 'view:' . $moduleView->view_path,
                'description' => 'Auto-created view permission for ' . $moduleView->submenu
            ]);
        }
    }
}
```

## No Frontend Changes Required!

Your frontend code is already correct and doesn't need any changes. The fix was entirely on the backend:

1. ✅ Database schema now matches what the code expects
2. ✅ EffectivePermissionsService uses correct relationships
3. ✅ UserPermisosController properly maps moduleview_ids to permission_ids
4. ✅ Auto-creation of missing permissions works

## Testing Checklist

### Test Case 1: Load Permissions
- [ ] Select a user
- [ ] Verify checkboxes show current permissions
- [ ] No console errors
- [ ] Network tab shows successful API call

### Test Case 2: Save Permissions
- [ ] Select a user
- [ ] Check/uncheck some moduleview permissions
- [ ] Click "Save"
- [ ] Verify success message
- [ ] Reload and verify permissions persist
- [ ] No console errors

### Test Case 3: Select All Module
- [ ] Expand a module
- [ ] Click "Select All" checkbox for that module
- [ ] Verify all views in that module are checked
- [ ] Save and verify
- [ ] Uncheck "Select All"
- [ ] Verify all views are unchecked

### Test Case 4: Search/Filter
- [ ] Use search to filter moduleviews
- [ ] Verify filtered results show correct permissions
- [ ] Select a module filter
- [ ] Verify only that module's views appear

## API Endpoints

### GET /api/userpermissions
**Query Params:**
- `user_id` (required): The user ID to get permissions for

**Response:**
```json
{
  "success": true,
  "message": "Permisos cargados correctamente.",
  "data": [
    {
      "id": 1,
      "user_id": 123,
      "permission_id": 456,
      "assigned_at": "2025-10-16T12:00:00Z",
      "scope": "self",
      "permission": {
        "id": 456,
        "action": "view",
        "moduleview_id": 19,
        "name": "view:/dashboard",
        "module_view": {
          "id": 19,
          "module_id": 1,
          "menu": "Dashboard",
          "submenu": "Overview",
          "view_path": "/dashboard",
          "status": true
        }
      }
    }
  ]
}
```

### POST /api/userpermissions
**Body:**
```json
{
  "user_id": 123,
  "permissions": [19, 20, 21, 22]  // array of moduleview_ids
}
```

**Response (Success):**
```json
{
  "success": true,
  "message": "Permisos actualizados correctamente.",
  "data": [/* updated permissions */]
}
```

**Response (Validation Error):**
```json
{
  "success": false,
  "message": "Datos inválidos",
  "errors": {
    "permissions.0": ["The selected permissions.0 is invalid."]
  }
}
```

## Common Issues and Solutions

### Issue: Checkboxes don't show current permissions
**Solution:** Check that the API response includes the nested `permission.module_view.id` or `permission.moduleview_id` field.

### Issue: "Datos inválidos" when saving
**Solution:** Ensure you're sending an array of valid moduleview_ids (integers) that exist in the `moduleviews` table.

### Issue: Some permissions don't save
**Solution:** The backend will auto-create missing 'view' permissions. Check Laravel logs for any errors during permission creation.

### Issue: Frontend sends wrong data format
**Solution:** Verify the payload structure matches:
```typescript
{
  user_id: number,
  permissions: number[]  // NOT objects, just IDs
}
```

## Summary

✅ **No frontend changes needed**
✅ Your current implementation is correct
✅ Backend now properly handles the schema
✅ Permissions auto-create when needed
✅ Ready to test and deploy
