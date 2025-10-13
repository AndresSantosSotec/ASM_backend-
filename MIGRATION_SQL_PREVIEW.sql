-- SQL Preview of Migration Changes
-- This file shows the SQL commands that will be executed by the migrations
-- DO NOT run this file directly - use: php artisan migrate

-- ============================================
-- Migration 1: Add fecha_recibo to kardex_pagos
-- ============================================
ALTER TABLE kardex_pagos 
ADD COLUMN fecha_recibo DATE NULL 
AFTER fecha_pago;

-- ============================================
-- Migration 2: Add audit fields to cuotas_programa_estudiante
-- ============================================
ALTER TABLE cuotas_programa_estudiante
ADD COLUMN created_by BIGINT UNSIGNED NULL AFTER paid_at,
ADD COLUMN updated_by BIGINT UNSIGNED NULL AFTER created_by,
ADD COLUMN deleted_by BIGINT UNSIGNED NULL AFTER updated_by;

-- ============================================
-- Migration 3: Make prospectos fields nullable
-- ============================================
ALTER TABLE prospectos
MODIFY COLUMN telefono VARCHAR(255) NULL,
MODIFY COLUMN correo_electronico VARCHAR(255) NULL;

-- ============================================
-- Migration 4: Add indexes to kardex_pagos
-- ============================================
-- Single column index on estudiante_programa_id
CREATE INDEX kardex_pagos_estudiante_programa_id_index 
ON kardex_pagos (estudiante_programa_id);

-- Composite index for duplicate detection
CREATE INDEX kardex_pagos_boleta_student_index 
ON kardex_pagos (numero_boleta_normalizada, estudiante_programa_id);

-- ============================================
-- Migration 5: Add indexes to cuotas_programa_estudiante
-- ============================================
-- Single column index on estudiante_programa_id
CREATE INDEX cuotas_estudiante_programa_id_index 
ON cuotas_programa_estudiante (estudiante_programa_id);

-- Composite index for finding pending quotas
CREATE INDEX cuotas_estado_fecha_index 
ON cuotas_programa_estudiante (estudiante_programa_id, estado, fecha_vencimiento);

-- ============================================
-- Migration 6: Add index to prospectos
-- ============================================
-- Index on carnet for faster student lookups
CREATE INDEX prospectos_carnet_index 
ON prospectos (carnet);

-- ============================================
-- Migration 7: Add indexes to estudiante_programa
-- ============================================
-- Index on prospecto_id
CREATE INDEX estudiante_programa_prospecto_id_index 
ON estudiante_programa (prospecto_id);

-- Index on programa_id
CREATE INDEX estudiante_programa_programa_id_index 
ON estudiante_programa (programa_id);

-- ============================================
-- Expected Performance Improvements
-- ============================================
-- These indexes will significantly improve:
-- 1. Payment duplicate detection queries (kardex_pagos indexes)
-- 2. Quota matching queries (cuotas indexes)
-- 3. Student lookup by carnet (prospectos index)
-- 4. Relationship queries (estudiante_programa indexes)

-- ============================================
-- Data Integrity Improvements
-- ============================================
-- 1. fecha_recibo field allows proper tracking of receipt dates
-- 2. Audit fields in cuotas enable proper tracking of who created/updated quotas
-- 3. Nullable phone/email in prospectos allows import with incomplete data
