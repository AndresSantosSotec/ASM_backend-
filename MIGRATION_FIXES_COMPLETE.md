# Migration Fixes for Payment Import System

## Overview
This document describes the migrations created to ensure all required fields and indexes exist for the payment import system to work correctly in production.

## Problem Statement
The production environment was showing "4000 operations" but no data was being inserted. This was likely due to:
1. Missing required fields in tables
2. Missing indexes causing slow queries and timeouts
3. Non-nullable fields that should be nullable for incomplete data during import

## Migrations Created

### 1. Add `fecha_recibo` to `kardex_pagos` table
**File:** `2025_10_13_000001_add_fecha_recibo_to_kardex_pagos_table.php`

**Purpose:** The `KardexPago` model expects a `fecha_recibo` field (cast as date), but it was never added in previous migrations (only mentioned in a comment).

**Fields Added:**
- `fecha_recibo` (date, nullable) - Receipt date for payment records

### 2. Add Audit Fields to `cuotas_programa_estudiante` table
**File:** `2025_10_13_000002_add_audit_fields_to_cuotas_programa_estudiante_table.php`

**Purpose:** The `CuotaProgramaEstudiante` model expects audit fields (`created_by`, `updated_by`, `deleted_by`) but they were missing from the table definition.

**Fields Added:**
- `created_by` (unsignedBigInteger, nullable)
- `updated_by` (unsignedBigInteger, nullable)
- `deleted_by` (unsignedBigInteger, nullable)

### 3. Make `telefono` and `correo_electronico` Nullable in `prospectos` table
**File:** `2025_10_13_000003_make_prospectos_fields_nullable.php`

**Purpose:** The payment import may create prospectos with incomplete contact information. The current schema requires these fields, which can cause insert failures.

**Fields Modified:**
- `telefono` - changed to nullable
- `correo_electronico` - changed to nullable

### 4. Add Indexes to `kardex_pagos` table
**File:** `2025_10_13_000004_add_indexes_to_kardex_pagos_table.php`

**Purpose:** Improve query performance for payment lookups and duplicate detection.

**Indexes Added:**
- `kardex_pagos_estudiante_programa_id_index` - Single column index on `estudiante_programa_id`
- `kardex_pagos_boleta_student_index` - Composite index on `(numero_boleta_normalizada, estudiante_programa_id)` for duplicate detection

### 5. Add Indexes to `cuotas_programa_estudiante` table
**File:** `2025_10_13_000005_add_indexes_to_cuotas_programa_estudiante_table.php`

**Purpose:** Improve query performance for finding pending quotas during payment matching.

**Indexes Added:**
- `cuotas_estudiante_programa_id_index` - Single column index on `estudiante_programa_id`
- `cuotas_estado_fecha_index` - Composite index on `(estudiante_programa_id, estado, fecha_vencimiento)` for finding pending quotas

### 6. Add Index to `prospectos` table
**File:** `2025_10_13_000006_add_index_to_prospectos_carnet.php`

**Purpose:** Improve student lookup performance during import when searching by carnet.

**Indexes Added:**
- `prospectos_carnet_index` - Single column index on `carnet`

### 7. Add Indexes to `estudiante_programa` table
**File:** `2025_10_13_000007_add_indexes_to_estudiante_programa_table.php`

**Purpose:** Improve query performance for relationships and lookups.

**Indexes Added:**
- `estudiante_programa_prospecto_id_index` - Single column index on `prospecto_id`
- `estudiante_programa_programa_id_index` - Single column index on `programa_id`

## Migration Safety Features

All migrations include:
1. **Existence checks** - Check if columns/indexes exist before adding them
2. **Doctrine Schema Manager** - Used for checking index existence
3. **Rollback support** - All migrations can be rolled back safely

## How to Run These Migrations

```bash
# Run all pending migrations
php artisan migrate

# Check migration status
php artisan migrate:status

# Rollback if needed (not recommended in production)
php artisan migrate:rollback --step=7
```

## Expected Impact

### Performance Improvements
- **Faster student lookups** - Index on `prospectos.carnet`
- **Faster payment duplicate detection** - Composite index on kardex_pagos
- **Faster quota matching** - Composite index on cuotas with estado and fecha
- **Better relationship queries** - Indexes on foreign keys

### Data Integrity
- **Allows incomplete prospect data** - Nullable phone and email fields
- **Proper audit trails** - Audit fields in cuotas table
- **Complete payment records** - fecha_recibo field for kardex_pagos

### Import Success Rate
These migrations should resolve the issue where 4000 operations were attempted but nothing was inserted. The likely causes were:
1. Missing required fields causing constraint violations
2. Slow queries without indexes causing timeouts
3. Non-nullable fields rejecting incomplete data

## Verification Steps

After running migrations, verify the schema:

```sql
-- Check kardex_pagos structure
DESCRIBE kardex_pagos;
SHOW INDEXES FROM kardex_pagos;

-- Check cuotas_programa_estudiante structure
DESCRIBE cuotas_programa_estudiante;
SHOW INDEXES FROM cuotas_programa_estudiante;

-- Check prospectos structure
DESCRIBE prospectos;
SHOW INDEXES FROM prospectos;

-- Check estudiante_programa structure
DESCRIBE estudiante_programa;
SHOW INDEXES FROM estudiante_programa;
```

## Related Files

These migrations support the following components:
- `app/Models/KardexPago.php` - Payment records model
- `app/Models/CuotaProgramaEstudiante.php` - Installment quota model
- `app/Models/Prospecto.php` - Student prospect model
- `app/Models/EstudiantePrograma.php` - Student program enrollment model
- `app/Imports/PaymentHistoryImport.php` - Payment import processor
- `app/Services/EstudianteService.php` - Student service

## Summary of All Tables and Required Fields

### ✅ `prospectos`
- id (PK) ✓
- carnet (string, now indexed) ✓
- nombre_completo (string) ✓
- correo_electronico (string, now nullable) ✓
- telefono (string, now nullable) ✓
- fecha (date) ✓
- activo (boolean) ✓
- created_by, updated_by, deleted_by (exists via separate migration) ✓

### ✅ `estudiante_programa`
- id (PK) ✓
- prospecto_id (FK, now indexed) ✓
- programa_id (FK, now indexed) ✓
- convenio_id (nullable) ✓
- fecha_inicio (date) ✓
- fecha_fin (date) ✓
- duracion_meses (integer) ✓
- inscripcion (decimal) ✓
- cuota_mensual (decimal) ✓
- inversion_total (decimal) ✓
- created_by, updated_by, deleted_by ✓

### ✅ `cuotas_programa_estudiante`
- id (PK) ✓
- estudiante_programa_id (FK, now indexed) ✓
- numero_cuota (integer) ✓
- fecha_vencimiento (date) ✓
- monto (decimal) ✓
- estado (string) ✓
- paid_at (datetime, nullable) ✓
- created_by, updated_by, deleted_by (now added) ✓

### ✅ `kardex_pagos`
- id (PK) ✓
- estudiante_programa_id (FK, now indexed) ✓
- cuota_id (FK, nullable) ✓
- numero_boleta (string) ✓
- numero_boleta_normalizada (string, indexed) ✓
- monto_pagado (decimal) ✓
- fecha_pago (datetime) ✓
- fecha_recibo (date, now added) ✓
- banco (string) ✓
- banco_normalizado (string) ✓
- metodo_pago (string, nullable) ✓
- estado_pago (string) ✓
- observaciones (text, nullable) ✓
- boleta_fingerprint (string, unique) ✓
- archivo_comprobante (string, nullable) ✓
- archivo_hash (string, nullable) ✓
- uploaded_by, created_by, updated_by ✓

### ✅ `tb_precios_programa`
- id (PK) ✓
- programa_id (FK) ✓
- inscripcion (decimal) ✓
- cuota_mensual (decimal) ✓
- meses (integer) ✓

### ✅ `reconciliation_records`
- id (PK) ✓
- prospecto_id (FK, nullable) ✓
- bank (string) ✓
- bank_normalized (string) ✓
- reference (string) ✓
- reference_normalized (string) ✓
- amount (decimal) ✓
- date (date) ✓
- auth_number (string, nullable) ✓
- status (string) ✓
- fingerprint (string, unique) ✓
- kardex_pago_id (FK, nullable, indexed) ✓
- uploaded_by (FK) ✓

### ✅ `tb_programas`
- id (PK) ✓
- nombre_del_programa (string) ✓
- abreviatura (string) ✓
- activo (boolean) ✓

## All Required Fields Status: ✅ COMPLETE

All tables now have the required fields and indexes for the payment import system to function properly in production.
