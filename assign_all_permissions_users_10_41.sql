-- ============================================
-- ASIGNAR TODOS LOS PERMISOS A USUARIOS 10 Y 41
-- ============================================
-- Fecha: 2025-10-24
-- Descripción: Inserta todos los moduleviews como permisos para usuarios específicos
-- Scope: 'self' (el único valor válido actualmente)
-- ============================================

BEGIN;

-- 1. LIMPIAR permisos existentes de estos usuarios (opcional)
DELETE FROM userpermissions WHERE user_id IN (10, 41);

-- 2. INSERTAR todos los moduleviews para USUARIO 10
INSERT INTO userpermissions (user_id, moduleview_id, assigned_at, scope)
SELECT
    10 AS user_id,
    id AS moduleview_id,
    NOW() AS assigned_at,
    'self' AS scope
FROM moduleviews
WHERE status = true  -- Solo vistas activas
ON CONFLICT DO NOTHING;

-- 3. INSERTAR todos los moduleviews para USUARIO 41
INSERT INTO userpermissions (user_id, moduleview_id, assigned_at, scope)
SELECT
    41 AS user_id,
    id AS moduleview_id,
    NOW() AS assigned_at,
    'self' AS scope
FROM moduleviews
WHERE status = true  -- Solo vistas activas
ON CONFLICT DO NOTHING;

COMMIT;

-- 4. VERIFICAR resultados
SELECT
    user_id,
    COUNT(*) as total_permisos
FROM userpermissions
WHERE user_id IN (10, 41)
GROUP BY user_id
ORDER BY user_id;

-- 5. VER detalle de permisos asignados
SELECT
    up.user_id,
    u.username,
    mv.menu,
    mv.submenu,
    mv.view_path,
    up.scope
FROM userpermissions up
JOIN users u ON u.id = up.user_id
JOIN moduleviews mv ON mv.id = up.moduleview_id
WHERE up.user_id IN (10, 41)
ORDER BY up.user_id, mv.order_num;
