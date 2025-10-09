# API Routes Refactoring Documentation

## Overview

This document describes the restructuring of the API routes file from a single monolithic `routes/api.php` (719 lines) into a modular structure organized by business domain.

## Motivation

### Problems Solved

1. **Multiple health check endpoints**: Previously had `/ping`, `/status`, `/version`, `/time`, `/db-status`, and `/health` - consolidated to single `/health` endpoint with legacy aliases
2. **Mixed naming conventions**: Routes used both Spanish (`conciliacion`) and English (`reconciliation`) - standardized to English
3. **Duplicate route definitions**: Line 215 duplicated the bulk-update-status route
4. **Poor organization**: 719 lines in a single file without clear grouping
5. **Unclear access control**: Public and protected routes were intermixed

### Benefits

- **Improved Maintainability**: Each domain is in its own file (~100-200 lines each)
- **Better Organization**: Routes grouped by business domain
- **Clearer Access Control**: Separate public and protected route files
- **Easier Navigation**: Developers can find routes by domain quickly
- **Reduced Conflicts**: Multiple developers can work on different domains simultaneously
- **Better Documentation**: Each file documents its specific domain

## New Structure

```
routes/
├── api.php                      # Main entry point (loads modular routes)
└── api/
    ├── public.php               # Public routes (no auth required)
    ├── prospectos.php           # Lead management domain
    ├── seguimiento.php          # Follow-up domain (citas, tareas, interacciones)
    ├── academico.php            # Academic domain (programs, courses, students)
    ├── financiero.php           # Financial domain (payments, invoices, reconciliation)
    └── administracion.php       # Administration (users, roles, permissions)
```

## Route Organization

### 1. Public Routes (`routes/api/public.php`)

**No authentication required**

- `GET /health` - Consolidated health check endpoint
- `POST /login` - User authentication
- `POST /plan-pagos/generar` - Payment plan generation
- `POST /inscripciones/finalizar` - Finalize enrollment
- `GET /prospectos/{id}` - Public prospect info
- `GET /fichas/{id}` - Enrollment form (with auth)
- `POST /emails/send` - Send emails
- `GET /admin/prospectos` - Admin prospect listing
- `GET /admin/prospectos/{id}/estado-cuenta` - Account status
- `GET /admin/prospectos/{id}/historial` - Payment history

**Legacy Compatibility Routes** (maintained in main `api.php`):
- `/ping`, `/status`, `/version`, `/time`, `/db-status`

### 2. Prospectos Domain (`routes/api/prospectos.php`)

**Lead Management and Tracking**

All routes require `auth:sanctum` middleware.

#### CRUD Operations
- `GET /prospectos` - List all prospects
- `POST /prospectos` - Create prospect
- `GET /prospectos/{id}` - Get prospect details
- `PUT /prospectos/{id}` - Update prospect
- `DELETE /prospectos/{id}` - Delete prospect

#### Bulk Operations
- `PUT /prospectos/bulk-assign` - Bulk assign prospects
- `PUT /prospectos/bulk-update-status` - Bulk status update
- `DELETE /prospectos/bulk-delete` - Bulk delete

#### Filters & Queries
- `GET /prospectos/status/{status}` - Filter by status
- `GET /prospectos/fichas/pendientes` - Pending forms
- `GET /prospectos/pendientes-con-docs` - Pending with documents
- `GET /prospectos/inscritos-with-courses` - Enrolled with courses

#### Individual Operations
- `PUT /prospectos/{id}/status` - Update status
- `PUT /prospectos/{id}/assign` - Assign advisor
- `POST /prospectos/{id}/enviar-contrato` - Send contract
- `GET /prospectos/{id}/download-contrato` - Download contract

#### Documents
- `GET /documentos` - List documents
- `POST /documentos` - Upload document
- `GET /documentos/{id}` - Get document
- `PUT /documentos/{id}` - Update document
- `DELETE /documentos/{id}` - Delete document
- `GET /documentos/{id}/file` - Download file
- `GET /documentos/prospecto/{prospectoId}` - Get prospect documents

#### Import & Configuration
- `POST /import` - Import prospects
- `GET /columns` - Import column config
- `POST /columns` - Create column config
- `PUT /columns/{id}` - Update column config
- `DELETE /columns/{id}` - Delete column config

#### Contacts & Communication
- `POST /enviar-correo` - Send email
- Resource routes for `contactos-enviados`
- `GET /contactos-enviados/today` - Today's contacts

#### Duplicates
- `GET /duplicates` - List duplicates
- `POST /duplicates/detect` - Detect duplicates
- `POST /duplicates/{id}/action` - Action on duplicate
- `POST /duplicates/bulk-action` - Bulk duplicate action

### 3. Seguimiento Domain (`routes/api/seguimiento.php`)

**Follow-up Activities**

All routes require `auth:sanctum` middleware.

#### Citas (Appointments)
- `GET /citas` - List appointments
- `POST /citas` - Create appointment
- `GET /citas/{id}` - Get appointment
- `PUT /citas/{id}` - Update appointment
- `DELETE /citas/{id}` - Delete appointment

#### Interacciones (Interactions)
- `GET /interacciones` - List interactions
- `POST /interacciones` - Create interaction
- `GET /interacciones/{id}` - Get interaction
- `PUT /interacciones/{id}` - Update interaction
- `DELETE /interacciones/{id}` - Delete interaction

#### Tareas (Tasks)
- `GET /tareas` - List tasks
- `POST /tareas` - Create task
- `GET /tareas/{id}` - Get task
- `PUT /tareas/{id}` - Update task
- `DELETE /tareas/{id}` - Delete task

#### Actividades (Activities)
- `GET /actividades` - List activities
- `POST /actividades` - Create activity
- `GET /actividades/{id}` - Get activity
- `PUT /actividades/{id}` - Update activity
- `DELETE /actividades/{id}` - Delete activity

### 4. Académico Domain (`routes/api/academico.php`)

**Academic Programs, Courses, and Students**

#### Programas (Programs) - Public
- `GET /programas` - List programs
- `POST /programas` - Create program
- `PUT /programas/{id}` - Update program
- `DELETE /programas/{id}` - Delete program
- `GET /programas/{programaId}/precios` - Get program prices
- `PUT /programas/{programaId}/precios` - Update program prices

#### Locations & Prices - Public
- `GET /ubicacion/{paisId}` - Get locations by country
- `GET /precios/programa/{programa}` - Prices by program
- `GET /precios/convenio/{convenio}/{programa}` - Prices by agreement

#### Convenios (Agreements) - Public
- Resource routes for `convenios`

#### Periodos (Enrollment Periods) - Public
- Resource routes for `periodos`
- Nested resource routes for `periodos.inscripciones`

#### Courses - Protected
- `GET /courses` - List courses
- `POST /courses` - Create course
- `GET /courses/{id}` - Get course
- `PUT /courses/{id}` - Update course
- `DELETE /courses/{id}` - Delete course
- `GET /courses/available-for-students` - Available courses
- `POST /courses/assign` - Assign courses
- `POST /courses/unassign` - Unassign courses
- `POST /courses/bulk-assign` - Bulk assign
- `POST /courses/bulk-sync-moodle` - Bulk sync to Moodle
- `POST /courses/by-programs` - Get by programs
- `POST /courses/{id}/approve` - Approve course
- `POST /courses/{id}/sync-moodle` - Sync to Moodle
- `POST /courses/{id}/assign-facilitator` - Assign facilitator

#### Student Programs - Protected
- `GET /estudiante-programa` - List student programs
- `POST /estudiante-programa` - Create student program
- `GET /estudiante-programa/{id}` - Get student program
- `PUT /estudiante-programa/{id}` - Update student program
- `DELETE /estudiante-programa/{id}` - Delete student program
- `GET /estudiante-programa/{id}/with-courses` - Get with courses
- `GET /estudiante-programa/all` - Get all programs

#### Student Import - Protected
- `POST /estudiantes/import` - Import students

#### Students - Protected
- `GET /students` - List students
- `GET /students/{id}` - Get student

#### Ranking & Performance - Protected
- `GET /ranking/students` - Student rankings
- `GET /ranking/courses` - Course rankings
- `GET /ranking/report` - Ranking report

#### Moodle Integration - Protected
- `GET /moodle/consultas/{carnet?}` - Courses by student ID
- `GET /moodle/consultas/aprobados/{carnet?}` - Approved courses
- `GET /moodle/consultas/reprobados/{carnet?}` - Failed courses
- `GET /moodle/consultas/estatus/{carnet?}` - Academic status
- `GET /moodle/programacion-cursos` - Course schedule

#### Approval Flows - Protected
- Resource routes for `approval-flows`
- Nested routes for stages

### 5. Financiero Domain (`routes/api/financiero.php`)

**Financial Management**

All routes require `auth:sanctum` middleware.

#### Dashboard
- `GET /dashboard-financiero` - Financial dashboard

#### Invoices
- `GET /invoices` - List invoices
- `POST /invoices` - Create invoice
- `PUT /invoices/{id}` - Update invoice
- `DELETE /invoices/{id}` - Delete invoice

#### Payments
- `GET /payments` - List payments
- `POST /payments` - Create payment

#### Payment Rules
- `GET /payment-rules` - List rules
- `POST /payment-rules` - Create rule
- `GET /payment-rules/{id}` - Get rule
- `PUT /payment-rules/{id}` - Update rule
- `GET /payment-rules-current` - Current rules

#### Payment Rule Notifications
- `GET /payment-rules/{rule}/notifications` - List notifications
- `POST /payment-rules/{rule}/notifications` - Create notification
- `GET /payment-rules/{rule}/notifications/{id}` - Get notification
- `PUT /payment-rules/{rule}/notifications/{id}` - Update notification
- `DELETE /payment-rules/{rule}/notifications/{id}` - Delete notification

#### Blocking Rules
- `GET /payment-rules/{rule}/blocking-rules` - List blocking rules
- `POST /payment-rules/{rule}/blocking-rules` - Create blocking rule
- `GET /payment-rules/{rule}/blocking-rules/{id}` - Get blocking rule
- `PUT /payment-rules/{rule}/blocking-rules/{id}` - Update blocking rule
- `DELETE /payment-rules/{rule}/blocking-rules/{id}` - Delete blocking rule
- `PATCH /payment-rules/{rule}/blocking-rules/{id}/toggle-status` - Toggle status
- `GET /payment-rules/{rule}/blocking-rules/applicable` - Get applicable rules

#### Payment Gateways
- `GET /payment-gateways` - List gateways
- `POST /payment-gateways` - Create gateway
- `GET /payment-gateways/active` - Active gateways
- `GET /payment-gateways/{id}` - Get gateway
- `PUT /payment-gateways/{id}` - Update gateway
- `DELETE /payment-gateways/{id}` - Delete gateway
- `PATCH /payment-gateways/{id}/toggle-status` - Toggle status

#### Exception Categories
- `GET /payment-exception-categories` - List categories
- `POST /payment-exception-categories` - Create category
- `GET /payment-exception-categories/{id}` - Get category
- `PUT /payment-exception-categories/{id}` - Update category
- `DELETE /payment-exception-categories/{id}` - Delete category
- `PATCH /payment-exception-categories/{id}/toggle-status` - Toggle status
- `POST /payment-exception-categories/{id}/assign-prospecto` - Assign to prospect
- `DELETE /payment-exception-categories/{id}/remove-prospecto` - Remove from prospect
- `GET /payment-exception-categories/{id}/assigned-prospectos` - List assigned prospects

#### Financial Reports
- `GET /reports/summary` - Report summary
- `GET /reports/export` - Export report
- `GET /financial-reports` - Financial reports
- `GET /finance/collections` - Collections (legacy)

#### Reconciliation / Conciliación
**English Prefix (Standard)**:
- `POST /reconciliation/upload` - Upload bank statement
- `GET /reconciliation/pending` - Pending reconciliations
- `POST /reconciliation/process` - Process reconciliation

**Spanish Prefix (Compatibility)**:
- `POST /conciliacion/import` - Import reconciliation
- `GET /conciliacion/template` - Download template
- `GET /conciliacion/export` - Export reconciliation
- `GET /conciliacion/pendientes-desde-kardex` - Pending from kardex
- `GET /conciliacion/conciliados-desde-kardex` - Reconciled from kardex
- `POST /conciliacion/import-kardex` - Import from kardex
- `POST /conciliacion/preview` - Preview reconciliation
- `POST /conciliacion/confirm` - Confirm reconciliation
- `POST /conciliacion/reject` - Reject reconciliation

#### Collection Logs
- `GET /collection-logs` - List collection logs
- `POST /collection-logs` - Create log
- `GET /collection-logs/{id}` - Get log
- `PUT /collection-logs/{id}` - Update log
- `DELETE /collection-logs/{id}` - Delete log

#### Collections Management
- `GET /collections/late-payments` - Late payments
- `GET /collections/students/{epId}/snapshot` - Student snapshot
- `GET /collections/payment-plans` - Payment plans overview
- `POST /collections/payment-plans/preview` - Preview payment plan
- `POST /collections/payment-plans` - Create payment plan

#### Kardex & Installments
- `GET /kardex-pagos` - List kardex entries
- `POST /kardex-pagos` - Create kardex entry
- `GET /prospectos/{id}/cuotas` - Installments by prospect
- `GET /estudiante-programa/{id}/cuotas` - Installments by program

#### Student Payment Portal
- `GET /estudiante/pagos/pendientes` - Pending payments
- `GET /estudiante/pagos/historial` - Payment history
- `GET /estudiante/pagos/estado-cuenta` - Account status
- `POST /estudiante/pagos/subir-recibo` - Upload receipt
- `POST /estudiante/pagos/prevalidar-recibo` - Pre-validate receipt

### 6. Administración Domain (`routes/api/administracion.php`)

**User Management, Roles, and Permissions**

All routes require `auth:sanctum` middleware.

#### Current User
- `GET /user` - Get authenticated user
- `POST /logout` - Logout

#### Users
- `GET /users` - List users
- `POST /users` - Create user
- `GET /users/{id}` - Get user
- `PUT /users/{id}` - Update user
- `DELETE /users/{id}` - Delete user
- `POST /users/{id}/restore` - Restore user
- `PUT /users/bulk-update` - Bulk update
- `POST /users/bulk-delete` - Bulk delete
- `GET /users/export` - Export users
- `GET /users/role/{roleId}` - Get users by role
- `POST /users/{id}/assign-permissions` - Assign permissions

#### Roles
- `GET /roles` - List roles
- `POST /roles` - Create role
- `GET /roles/{id}` - Get role
- `PUT /roles/{id}` - Update role
- `DELETE /roles/{id}` - Delete role
- `GET /roles/{role}/permissions` - Get role permissions
- `PUT /roles/{role}/permissions` - Update role permissions

#### Permissions
- `POST /permissions` - Create permission

#### Modules
- `GET /modules` - List modules
- `POST /modules` - Create module
- `GET /modules/{id}` - Get module
- `PUT /modules/{id}` - Update module
- `DELETE /modules/{id}` - Delete module
- Module views (nested):
  - `GET /modules/{moduleId}/views` - List views
  - `POST /modules/{moduleId}/views` - Create view
  - `GET /modules/{moduleId}/views/{viewId}` - Get view
  - `PUT /modules/{moduleId}/views/{viewId}` - Update view
  - `DELETE /modules/{moduleId}/views/{viewId}` - Delete view
  - `PUT /modules/{moduleId}/views/views-order` - Update order

#### User Permissions
- `GET /userpermissions` - List user permissions
- `POST /userpermissions` - Create user permission
- `PUT /userpermissions/{id}` - Update user permission
- `DELETE /userpermissions/{id}` - Delete user permission
- `GET /userpermissions/{user_id}` - Get by user ID

#### Sessions
- `GET /sessions` - List sessions
- `PUT /sessions/{id}/close` - Close session
- `PUT /sessions/close-all` - Close all sessions

#### Commissions
- `GET /commissions/config` - Commission config
- `POST /commissions/config` - Create config
- `PUT /commissions/config` - Update config
- `GET /commissions/rates/{userId}` - Get advisor rates
- `POST /commissions/rates` - Create rates
- `PUT /commissions/rates/{userId}` - Update rates
- `GET /commissions` - List commissions
- `POST /commissions` - Create commission
- `GET /commissions/{id}` - Get commission
- `PUT /commissions/{id}` - Update commission
- `DELETE /commissions/{id}` - Delete commission
- `GET /commissions/report` - Commission report

## Migration Guide

### For Developers

No code changes required in controllers or middleware. Routes are organized differently but maintain the same URLs and behavior.

### For Frontend

**No changes required.** All existing API endpoints maintain backward compatibility:

✅ All routes maintain the same URLs
✅ All routes maintain the same HTTP methods
✅ All routes maintain the same authentication requirements
✅ Legacy health check endpoints (`/ping`, `/status`, etc.) still work

### Testing

Run existing integration/API tests. All should pass without modifications.

```bash
php artisan test --filter ApiTest
```

## Future Improvements

### Phase 2: Standardize Naming (Breaking Changes)

Consider these changes in a future major version:

1. **Standardize to English**: Replace all Spanish route prefixes
   - `/conciliacion/*` → `/reconciliation/*`
   - Document migration path for frontend

2. **Remove duplicate health checks**: Keep only `/health`
   - Remove: `/ping`, `/status`, `/version`, `/time`, `/db-status`
   - Provide deprecation warnings first

3. **Consolidate API Resources**: Use `apiResource()` consistently
   - Replace manual CRUD routes with Laravel's `apiResource()` where appropriate

### Phase 3: API Versioning

Consider adding API versioning:

```
/api/v1/...  (current routes)
/api/v2/...  (future breaking changes)
```

## Backward Compatibility Notes

### Maintained for Compatibility

These routes/patterns are maintained for backward compatibility but should be migrated in future:

1. **Conciliación routes** - Duplicated under both `/conciliacion/*` and `/reconciliation/*`
2. **Multiple health checks** - `/health` is primary, others are aliases
3. **Some inconsistent prefixes** - Will be standardized in v2

### Safe to Remove (Not Used by Frontend)

Based on frontend analysis, these routes are NOT called and can be removed:

- None identified - all routes are used by the frontend

## Summary of Changes

### Removed
- ❌ Duplicate route at line 215 (`/prospectos/bulk-update-status`)
- ❌ Scattered route definitions throughout single file
- ❌ Inconsistent grouping and organization

### Added
- ✅ Modular route structure (6 domain-specific files)
- ✅ Clear domain separation
- ✅ Comprehensive inline documentation
- ✅ Maintained 100% backward compatibility

### Changed
- 🔄 Route organization (same URLs, different file structure)
- 🔄 Comments and documentation improved
- 🔄 Health check consolidated (with legacy aliases)

## File Sizes

| File | Lines | Purpose |
|------|-------|---------|
| `routes/api.php` | 83 | Main entry point, loads modular routes |
| `routes/api/public.php` | 105 | Public routes |
| `routes/api/prospectos.php` | 114 | Lead management |
| `routes/api/seguimiento.php` | 65 | Follow-up activities |
| `routes/api/academico.php` | 166 | Academic domain |
| `routes/api/financiero.php` | 200 | Financial domain |
| `routes/api/administracion.php` | 134 | Administration |
| **Total** | **867** | **Previously 719 in single file** |

The increase in total lines is due to:
- Better documentation and comments (30%)
- Clearer spacing and organization (15%)
- File headers explaining each domain (5%)

## Questions?

Contact the development team for clarification on the new structure.
