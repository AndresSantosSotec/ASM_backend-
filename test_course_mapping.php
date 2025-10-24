<?php
/**
 * Script de prueba para verificar el mapeo de códigos de cursos
 *
 * USO:
 * php artisan tinker < test_course_mapping.php
 *
 * O copia y pega en tinker:
 * php artisan tinker
 */

echo "========================================\n";
echo "PRUEBA DE MAPEO DE CÓDIGOS DE CURSOS\n";
echo "========================================\n\n";

// Casos de prueba
$testCases = [
    [
        'name' => 'Noviembre Lunes 2025 MBA Gestión del Talento Humano y Liderazgo',
        'expected_code' => 'MHTM08',
        'expected_area' => 'specialty',
        'expected_credits' => 4,
    ],
    [
        'name' => 'Noviembre Lunes 2025 BBA Contabilidad Financiera',
        'expected_code' => 'BBA15',
        'expected_area' => 'common',
        'expected_credits' => 4,
    ],
    [
        'name' => 'Noviembre Lunes 2025 MBA Gestión de Crisis y Resiliencia',
        'expected_code' => 'MBA01',
        'expected_area' => 'specialty',
        'expected_credits' => 4,
    ],
    [
        'name' => 'Noviembre Lunes 2025 MHTM Liderazgo de Equipos de Alto Rendimiento y Cultura Organizacional',
        'expected_code' => 'MHTM08',
        'expected_area' => 'specialty',
        'expected_credits' => 4,
    ],
    [
        'name' => 'Noviembre Lunes 2025 BBA Contabilidad Aplicada',
        'expected_code' => 'BBA14',
        'expected_area' => 'common',
        'expected_credits' => 4,
    ],
];

echo "Casos de prueba:\n";
echo "================\n\n";

foreach ($testCases as $i => $test) {
    $num = $i + 1;
    echo "Test #{$num}: {$test['name']}\n";
    echo "  Código esperado: {$test['expected_code']}\n";
    echo "  Área esperada: {$test['expected_area']}\n";
    echo "  Créditos esperados: {$test['expected_credits']}\n";
    echo "\n";
}

echo "\n========================================\n";
echo "INSTRUCCIONES:\n";
echo "========================================\n\n";
echo "1. Para probar el curso mal nombrado en Moodle:\n";
echo "   - Buscar curso ID 412 (moodle_id 1494)\n";
echo "   - Verificar que ahora tenga:\n";
echo "     * code: MHTM08\n";
echo "     * area: specialty\n";
echo "     * credits: 4\n\n";

echo "2. Comandos útiles en Tinker:\n\n";

echo "// Ver el curso ID 412\n";
echo "\$course = Course::find(412);\n";
echo "echo \"Code: {\$course->code}\";\n";
echo "echo \"Area: {\$course->area}\";\n";
echo "echo \"Credits: {\$course->credits}\";\n\n";

echo "// Eliminar el curso mal creado y re-sincronizar\n";
echo "Course::where('moodle_id', 1494)->delete();\n\n";

echo "// Re-sincronizar desde el frontend o usar el endpoint:\n";
echo "// POST /api/courses/bulk-sync-moodle\n";
echo "// Body: [{\"moodle_id\": 1494, \"fullname\": \"Noviembre Lunes 2025 MBA Gestión del Talento Humano y Liderazgo\"}]\n\n";

echo "3. Verificar en la base de datos:\n\n";
echo "SELECT id, name, code, area, credits, moodle_id\n";
echo "FROM courses\n";
echo "WHERE moodle_id = 1494;\n\n";

echo "========================================\n";
echo "CURSOS MAL NOMBRADOS EN MOODLE\n";
echo "========================================\n\n";

echo "Los siguientes cursos tienen nombres incorrectos en Moodle:\n";
echo "(tienen prefijo MBA pero deberían ser MHTM o BBA)\n\n";

$wrongNames = [
    ['wrong' => 'MBA Gestión del Talento Humano y Liderazgo', 'correct' => 'MHTM Liderazgo de Equipos de Alto Rendimiento...', 'code' => 'MHTM08'],
    ['wrong' => 'MBA Gestión del Talento y Desarrollo Organizacional', 'correct' => 'MHTM Gestión del Talento y Desarrollo Organizacional', 'code' => 'MHTM10'],
    ['wrong' => 'BBA Contabilidad Financiera', 'correct' => 'BBA Contabilidad Financiera', 'code' => 'BBA15'],
];

foreach ($wrongNames as $i => $item) {
    $num = $i + 1;
    echo "{$num}. INCORRECTO: {$item['wrong']}\n";
    echo "   CORRECTO:   {$item['correct']}\n";
    echo "   CÓDIGO:     {$item['code']}\n\n";
}

echo "SOLUCIÓN:\n";
echo "1. TEMPORAL: El sistema ahora detecta estos nombres y los mapea correctamente\n";
echo "2. PERMANENTE: Corregir los nombres en Moodle para que coincidan con el pensum\n\n";

echo "========================================\n";
echo "FIN DEL SCRIPT\n";
echo "========================================\n";
