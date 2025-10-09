# Routes Refactoring - Testing & Verification Checklist

## Pre-Deployment Verification

### ✅ File Structure
- [x] Created `routes/api/` directory
- [x] Created `routes/api/public.php`
- [x] Created `routes/api/prospectos.php`
- [x] Created `routes/api/seguimiento.php`
- [x] Created `routes/api/academico.php`
- [x] Created `routes/api/financiero.php`
- [x] Created `routes/api/administracion.php`
- [x] Updated `routes/api.php` to load modular files
- [x] All files have valid PHP syntax

### ✅ Documentation
- [x] Created `ROUTES_REFACTORING.md` (comprehensive docs)
- [x] Created `ROUTES_QUICK_REF.md` (quick reference)
- [x] Created `ROUTES_VISUAL.md` (visual overview)
- [x] Created `ROUTES_TESTING_CHECKLIST.md` (this file)
- [x] Inline comments in all route files

## Testing Checklist

### Phase 1: Syntax & Loading Tests

#### Syntax Check
```bash
cd /path/to/project
php -l routes/api.php
for file in routes/api/*.php; do php -l "$file"; done
```

- [ ] All files pass syntax check
- [ ] No parse errors reported

#### Route Loading
```bash
php artisan route:list
```

- [ ] Command runs without errors
- [ ] All expected routes are listed
- [ ] No duplicate route definitions
- [ ] Route counts match expectations (~200+ routes)

### Phase 2: API Endpoint Tests

Test representative endpoints from each domain:

#### Public Domain (`public.php`)
```bash
# Health Check
curl -X GET http://localhost:8000/api/health

# Login (replace with valid credentials)
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password"}'
```

- [ ] `/health` returns 200 OK with health status
- [ ] `/login` accepts credentials and returns token
- [ ] `/ping` returns pong (legacy compatibility)
- [ ] `/status` returns API running status

#### Prospectos Domain (`prospectos.php`)
```bash
# List prospectos (requires auth token)
curl -X GET http://localhost:8000/api/prospectos \
  -H "Authorization: Bearer YOUR_TOKEN"

# Get single prospecto
curl -X GET http://localhost:8000/api/prospectos/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

- [ ] `/prospectos` returns list with auth
- [ ] `/prospectos/{id}` returns single record
- [ ] `/documentos` endpoint accessible
- [ ] `/duplicates` endpoint accessible
- [ ] 401 without auth token

#### Seguimiento Domain (`seguimiento.php`)
```bash
# List citas
curl -X GET http://localhost:8000/api/citas \
  -H "Authorization: Bearer YOUR_TOKEN"

# List tareas
curl -X GET http://localhost:8000/api/tareas \
  -H "Authorization: Bearer YOUR_TOKEN"
```

- [ ] `/citas` returns appointments
- [ ] `/tareas` returns tasks
- [ ] `/interacciones` returns interactions
- [ ] `/actividades` returns activities

#### Académico Domain (`academico.php`)
```bash
# List programs (public)
curl -X GET http://localhost:8000/api/programas

# List courses (protected)
curl -X GET http://localhost:8000/api/courses \
  -H "Authorization: Bearer YOUR_TOKEN"
```

- [ ] `/programas` accessible without auth
- [ ] `/courses` requires auth
- [ ] `/students` endpoint works
- [ ] `/ranking/students` endpoint works
- [ ] `/moodle/*` endpoints accessible with auth

#### Financiero Domain (`financiero.php`)
```bash
# Dashboard (protected)
curl -X GET http://localhost:8000/api/dashboard-financiero \
  -H "Authorization: Bearer YOUR_TOKEN"

# Payments list
curl -X GET http://localhost:8000/api/payments \
  -H "Authorization: Bearer YOUR_TOKEN"

# Conciliación (Spanish prefix - compatibility)
curl -X GET http://localhost:8000/api/conciliacion/template \
  -H "Authorization: Bearer YOUR_TOKEN"

# Reconciliation (English prefix - standard)
curl -X GET http://localhost:8000/api/reconciliation/pending \
  -H "Authorization: Bearer YOUR_TOKEN"
```

- [ ] `/dashboard-financiero` returns dashboard data
- [ ] `/payments` returns payment list
- [ ] `/invoices` endpoint works
- [ ] `/payment-rules` endpoint works
- [ ] `/conciliacion/*` routes work (Spanish)
- [ ] `/reconciliation/*` routes work (English)
- [ ] Both prefixes return same data structure

#### Administración Domain (`administracion.php`)
```bash
# Get current user
curl -X GET http://localhost:8000/api/user \
  -H "Authorization: Bearer YOUR_TOKEN"

# List users
curl -X GET http://localhost:8000/api/users \
  -H "Authorization: Bearer YOUR_TOKEN"

# List roles
curl -X GET http://localhost:8000/api/roles \
  -H "Authorization: Bearer YOUR_TOKEN"
```

- [ ] `/user` returns authenticated user
- [ ] `/users` returns user list
- [ ] `/roles` returns roles list
- [ ] `/sessions` endpoint works
- [ ] `/modules` endpoint works

### Phase 3: Frontend Integration Tests

Run the frontend application and verify:

#### Captura Module
- [ ] Prospecto creation works
- [ ] Document upload works
- [ ] Bulk import works

#### Seguimiento Module
- [ ] Listing prospectos works
- [ ] Filtering by status works
- [ ] Creating interactions works
- [ ] Creating citas works
- [ ] Creating tareas works

#### Académico Module
- [ ] Program listing works
- [ ] Course listing works
- [ ] Student assignment works
- [ ] Document upload works
- [ ] Bulk student import works

#### Financiero Module
- [ ] Payment dashboard loads
- [ ] Payment listing works
- [ ] Invoice creation works
- [ ] Reconciliation import works
- [ ] Student payment portal works

#### Administración Module
- [ ] User listing works
- [ ] Role management works
- [ ] Permission assignment works
- [ ] Session management works

### Phase 4: Performance Tests

#### Route Loading Performance
```bash
# Time route caching
time php artisan route:cache
time php artisan route:clear
```

- [ ] Route caching completes successfully
- [ ] No significant performance degradation
- [ ] Cache size reasonable

#### Endpoint Response Times
Test response times for key endpoints:

- [ ] `/health` responds < 100ms
- [ ] `/prospectos` responds < 500ms
- [ ] `/courses` responds < 500ms
- [ ] `/payments` responds < 1s

### Phase 5: Error Handling

Test error scenarios:

#### Missing Auth Token
```bash
curl -X GET http://localhost:8000/api/prospectos
```
- [ ] Returns 401 Unauthorized
- [ ] Error message is clear

#### Invalid Route
```bash
curl -X GET http://localhost:8000/api/nonexistent
```
- [ ] Returns 404 Not Found
- [ ] Error handled gracefully

#### Invalid Method
```bash
curl -X POST http://localhost:8000/api/health
```
- [ ] Returns 405 Method Not Allowed
- [ ] Lists allowed methods

### Phase 6: Backward Compatibility

Verify all routes used by frontend still work:

#### From Frontend Analysis
Based on the problem statement, verify these specific routes:

- [ ] `GET /documentos`
- [ ] `PUT /documentos/{id}`
- [ ] `POST /estudiantes/import`
- [ ] `GET /users?email=...`
- [ ] `GET /users?username=...`
- [ ] `PUT /tareas/{id}`
- [ ] `POST /tareas`
- [ ] `DELETE /tareas/{id}`
- [ ] `DELETE /citas/{id}`
- [ ] `POST /login`
- [ ] `GET /prospectos`
- [ ] `GET /interacciones?id_lead=...`
- [ ] `GET /interacciones`
- [ ] `GET /citas`
- [ ] `GET /actividades`
- [ ] `POST /interacciones`
- [ ] `POST /citas`
- [ ] `GET /programas`
- [ ] `POST /programas`
- [ ] `DELETE /programas/{id}`
- [ ] `PUT /programas/{id}`
- [ ] `POST /logout`
- [ ] `GET /courses`
- [ ] `POST /courses/by-programs`
- [ ] `GET /estudiante-programa/{id}/with-courses`
- [ ] `POST /courses`
- [ ] `PUT /courses/{id}`
- [ ] `DELETE /courses/{id}`
- [ ] `POST /courses/{id}/approve`
- [ ] `POST /courses/{id}/sync-moodle`
- [ ] `POST /courses/{id}/assign-facilitator`
- [ ] `GET /users/role/2`
- [ ] `GET /courses/available-for-students`
- [ ] `GET /documentos/prospecto/{id}`
- [ ] `GET /admin/prospectos`
- [ ] `GET /admin/prospectos/{id}/estado-cuenta`
- [ ] `GET /convenios/{id}`
- [ ] `GET /ubicacion/{pais}`
- [ ] `GET /fichas/{id}`
- [ ] `GET /prospectos/{id}`
- [ ] `GET /reports/summary`
- [ ] `GET /reports/export`
- [ ] `GET /financial-reports`
- [ ] `GET /dashboard-financiero`
- [ ] `GET /invoices`
- [ ] `POST /invoices`
- [ ] `PUT /invoices/{id}`
- [ ] `DELETE /invoices/{id}`
- [ ] `GET /payments`
- [ ] `POST /payments`
- [ ] `GET /reconciliation/pending`
- [ ] `POST /reconciliation/upload`
- [ ] `POST /reconciliation/process`
- [ ] `GET /payment-rules`
- [ ] `GET /payment-rules-current`
- [ ] `GET /payment-rules/{id}`
- [ ] `POST /payment-rules`
- [ ] `PUT /payment-rules/{id}`
- [ ] `GET /payment-rules/{id}/notifications`
- [ ] `POST /payment-rules/{id}/notifications`
- [ ] `PUT /payment-rules/{id}/notifications/{notificationId}`
- [ ] `DELETE /payment-rules/{id}/notifications/{notificationId}`
- [ ] `GET /payment-rules/{id}/blocking-rules`
- [ ] `POST /payment-rules/{id}/blocking-rules`
- [ ] `PUT /payment-rules/{id}/blocking-rules/{blockingRuleId}`
- [ ] `DELETE /payment-rules/{id}/blocking-rules/{blockingRuleId}`
- [ ] `GET /payment-gateways`
- [ ] `GET /payment-gateways/active`
- [ ] `POST /payment-gateways`
- [ ] `PUT /payment-gateways/{id}`
- [ ] `DELETE /payment-gateways/{id}`
- [ ] `PATCH /payment-gateways/{id}/toggle-status`
- [ ] `GET /payment-exception-categories`
- [ ] `POST /payment-exception-categories`
- [ ] `PUT /payment-exception-categories/{id}`
- [ ] `DELETE /payment-exception-categories/{id}`
- [ ] `PATCH /payment-exception-categories/{id}/toggle-status`
- [ ] `POST /payment-exception-categories/{id}/assign-student`
- [ ] `GET /collection-logs`
- [ ] `POST /collection-logs`
- [ ] `PUT /collection-logs/{id}`
- [ ] `DELETE /collection-logs/{id}`
- [ ] `GET /finance/collections`
- [ ] `GET /collections/late-payments`
- [ ] `GET /collections/students/{epId}/snapshot`
- [ ] `GET /kardex-pagos`
- [ ] `POST /kardex-pagos`
- [ ] `GET /prospectos/{id}/cuotas`
- [ ] `GET /estudiante-programa/{id}/cuotas`
- [ ] `POST /emails/send`
- [ ] `GET /conciliacion/pendientes-desde-kardex`
- [ ] `POST /conciliacion/preview`
- [ ] `POST /conciliacion/confirm`
- [ ] `POST /conciliacion/reject`
- [ ] `POST /conciliacion/import`
- [ ] `GET /conciliacion/template`
- [ ] `GET /conciliacion/export`
- [ ] `GET /conciliacion/conciliados-desde-kardex`
- [ ] `POST /courses/bulk-sync-moodle`
- [ ] `GET /moodle/consultas/aprobados/{carnet}`
- [ ] `GET /estudiante/pagos/pendientes`
- [ ] `GET /estudiante/pagos/historial`
- [ ] `GET /estudiante/pagos/estado-cuenta`
- [ ] `POST /estudiante/pagos/prevalidar-recibo`
- [ ] `POST /estudiante/pagos/subir-recibo`
- [ ] `GET /moodle/programacion-cursos`
- [ ] `GET /ranking/students`
- [ ] `GET /ranking/courses`
- [ ] `GET /ranking/report`
- [ ] `GET /estudiante-programa`
- [ ] `GET /estudiante-programa/{id}`
- [ ] `GET /prospectos/status/{status}`
- [ ] `POST /courses/assign`
- [ ] `POST /courses/unassign`
- [ ] `POST /courses/bulk-assign`
- [ ] `GET /user`

## Automated Test Suite

### Unit Tests
```bash
php artisan test --filter RouteTest
```

- [ ] All route tests pass
- [ ] No deprecated routes
- [ ] All routes registered

### Integration Tests
```bash
php artisan test --filter ApiTest
```

- [ ] All API tests pass
- [ ] No authentication failures
- [ ] No unexpected errors

### Feature Tests
```bash
php artisan test --filter FeatureTest
```

- [ ] All feature tests pass
- [ ] Frontend integration intact
- [ ] Data flow correct

## Deployment Checklist

### Pre-Deployment
- [ ] All tests pass
- [ ] Documentation reviewed
- [ ] Team informed of changes
- [ ] Backup of old routes created
- [ ] Rollback plan prepared

### Deployment Steps
1. [ ] Pull latest code to staging
2. [ ] Run `composer install`
3. [ ] Clear route cache: `php artisan route:clear`
4. [ ] Cache routes: `php artisan route:cache`
5. [ ] Test key endpoints on staging
6. [ ] Deploy to production
7. [ ] Clear route cache on production
8. [ ] Cache routes on production
9. [ ] Monitor for errors (15 minutes)
10. [ ] Verify frontend functionality

### Post-Deployment
- [ ] All endpoints responding
- [ ] No 404 errors in logs
- [ ] Frontend working normally
- [ ] Performance metrics normal
- [ ] No user complaints

## Rollback Plan

If issues are found:

1. **Immediate Rollback**
   ```bash
   git revert HEAD
   git push
   php artisan route:clear
   php artisan route:cache
   ```

2. **Verify rollback successful**
   - [ ] Old routes working
   - [ ] Frontend functional
   - [ ] No errors in logs

3. **Post-Mortem**
   - Document the issue
   - Identify root cause
   - Plan fix
   - Re-test before re-deployment

## Success Criteria

✅ All automated tests pass
✅ All manual endpoint tests successful
✅ Frontend integration complete
✅ No performance degradation
✅ No increase in error rates
✅ Documentation complete
✅ Team understands new structure

## Notes

- **Testing Environment**: Staging server recommended
- **Testing Data**: Use test database with sample data
- **Auth Tokens**: Generate fresh tokens for each test session
- **Performance**: Baseline metrics captured before deployment
- **Monitoring**: Set up alerts for 404/500 errors

## Sign-off

- [ ] Developer: Routes refactored and tested
- [ ] QA: All tests passed
- [ ] Tech Lead: Code reviewed and approved
- [ ] DevOps: Deployment plan reviewed
- [ ] Product Owner: Changes understood and approved
