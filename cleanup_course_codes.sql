-- ============================================
-- Script SQL: Limpieza de Códigos de Cursos
-- ============================================

-- 1. Ver cursos con códigos malformados (largos > 20 caracteres)
SELECT
    id,
    code,
    LENGTH(code) as code_length,
    LEFT(name, 60) as nombre,
    status,
    origen,
    created_at
FROM courses
WHERE LENGTH(code) > 20
ORDER BY LENGTH(code) DESC
LIMIT 50;

-- 2. Contar cursos por longitud de código
SELECT
    CASE
        WHEN LENGTH(code) <= 5 THEN '1-5 chars (OK)'
        WHEN LENGTH(code) <= 10 THEN '6-10 chars (OK)'
        WHEN LENGTH(code) <= 20 THEN '11-20 chars (Largo)'
        ELSE '20+ chars (MALFORMADO)'
    END as categoria,
    COUNT(*) as cantidad
FROM courses
GROUP BY
    CASE
        WHEN LENGTH(code) <= 5 THEN '1-5 chars (OK)'
        WHEN LENGTH(code) <= 10 THEN '6-10 chars (OK)'
        WHEN LENGTH(code) <= 20 THEN '11-20 chars (Largo)'
        ELSE '20+ chars (MALFORMADO)'
    END
ORDER BY
    CASE
        WHEN LENGTH(code) <= 5 THEN 1
        WHEN LENGTH(code) <= 10 THEN 2
        WHEN LENGTH(code) <= 20 THEN 3
        ELSE 4
    END;

-- 3. Ver ejemplos de códigos correctos vs malformados
(SELECT 'MALFORMADO' as tipo, code, name
 FROM courses
 WHERE LENGTH(code) > 20
 LIMIT 5)
UNION ALL
(SELECT 'CORRECTO' as tipo, code, name
 FROM courses
 WHERE LENGTH(code) <= 10
 LIMIT 5);

-- 4. Ver duplicados de códigos
SELECT
    code,
    COUNT(*) as cantidad,
    GROUP_CONCAT(id ORDER BY id) as course_ids
FROM courses
GROUP BY code
HAVING COUNT(*) > 1
ORDER BY cantidad DESC;

-- ============================================
-- COMANDOS DE LIMPIEZA (USAR CON PRECAUCIÓN)
-- ============================================

-- OPCIÓN 1: Ver qué se eliminaría (DRY RUN)
SELECT
    COUNT(*) as total_a_eliminar,
    SUM(CASE WHEN origen = 'moodle' THEN 1 ELSE 0 END) as desde_moodle,
    SUM(CASE WHEN origen IS NULL OR origen != 'moodle' THEN 1 ELSE 0 END) as creados_manual
FROM courses
WHERE LENGTH(code) > 20;

-- OPCIÓN 2: Eliminar SOLO cursos de Moodle con códigos malformados
-- ⚠️ DESCOMENTAR SOLO SI ESTÁS SEGURO
/*
DELETE FROM courses
WHERE LENGTH(code) > 20
AND (origen = 'moodle' OR origen IS NULL)
AND status = 'draft';
*/

-- OPCIÓN 3: Marcar como "pendiente de limpieza" en lugar de eliminar
/*
UPDATE courses
SET status = 'archived',
    code = CONCAT('OLD_', code)
WHERE LENGTH(code) > 20;
*/

-- 5. Verificar cursos después de limpieza
SELECT
    status,
    COUNT(*) as cantidad,
    AVG(LENGTH(code)) as promedio_longitud_codigo
FROM courses
GROUP BY status
ORDER BY status;

-- 6. Ver últimos cursos sincronizados desde Moodle
SELECT
    id,
    code,
    name,
    moodle_id,
    status,
    created_at
FROM courses
WHERE origen = 'moodle'
ORDER BY created_at DESC
LIMIT 20;

-- ============================================
-- VERIFICACIÓN POST-FIX
-- ============================================

-- Ver distribución de códigos después del fix
SELECT
    LEFT(code, 3) as prefijo,
    COUNT(*) as cantidad,
    GROUP_CONCAT(DISTINCT status) as estados
FROM courses
WHERE LENGTH(code) <= 10
GROUP BY LEFT(code, 3)
ORDER BY cantidad DESC;

-- Ver si hay cursos sin prefijo reconocido
SELECT id, code, name
FROM courses
WHERE code NOT REGEXP '^(MBA|BBA|MMK|EMBA|DBA|MSc|PhD)'
AND LENGTH(code) > 0
LIMIT 20;
