# Fix: PostgreSQL Dashboard HAVING Clause Error

## 🐛 Problem Description

The administrative dashboard was failing with the following PostgreSQL error:

```
SQLSTATE[42703]: Undefined column: 7 ERROR: no existe la columna «total_programas»
LINE 1: ...tudiante_programa" group by "prospecto_id" having "total_pro...
```

### Error Context
- **Endpoint**: `GET /api/administracion/dashboard`
- **Controller**: `AdministracionController@dashboard`
- **Method**: `obtenerEstadisticasGenerales()`
- **Database**: PostgreSQL

## 🔍 Root Cause

PostgreSQL does not allow referencing column aliases defined in the SELECT clause directly within the HAVING clause. This is a known PostgreSQL behavior that differs from MySQL.

**Original Code (Line 252):**
```php
$estudiantesMultiplesProgramas = DB::table('estudiante_programa')
    ->select('prospecto_id', DB::raw('COUNT(*) as total_programas'))
    ->groupBy('prospecto_id')
    ->having('total_programas', '>', 1)  // ❌ Alias reference not allowed in PostgreSQL
    ->get();
```

The query was attempting to reference the alias `total_programas` in the HAVING clause, which PostgreSQL interprets as a column name rather than the computed value.

## ✅ Solution

Replace the `having()` method with `havingRaw()` to use the raw SQL expression:

**Fixed Code:**
```php
$estudiantesMultiplesProgramas = DB::table('estudiante_programa')
    ->select('prospecto_id', DB::raw('COUNT(*) as total_programas'))
    ->groupBy('prospecto_id')
    ->havingRaw('COUNT(*) > ?', [1])  // ✅ Uses raw expression
    ->get();
```

### Why This Works

1. `havingRaw()` evaluates the SQL expression directly
2. PostgreSQL can compute `COUNT(*) > 1` without needing to reference an alias
3. The parameter binding (`?`, [1]) prevents SQL injection
4. The alias `total_programas` is still available in the SELECT results

## 📝 Technical Details

### Laravel Query Builder Behavior

| Method | MySQL | PostgreSQL |
|--------|-------|------------|
| `having('alias', '>', 1)` | ✅ Works | ❌ Fails |
| `havingRaw('COUNT(*) > ?', [1])` | ✅ Works | ✅ Works |

### SQL Generated

**Before (failing):**
```sql
SELECT "prospecto_id", COUNT(*) as total_programas 
FROM "estudiante_programa" 
GROUP BY "prospecto_id" 
HAVING "total_programas" > 1
```

**After (working):**
```sql
SELECT "prospecto_id", COUNT(*) as total_programas 
FROM "estudiante_programa" 
GROUP BY "prospecto_id" 
HAVING COUNT(*) > 1
```

## 🧪 Testing

### Manual Testing

Test the dashboard endpoint:
```bash
curl -X GET http://localhost:8000/api/administracion/dashboard \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

Expected response includes:
```json
{
  "estadisticas": {
    "totalEstudiantes": 523,
    "totalProgramas": 12,
    "totalCursos": 145,
    "estudiantesEnMultiplesProgramas": {
      "total": 78,
      "promedio": 2.3,
      "maximo": 4,
      "top5": [...]
    }
  }
}
```

### Database Testing

Verify the query works directly in PostgreSQL:
```sql
-- This should work now
SELECT prospecto_id, COUNT(*) as total_programas 
FROM estudiante_programa 
GROUP BY prospecto_id 
HAVING COUNT(*) > 1;
```

## 🔄 Related Patterns

This fix follows the same pattern already used elsewhere in the codebase:

**DashboardFinancieroController.php (Line 86):**
```php
->havingRaw('COUNT(DISTINCT ep.id) > 0')
```

## ✅ Verification Checklist

- [x] Fixed the HAVING clause to use `havingRaw()`
- [x] Verified PHP syntax is correct
- [x] Checked for similar issues in the codebase (none found)
- [x] Documented the fix
- [x] Follows existing codebase patterns

## 📚 References

- **File Modified**: `app/Http/Controllers/Api/AdministracionController.php`
- **Line Changed**: 252
- **PostgreSQL Documentation**: [7.2.3. The HAVING Clause](https://www.postgresql.org/docs/current/queries-table-expressions.html#QUERIES-GROUP)
- **Laravel Documentation**: [Query Builder - havingRaw](https://laravel.com/docs/queries#raw-methods)

## 🚀 Deployment Notes

This fix:
- ✅ Is backwards compatible
- ✅ Works with both PostgreSQL and MySQL
- ✅ Does not require database migrations
- ✅ Does not change the API response structure
- ✅ Has no performance impact

The dashboard should now load correctly without errors.
