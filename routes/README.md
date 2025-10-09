# Routes Directory

This directory contains the API route definitions for the ASM Backend system.

## Structure

```
routes/
├── api.php              # Main API entry point (loads modular routes)
├── api/                 # Modular route files by domain
│   ├── public.php       # Public routes (no authentication)
│   ├── prospectos.php   # Lead management (requires auth)
│   ├── seguimiento.php  # Follow-up activities (requires auth)
│   ├── academico.php    # Academic domain (mixed auth)
│   ├── financiero.php   # Financial domain (requires auth)
│   └── administracion.php # Administration (requires auth)
├── web.php              # Web routes (if any)
├── console.php          # Console/CLI routes
└── channels.php         # Broadcasting channels
```

## Quick Navigation

Looking for a specific route? Check these files:

| What you need | File to check |
|--------------|---------------|
| Health checks, login | `api/public.php` |
| Prospectos, leads, documents | `api/prospectos.php` |
| Appointments, tasks, interactions | `api/seguimiento.php` |
| Programs, courses, students | `api/academico.php` |
| Payments, invoices, reconciliation | `api/financiero.php` |
| Users, roles, permissions | `api/administracion.php` |

## Documentation

For complete documentation about the routes structure, see:

- **ROUTES_REFACTORING.md** - Complete technical documentation
- **ROUTES_QUICK_REF.md** - Quick reference guide
- **ROUTES_VISUAL.md** - Visual structure overview
- **ROUTES_TESTING_CHECKLIST.md** - Testing guide
- **ROUTES_IMPLEMENTATION_SUMMARY.md** - Implementation summary

## Making Changes

### Adding New Routes

1. Identify the domain (prospectos, academico, financiero, etc.)
2. Edit the corresponding file in `routes/api/`
3. Add routes in the appropriate section
4. Update documentation if needed

### Example: Adding a new prospecto route

Edit `routes/api/prospectos.php`:

```php
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('prospectos')->group(function () {
        // ... existing routes ...
        
        // New route
        Route::get('/{id}/analytics', [ProspectoController::class, 'analytics']);
    });
});
```

### Testing Routes

```bash
# List all routes
php artisan route:list

# Clear route cache
php artisan route:clear

# Cache routes for production
php artisan route:cache
```

## Authentication

- **Public routes**: No authentication required (in `api/public.php`)
- **Protected routes**: Require `auth:sanctum` middleware (all other files)

## Backward Compatibility

All existing routes are maintained for backward compatibility:

- Legacy health checks: `/ping`, `/status`, `/version`, `/time`, `/db-status`
- Spanish prefixes: `/conciliacion/*` (alongside English `/reconciliation/*`)

## Questions?

Refer to the comprehensive documentation in the repository root or contact the development team.
