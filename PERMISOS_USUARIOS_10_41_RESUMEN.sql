-- ============================================
-- RESUMEN: PERMISOS ASIGNADOS A USUARIOS 10 Y 41
-- ============================================
-- Fecha: 2025-10-24
-- Usuarios: 10 (PabloAdmin), 41 (i.american)
-- Total permisos: 62 moduleviews cada uno
-- Scope: 'self'
-- ============================================

-- VERIFICAR usuarios
SELECT
    id,
    username,
    email,
    first_name,
    last_name
FROM users
WHERE id IN (10, 41);

-- CONTAR permisos por usuario
SELECT
    u.id as user_id,
    u.username,
    COUNT(up.id) as total_permisos
FROM users u
LEFT JOIN userpermissions up ON up.user_id = u.id
WHERE u.id IN (10, 41)
GROUP BY u.id, u.username
ORDER BY u.id;

-- VER DETALLE de permisos con nombres legibles
SELECT
    u.id as user_id,
    u.username,
    m.name as modulo,
    mv.menu,
    mv.submenu,
    mv.view_path,
    up.scope
FROM userpermissions up
JOIN users u ON u.id = up.user_id
JOIN moduleviews mv ON mv.id = up.moduleview_id
JOIN modules m ON m.id = mv.module_id
WHERE u.id IN (10, 41)
ORDER BY u.id, mv.order_num;

-- RESUMEN por módulo
SELECT
    u.username,
    m.name as modulo,
    COUNT(*) as vistas_asignadas
FROM userpermissions up
JOIN users u ON u.id = up.user_id
JOIN moduleviews mv ON mv.id = up.moduleview_id
JOIN modules m ON m.id = mv.module_id
WHERE u.id IN (10, 41)
GROUP BY u.username, m.name
ORDER BY u.username, vistas_asignadas DESC;

-- ============================================
-- NOTAS IMPORTANTES:
-- ============================================
-- 1. Scope válidos: 'global', 'group', 'self'
--    ❌ NO usar 'full' - causa error de constraint
--
-- 2. Para asignar más permisos:
--    INSERT INTO userpermissions (user_id, moduleview_id, assigned_at, scope)
--    VALUES (USER_ID, MODULEVIEW_ID, NOW(), 'self');
--
-- 3. Para quitar permisos:
--    DELETE FROM userpermissions
--    WHERE user_id = USER_ID AND moduleview_id = MODULEVIEW_ID;
--
-- 4. Para cambiar scope:
--    UPDATE userpermissions
--    SET scope = 'global'  -- o 'group' o 'self'
--    WHERE user_id = USER_ID;
-- ============================================
