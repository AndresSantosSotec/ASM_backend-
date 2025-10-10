<?php

/**
 * Validación Manual: Query de Distribución de Programas
 * 
 * Este script muestra cómo validar manualmente que el query corregido
 * funciona correctamente con datos reales.
 */

echo "═══════════════════════════════════════════════════════════════\n";
echo "   GUÍA DE VALIDACIÓN MANUAL - Distribución de Programas\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

echo "📋 PASOS PARA VALIDAR LA CORRECCIÓN:\n\n";

echo "1️⃣  VERIFICAR DATOS EN BASE DE DATOS\n";
echo "-----------------------------------\n";
echo "Ejecutar estos queries en PostgreSQL para verificar el estado actual:\n\n";

echo "-- a) Contar estudiantes ACTIVOS por programa (sin soft deletes)\n";
echo "SELECT \n";
echo "    p.nombre_del_programa,\n";
echo "    p.abreviatura,\n";
echo "    COUNT(DISTINCT ep.id) as total_estudiantes\n";
echo "FROM tb_programas p\n";
echo "LEFT JOIN estudiante_programa ep ON p.id = ep.programa_id AND ep.deleted_at IS NULL\n";
echo "WHERE p.activo = 1\n";
echo "GROUP BY p.id, p.nombre_del_programa, p.abreviatura\n";
echo "ORDER BY total_estudiantes DESC;\n\n";

echo "-- b) Verificar si hay estudiantes con soft delete\n";
echo "SELECT COUNT(*) as estudiantes_eliminados\n";
echo "FROM estudiante_programa\n";
echo "WHERE deleted_at IS NOT NULL;\n\n";

echo "-- c) Verificar programas activos vs inactivos\n";
echo "SELECT activo, COUNT(*) as total\n";
echo "FROM tb_programas\n";
echo "GROUP BY activo;\n\n";

echo "2️⃣  PROBAR EL ENDPOINT\n";
echo "----------------------\n";
echo "Usar curl o Postman para probar el endpoint:\n\n";

echo "curl -X GET 'http://localhost:8000/api/administracion/dashboard' \\\n";
echo "  -H 'Authorization: Bearer {YOUR_TOKEN}' \\\n";
echo "  -H 'Accept: application/json' | jq '.distribucionProgramas'\n\n";

echo "3️⃣  VALIDAR LA RESPUESTA\n";
echo "------------------------\n";
echo "La respuesta debe tener esta estructura:\n\n";

$expectedResponse = [
    [
        'programa' => 'Nombre del Programa',
        'abreviatura' => 'ABREV',
        'totalEstudiantes' => 25
    ]
];

echo json_encode($expectedResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "4️⃣  CASOS A VERIFICAR\n";
echo "---------------------\n\n";

echo "✅ Caso 1: Estudiante activo (deleted_at = NULL)\n";
echo "   - Debe aparecer en el conteo\n";
echo "   - Query: SELECT * FROM estudiante_programa WHERE deleted_at IS NULL LIMIT 5;\n\n";

echo "❌ Caso 2: Estudiante eliminado (deleted_at != NULL)\n";
echo "   - NO debe aparecer en el conteo\n";
echo "   - Query: SELECT * FROM estudiante_programa WHERE deleted_at IS NOT NULL LIMIT 5;\n\n";

echo "✅ Caso 3: Programa activo (activo = 1)\n";
echo "   - Debe aparecer en la lista\n";
echo "   - Query: SELECT * FROM tb_programas WHERE activo = 1;\n\n";

echo "❌ Caso 4: Programa inactivo (activo = 0)\n";
echo "   - NO debe aparecer en la lista\n";
echo "   - Query: SELECT * FROM tb_programas WHERE activo = 0;\n\n";

echo "5️⃣  COMPARAR CONTEOS\n";
echo "--------------------\n";
echo "Comparar el conteo del endpoint con el query directo:\n\n";

echo "-- Query directo (lo que el endpoint debería devolver)\n";
echo "SELECT \n";
echo "    p.nombre_del_programa,\n";
echo "    COUNT(DISTINCT ep.id) as estudiantes\n";
echo "FROM tb_programas p\n";
echo "LEFT JOIN estudiante_programa ep \n";
echo "    ON p.id = ep.programa_id \n";
echo "    AND ep.deleted_at IS NULL\n";
echo "WHERE p.activo = 1\n";
echo "GROUP BY p.id, p.nombre_del_programa\n";
echo "ORDER BY estudiantes DESC;\n\n";

echo "6️⃣  PROBAR CASOS ESPECÍFICOS\n";
echo "----------------------------\n\n";

echo "-- a) Crear un estudiante de prueba\n";
echo "INSERT INTO estudiante_programa (prospecto_id, programa_id, fecha_inicio, fecha_fin, duracion_meses, inscripcion, cuota_mensual, inversion_total)\n";
echo "VALUES (1, 1, CURRENT_DATE, CURRENT_DATE + INTERVAL '12 months', 12, 100.00, 50.00, 600.00);\n\n";

echo "-- b) Verificar que aparece en el endpoint\n";
echo "-- Llamar al endpoint y verificar que el conteo aumentó\n\n";

echo "-- c) Eliminar el estudiante (soft delete)\n";
echo "UPDATE estudiante_programa SET deleted_at = CURRENT_TIMESTAMP WHERE id = (SELECT MAX(id) FROM estudiante_programa);\n\n";

echo "-- d) Verificar que NO aparece en el endpoint\n";
echo "-- Llamar al endpoint y verificar que el conteo disminuyó\n\n";

echo "7️⃣  RESULTADO ESPERADO\n";
echo "----------------------\n\n";

echo "Si todo funciona correctamente:\n";
echo "✅ Programas activos aparecen en la lista\n";
echo "✅ Solo estudiantes sin soft delete se cuentan\n";
echo "✅ Conteos coinciden con query directo en base de datos\n";
echo "✅ Respuesta tiene formato correcto (programa, abreviatura, totalEstudiantes)\n";
echo "✅ Programas sin estudiantes muestran totalEstudiantes = 0\n\n";

echo "═══════════════════════════════════════════════════════════════\n";
echo "   QUERIES ÚTILES ADICIONALES\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

echo "-- Ver distribución completa con detalles\n";
echo "SELECT \n";
echo "    p.id,\n";
echo "    p.nombre_del_programa,\n";
echo "    p.abreviatura,\n";
echo "    p.activo,\n";
echo "    COUNT(DISTINCT CASE WHEN ep.deleted_at IS NULL THEN ep.id END) as estudiantes_activos,\n";
echo "    COUNT(DISTINCT CASE WHEN ep.deleted_at IS NOT NULL THEN ep.id END) as estudiantes_eliminados,\n";
echo "    COUNT(DISTINCT ep.id) as total_registros\n";
echo "FROM tb_programas p\n";
echo "LEFT JOIN estudiante_programa ep ON p.id = ep.programa_id\n";
echo "GROUP BY p.id, p.nombre_del_programa, p.abreviatura, p.activo\n";
echo "ORDER BY estudiantes_activos DESC;\n\n";

echo "-- Ver estudiantes por programa con estado\n";
echo "SELECT \n";
echo "    p.nombre_del_programa,\n";
echo "    pr.nombre_completo,\n";
echo "    ep.fecha_inicio,\n";
echo "    CASE WHEN ep.deleted_at IS NULL THEN 'Activo' ELSE 'Eliminado' END as estado\n";
echo "FROM estudiante_programa ep\n";
echo "JOIN tb_programas p ON ep.programa_id = p.id\n";
echo "JOIN prospectos pr ON ep.prospecto_id = pr.id\n";
echo "ORDER BY p.nombre_del_programa, estado;\n\n";

echo "═══════════════════════════════════════════════════════════════\n";
echo "   CHECKLIST DE VALIDACIÓN\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

echo "□ Query directo en base de datos ejecutado correctamente\n";
echo "□ Endpoint /api/administracion/dashboard responde sin errores\n";
echo "□ Respuesta tiene estructura correcta\n";
echo "□ Conteos coinciden con query directo\n";
echo "□ Estudiantes eliminados NO se cuentan\n";
echo "□ Programas inactivos NO aparecen\n";
echo "□ Programas sin estudiantes muestran 0\n";
echo "□ Frontend puede consumir los datos correctamente\n\n";

echo "═══════════════════════════════════════════════════════════════\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n";
echo "═══════════════════════════════════════════════════════════════\n";
