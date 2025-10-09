# Quick Reference: New Routes Structure

## Overview

Routes have been reorganized from a single 719-line file into 6 domain-specific modules.

## File Structure

```
routes/
├── api.php (83 lines)              # Main entry, loads all modules
└── api/
    ├── public.php (98 lines)       # No auth required
    ├── prospectos.php (105 lines)  # Lead management (auth required)
    ├── seguimiento.php (61 lines)  # Follow-up activities (auth required)
    ├── academico.php (156 lines)   # Academic domain (mixed auth)
    ├── financiero.php (186 lines)  # Financial domain (auth required)
    └── administracion.php (132 lines) # Administration (auth required)
```

## Quick Domain Lookup

| What you need | File to check |
|--------------|---------------|
| Health checks, login, public access | `public.php` |
| Prospectos, leads, documents, imports | `prospectos.php` |
| Citas, tareas, interacciones | `seguimiento.php` |
| Programs, courses, students, Moodle | `academico.php` |
| Payments, invoices, reconciliation | `financiero.php` |
| Users, roles, permissions, sessions | `administracion.php` |

## Common Routes

### Health Check
- **Primary**: `GET /health`
- **Legacy**: `/ping`, `/status`, `/version`, `/time`, `/db-status`

### Authentication
- `POST /login` (public.php)
- `POST /logout` (administracion.php, requires auth)
- `GET /user` (administracion.php, requires auth)

### Prospectos
- `GET /prospectos` - List
- `POST /prospectos` - Create
- `GET /prospectos/{id}` - View
- `PUT /prospectos/{id}` - Update
- `DELETE /prospectos/{id}` - Delete

### Documents
- `GET /documentos` - List
- `POST /documentos` - Upload
- `GET /documentos/prospecto/{id}` - By prospect

### Courses
- `GET /courses` - List
- `POST /courses` - Create
- `POST /courses/assign` - Assign to students

### Payments
- `GET /payments` - List
- `POST /payments` - Create
- `GET /estudiante/pagos/pendientes` - Student pending payments

### Reconciliation
- **English**: `/reconciliation/*` (standard)
- **Spanish**: `/conciliacion/*` (compatibility)

## No Changes Required!

✅ **Frontend**: No changes needed, all URLs work as before
✅ **Controllers**: No changes needed
✅ **Middleware**: No changes needed
✅ **Tests**: Should pass without modification

## Benefits

1. **Faster Navigation**: Find routes by domain quickly
2. **Easier Maintenance**: Changes isolated to specific files
3. **Better Organization**: Clear separation of concerns
4. **Reduced Conflicts**: Multiple developers can work simultaneously
5. **Improved Documentation**: Each file documents its domain

## Need More Info?

See `ROUTES_REFACTORING.md` for complete documentation.

## Testing

```bash
# Check syntax
php -l routes/api.php
for file in routes/api/*.php; do php -l "$file"; done

# Run tests (when composer is installed)
php artisan test

# List all routes
php artisan route:list
```

## Rollback

If needed, the old `routes/api.php` is in git history:

```bash
git show HEAD~1:routes/api.php > routes/api.php.backup
```
