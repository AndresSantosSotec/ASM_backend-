-- ========================================
-- SCRIPT DE CORRECCIÓN DE CURSO ID 412
-- ========================================
--
-- Problema:
-- El curso con moodle_id 1494 se sincronizó con código MBA
-- cuando debería ser MHTM08
--
-- Causa:
-- En Moodle está nombrado como "MBA Gestión del Talento Humano..."
-- pero según el pensum debería ser "MHTM Liderazgo de Equipos..."
-- ========================================

-- 1️⃣ Ver el estado actual del curso
SELECT
    id,
    name,
    code,
    area,
    credits,
    moodle_id,
    status,
    created_at
FROM courses
WHERE id = 412 OR moodle_id = 1494;

-- Resultado esperado ANTES de la corrección:
-- id=412, code=MBA, area=common, credits=3

-- ========================================

-- 2️⃣ OPCIÓN A: Actualizar el curso existente
UPDATE courses
SET
    code = 'MHTM08',
    area = 'specialty',
    credits = 4,
    updated_at = NOW()
WHERE id = 412 AND moodle_id = 1494;

-- ========================================

-- 3️⃣ OPCIÓN B: Eliminar y re-sincronizar (RECOMENDADO)
-- Esto permitirá que el sistema aplique la lógica completa de mapeo

-- Paso 1: Eliminar el curso mal creado
DELETE FROM courses WHERE id = 412 AND moodle_id = 1494;

-- Paso 2: Re-sincronizar desde el frontend o API
-- POST /api/courses/bulk-sync-moodle
-- Body: [{"moodle_id": 1494, "fullname": "Noviembre Lunes 2025 MBA Gestión del Talento Humano y Liderazgo"}]

-- ========================================

-- 4️⃣ Verificar después de la corrección
SELECT
    id,
    name,
    code,
    area,
    credits,
    moodle_id,
    status,
    created_at
FROM courses
WHERE moodle_id = 1494
ORDER BY created_at DESC
LIMIT 1;

-- Resultado esperado DESPUÉS de la corrección:
-- code=MHTM08, area=specialty, credits=4

-- ========================================

-- 5️⃣ Buscar otros cursos con código genérico (posibles problemas)
SELECT
    id,
    name,
    code,
    area,
    credits,
    moodle_id,
    status
FROM courses
WHERE
    LENGTH(code) <= 4  -- Códigos muy cortos (MBA, BBA, etc.)
    AND code NOT LIKE '%-%'  -- Sin sufijo -1, -2
    AND status = 'draft'
ORDER BY created_at DESC;

-- ========================================

-- 6️⃣ Encontrar cursos duplicados por moodle_id
SELECT
    moodle_id,
    COUNT(*) as count,
    GROUP_CONCAT(id) as course_ids,
    GROUP_CONCAT(code) as codes
FROM courses
WHERE moodle_id IS NOT NULL
GROUP BY moodle_id
HAVING COUNT(*) > 1;

-- ========================================

-- 7️⃣ Ver todos los cursos MHTM
SELECT
    id,
    code,
    name,
    area,
    credits,
    moodle_id,
    status
FROM courses
WHERE code LIKE 'MHTM%'
ORDER BY code;

-- ========================================

-- 8️⃣ INFORMACIÓN ADICIONAL
--
-- Cursos mal nombrados en Moodle detectados:
--
-- 1. "MBA Gestión del Talento Humano y Liderazgo"
--    → Debería ser: "MHTM Liderazgo de Equipos..."
--    → Código correcto: MHTM08
--
-- 2. "MBA Gestión del Talento y Desarrollo Organizacional"
--    → Debería ser: "MHTM Gestión del Talento..."
--    → Código correcto: MHTM10
--
-- 3. "BBA Contabilidad Financiera" (ya está correcto)
--    → Código: BBA15
--
-- ========================================

-- 9️⃣ RECOMENDACIÓN
--
-- Corregir los nombres en Moodle:
-- 1. Ir a Moodle como administrador
-- 2. Buscar el curso con ID 1494
-- 3. Editar configuración
-- 4. Cambiar nombre completo de:
--    "Noviembre Lunes 2025 MBA Gestión del Talento Humano y Liderazgo"
--    a:
--    "Noviembre Lunes 2025 MHTM Liderazgo de Equipos de Alto Rendimiento y Cultura Organizacional"
-- 5. Guardar cambios
-- 6. Re-sincronizar desde Blue Atlas
--
-- ========================================
