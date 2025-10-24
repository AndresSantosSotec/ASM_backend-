#!/usr/bin/env php
<?php

/**
 * Script para limpiar cursos con códigos malformados y resincronizar
 *
 * Uso: php artisan tinker < fix_course_codes.php
 * O ejecutar directamente en Tinker
 */

echo "🔧 Iniciando limpieza de códigos de cursos...\n\n";

// 1. Listar cursos con códigos problemáticos (largos)
echo "📊 Cursos con códigos malformados (> 20 caracteres):\n";
$badCourses = \App\Models\Course::whereRaw('LENGTH(code) > 20')->get();

echo "Total encontrados: " . $badCourses->count() . "\n\n";

foreach ($badCourses as $course) {
    echo sprintf(
        "ID: %d | Código: %s | Nombre: %s\n",
        $course->id,
        $course->code,
        substr($course->name, 0, 50) . '...'
    );
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "⚠️  ADVERTENCIA: El siguiente comando eliminará estos cursos.\n";
echo "Si solo quieres ver la lista, NO ejecutes los comandos siguientes.\n";
echo str_repeat("=", 80) . "\n\n";

// DESCOMENTAR SOLO SI QUIERES EJECUTAR LA LIMPIEZA
/*
echo "🗑️  Eliminando cursos con códigos malformados...\n";
$deleted = \App\Models\Course::whereRaw('LENGTH(code) > 20')
    ->where('origen', 'moodle')
    ->delete();
echo "✅ Eliminados: $deleted cursos\n\n";

// 2. Resincronizar desde Moodle
echo "🔄 Para resincronizar, usa la API:\n";
echo "POST /api/moodle/test/sync-all\n";
echo "O importa manualmente desde el frontend\n\n";
*/

echo "✅ Análisis completado.\n";
echo "Para ejecutar la limpieza, descomenta las líneas en el script.\n";
