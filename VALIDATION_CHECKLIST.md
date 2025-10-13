# ✅ Validation Checklist for Production Deployment

## Pre-Deployment Checks

### 1. Code Review ✓
- [x] All 7 migration files created
- [x] PHP syntax validated for all migrations
- [x] Rollback methods implemented for all migrations
- [x] Idempotent checks (safe to run multiple times)
- [x] No Doctrine DBAL dependencies (using information_schema)
- [x] Documentation created (English + Spanish)

### 2. Migration Files Present
```bash
cd database/migrations/
ls -la 2025_10_13_000001_add_fecha_recibo_to_kardex_pagos_table.php
ls -la 2025_10_13_000002_add_audit_fields_to_cuotas_programa_estudiante_table.php
ls -la 2025_10_13_000003_make_prospectos_fields_nullable.php
ls -la 2025_10_13_000004_add_indexes_to_kardex_pagos_table.php
ls -la 2025_10_13_000005_add_indexes_to_cuotas_programa_estudiante_table.php
ls -la 2025_10_13_000006_add_index_to_prospectos_carnet.php
ls -la 2025_10_13_000007_add_indexes_to_estudiante_programa_table.php
```
- [ ] All 7 files exist
- [ ] All files have correct permissions (644)

### 3. Documentation Present
```bash
ls -la MIGRATION_FIXES_COMPLETE.md
ls -la MIGRATION_SQL_PREVIEW.sql
ls -la DEPLOYMENT_GUIDE.md
ls -la RESUMEN_MIGRACIONES.md
ls -la VISUAL_SCHEMA_DIAGRAM.md
```
- [ ] All documentation files exist
- [ ] Documentation is clear and complete

## Deployment Steps

### Step 1: Backup Database ⚠️ CRITICAL
```bash
# For PostgreSQL
pg_dump -U username -d database_name > backup_$(date +%Y%m%d_%H%M%S).sql

# For MySQL
mysqldump -u username -p database_name > backup_$(date +%Y%m%d_%H%M%S).sql
```
- [ ] Database backup created
- [ ] Backup file size verified (not empty)
- [ ] Backup stored in safe location

### Step 2: Check Current Migration Status
```bash
php artisan migrate:status
```
- [ ] Command runs without errors
- [ ] All existing migrations show as "Ran"
- [ ] New migrations show as "Pending"

### Step 3: Run Migrations
```bash
# Dry run (check what will be executed)
php artisan migrate --pretend

# Actual migration
php artisan migrate --force
```
- [ ] Migrations run without errors
- [ ] All 7 new migrations completed successfully
- [ ] No constraint violation errors
- [ ] No timeout errors

### Step 4: Verify Migration Status
```bash
php artisan migrate:status
```
- [ ] All 7 new migrations show as "Ran"
- [ ] No migrations stuck in "Pending" state

## Post-Deployment Validation

### 1. Verify Schema Changes

#### Check kardex_pagos table
```sql
-- Check columns
DESCRIBE kardex_pagos;

-- Should include: fecha_recibo

-- Check indexes
SHOW INDEXES FROM kardex_pagos;

-- Should include:
-- - kardex_pagos_estudiante_programa_id_index
-- - kardex_pagos_boleta_student_index
```
- [ ] `fecha_recibo` column exists (DATE, NULL)
- [ ] `estudiante_programa_id` index exists
- [ ] Composite boleta/student index exists

#### Check cuotas_programa_estudiante table
```sql
-- Check columns
DESCRIBE cuotas_programa_estudiante;

-- Should include: created_by, updated_by, deleted_by

-- Check indexes
SHOW INDEXES FROM cuotas_programa_estudiante;

-- Should include:
-- - cuotas_estudiante_programa_id_index
-- - cuotas_estado_fecha_index
```
- [ ] `created_by` column exists (BIGINT, NULL)
- [ ] `updated_by` column exists (BIGINT, NULL)
- [ ] `deleted_by` column exists (BIGINT, NULL)
- [ ] `estudiante_programa_id` index exists
- [ ] Composite estado/fecha index exists

#### Check prospectos table
```sql
-- Check columns
DESCRIBE prospectos;

-- telefono and correo_electronico should be NULL-able

-- Check indexes
SHOW INDEXES FROM prospectos;

-- Should include:
-- - prospectos_carnet_index
```
- [ ] `telefono` is nullable (NULL: YES)
- [ ] `correo_electronico` is nullable (NULL: YES)
- [ ] `carnet` index exists

#### Check estudiante_programa table
```sql
-- Check indexes
SHOW INDEXES FROM estudiante_programa;

-- Should include:
-- - estudiante_programa_prospecto_id_index
-- - estudiante_programa_programa_id_index
```
- [ ] `prospecto_id` index exists
- [ ] `programa_id` index exists

### 2. Test Data Operations

#### Test 1: Create prospecto with null phone/email
```sql
INSERT INTO prospectos (
    carnet, nombre_completo, telefono, correo_electronico, 
    fecha, activo, created_at, updated_at
) VALUES (
    'TEST001', 'Test Student', NULL, NULL, 
    CURRENT_DATE, true, NOW(), NOW()
);

-- Should succeed without error
DELETE FROM prospectos WHERE carnet = 'TEST001';
```
- [ ] Insert succeeds
- [ ] No constraint violations

#### Test 2: Create cuota with audit fields
```sql
-- Get a valid estudiante_programa_id first
SELECT id FROM estudiante_programa LIMIT 1;

-- Insert cuota
INSERT INTO cuotas_programa_estudiante (
    estudiante_programa_id, numero_cuota, fecha_vencimiento,
    monto, estado, created_by, created_at, updated_at
) VALUES (
    /* use id from above */, 99, CURRENT_DATE,
    100.00, 'pendiente', 1, NOW(), NOW()
);

-- Should succeed without error
DELETE FROM cuotas_programa_estudiante WHERE numero_cuota = 99;
```
- [ ] Insert succeeds with created_by
- [ ] Audit fields work correctly

#### Test 3: Create kardex_pago with fecha_recibo
```sql
-- Get a valid estudiante_programa_id
SELECT id FROM estudiante_programa LIMIT 1;

-- Insert payment
INSERT INTO kardex_pagos (
    estudiante_programa_id, fecha_pago, fecha_recibo,
    monto_pagado, created_at, updated_at
) VALUES (
    /* use id from above */, NOW(), CURRENT_DATE,
    100.00, NOW(), NOW()
);

-- Should succeed without error
DELETE FROM kardex_pagos WHERE monto_pagado = 100.00 AND fecha_pago = NOW();
```
- [ ] Insert succeeds with fecha_recibo
- [ ] No field missing errors

### 3. Performance Validation

#### Check Index Usage
```sql
-- Explain query for carnet lookup (should use index)
EXPLAIN SELECT * FROM prospectos WHERE carnet = 'ASM123';

-- Should show: "Using index condition" or "index scan"

-- Explain query for quota matching (should use index)
EXPLAIN SELECT * FROM cuotas_programa_estudiante 
WHERE estudiante_programa_id = 1 
AND estado = 'pendiente' 
AND fecha_vencimiento <= CURRENT_DATE;

-- Should show: "Using index condition" or "index scan"

-- Explain query for duplicate check (should use index)
EXPLAIN SELECT * FROM kardex_pagos 
WHERE numero_boleta_normalizada = 'ABC123' 
AND estudiante_programa_id = 1;

-- Should show: "Using index condition" or "index scan"
```
- [ ] Carnet query uses index
- [ ] Quota query uses composite index
- [ ] Duplicate check uses composite index

### 4. Import Test

#### Test with Sample Excel
```bash
# Upload a test Excel file with payment history
# Monitor logs during import
tail -f storage/logs/laravel.log
```
- [ ] Import starts without errors
- [ ] No "column not found" errors
- [ ] No "timeout" errors
- [ ] Records inserted successfully
- [ ] Audit fields populated correctly

#### Verify Import Results
```sql
-- Check inserted payments
SELECT COUNT(*) FROM kardex_pagos 
WHERE created_at >= NOW() - INTERVAL 5 MINUTE;

-- Check updated quotas
SELECT COUNT(*) FROM cuotas_programa_estudiante 
WHERE estado = 'pagado' 
AND paid_at >= NOW() - INTERVAL 5 MINUTE;

-- Check created prospectos
SELECT COUNT(*) FROM prospectos 
WHERE created_at >= NOW() - INTERVAL 5 MINUTE;
```
- [ ] Payments inserted (count > 0)
- [ ] Quotas updated (count > 0 if matching quotas exist)
- [ ] Prospectos created for new students

### 5. Performance Monitoring

#### Query Performance
```sql
-- Check query execution time for carnet lookup
SET profiling = 1;
SELECT * FROM prospectos WHERE carnet = 'ASM123';
SHOW PROFILES;

-- Should be < 10ms

-- Check query execution time for quota matching
SELECT * FROM cuotas_programa_estudiante 
WHERE estudiante_programa_id = 1 
AND estado = 'pendiente';
SHOW PROFILES;

-- Should be < 5ms
```
- [ ] Carnet lookup < 10ms
- [ ] Quota matching < 5ms
- [ ] Duplicate check < 5ms

#### Import Performance
- [ ] 100 records imported in < 30 seconds
- [ ] 1000 records imported in < 5 minutes
- [ ] 4000 records imported in < 20 minutes
- [ ] No timeout errors

## Rollback Procedure (If Needed)

### If Something Goes Wrong
```bash
# Stop the application
php artisan down

# Rollback last 7 migrations
php artisan migrate:rollback --step=7

# Restore from backup
# For PostgreSQL
psql -U username -d database_name < backup_YYYYMMDD_HHMMSS.sql

# For MySQL
mysql -u username -p database_name < backup_YYYYMMDD_HHMMSS.sql

# Bring application back up
php artisan up
```
- [ ] Rollback procedure tested in staging
- [ ] Backup restore procedure verified

## Success Criteria

### All checks must pass:
- [x] All 7 migrations created and validated
- [ ] Database backup created and verified
- [ ] All migrations run successfully
- [ ] All schema changes verified in database
- [ ] All test data operations successful
- [ ] Performance improvements confirmed
- [ ] Import test successful with sample data
- [ ] No errors in application logs
- [ ] 4000 operations = 4000 inserts (not 0!)

## Sign-Off

### Development Team
- [ ] Migrations tested in development environment
- [ ] Code reviewed and approved
- [ ] Documentation complete and accurate

### QA Team
- [ ] Tested in staging environment
- [ ] Performance validated
- [ ] Import functionality verified
- [ ] Edge cases tested

### Operations Team
- [ ] Database backup procedure confirmed
- [ ] Rollback procedure tested
- [ ] Monitoring in place
- [ ] Ready for production deployment

### Final Approval
- [ ] Product Owner approval
- [ ] Technical Lead approval
- [ ] Operations approval

---

## Deployment Window

- **Estimated Duration:** 5-10 minutes
- **Recommended Time:** Low-traffic period (early morning or late night)
- **Required Downtime:** None (migrations run while app is live)
- **Rollback Time:** < 5 minutes if needed

---

## Emergency Contacts

- **Technical Lead:** [Name/Contact]
- **Database Admin:** [Name/Contact]
- **Operations On-Call:** [Name/Contact]

---

## Post-Deployment Monitoring (First 24 Hours)

- [ ] Monitor application logs for errors
- [ ] Monitor database performance metrics
- [ ] Monitor import success rate
- [ ] Monitor query execution times
- [ ] Verify no degradation in system performance

---

**Status:** Ready for Production Deployment ✅

**Date:** 2025-10-13

**Prepared by:** GitHub Copilot Agent

**Approved by:** [Awaiting approval]
