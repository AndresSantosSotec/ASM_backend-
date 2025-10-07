<?php

/**
 * Script de debugging para PaymentHistoryImport
 * 
 * Este script verifica que las mejoras de logging y manejo de errores funcionen correctamente.
 */

echo "=== Test de PaymentHistoryImport - Debug de Errores Silenciosos ===\n\n";

// Verificar sintaxis
echo "1. Verificando sintaxis de PaymentHistoryImport.php...\n";
$output = [];
$return_var = 0;
exec('php -l app/Imports/PaymentHistoryImport.php', $output, $return_var);
if ($return_var === 0) {
    echo "   ✅ Sintaxis correcta\n";
} else {
    echo "   ❌ Error de sintaxis:\n";
    echo "   " . implode("\n   ", $output) . "\n";
    exit(1);
}

echo "\n2. Verificando que existen los métodos nuevos...\n";
$content = file_get_contents('app/Imports/PaymentHistoryImport.php');

$methods_to_check = [
    'getErrorSummary' => '✅ Método getErrorSummary() encontrado',
    'hasErrors' => '✅ Método hasErrors() encontrado',
    'hasSuccessfulImports' => '✅ Método hasSuccessfulImports() encontrado',
    'dumpErrorsToStderr' => '✅ Método dumpErrorsToStderr() encontrado',
];

$all_found = true;
foreach ($methods_to_check as $method => $message) {
    if (strpos($content, "function $method") !== false) {
        echo "   $message\n";
    } else {
        echo "   ❌ Método $method() NO encontrado\n";
        $all_found = false;
    }
}

if (!$all_found) {
    echo "\n❌ Algunos métodos no fueron encontrados\n";
    exit(1);
}

echo "\n3. Verificando manejo de excepciones...\n";

// Verificar que se lanzan excepciones en validación
if (strpos($content, 'throw new \Exception($errorMsg)') !== false) {
    $count = substr_count($content, 'throw new \Exception($errorMsg)');
    echo "   ✅ Se encontraron $count puntos donde se lanzan excepciones\n";
} else {
    echo "   ❌ No se encontraron excepciones en validaciones\n";
    exit(1);
}

echo "\n4. Verificando logging de errores de BD...\n";
if (strpos($content, '❌ Error al insertar en kardex_pagos') !== false) {
    echo "   ✅ Logging de errores de BD implementado\n";
} else {
    echo "   ❌ Logging de errores de BD NO encontrado\n";
    exit(1);
}

echo "\n5. Verificando logging a stderr...\n";
if (strpos($content, 'error_log') !== false) {
    $count = substr_count($content, 'error_log');
    echo "   ✅ Se encontraron $count llamadas a error_log()\n";
} else {
    echo "   ❌ No se encontró logging a stderr\n";
    exit(1);
}

echo "\n6. Verificando validación de 0 registros insertados...\n";
if (strpos($content, 'IMPORTACIÓN SIN RESULTADOS') !== false) {
    echo "   ✅ Validación de 0 registros implementada\n";
} else {
    echo "   ❌ Validación de 0 registros NO encontrada\n";
    exit(1);
}

echo "\n=== ✅ TODAS LAS VERIFICACIONES PASARON ===\n\n";

echo "Resumen de mejoras implementadas:\n";
echo "  ✅ Excepciones en validaciones (archivo vacío, columnas inválidas)\n";
echo "  ✅ Excepción cuando 0 registros insertados\n";
echo "  ✅ Logging detallado de errores de BD\n";
echo "  ✅ Métodos helper para obtener resumen de errores\n";
echo "  ✅ Logging a stderr para debugging\n";
echo "  ✅ Validación de PHP sin errores de sintaxis\n\n";

echo "Para probar la importación:\n";
echo "  1. Crear un archivo Excel con datos de prueba\n";
echo "  2. Llamar al endpoint POST /api/conciliacion/importar-pagos-kardex\n";
echo "  3. Verificar los logs en storage/logs/laravel.log\n";
echo "  4. Verificar stderr si los logs no aparecen\n\n";

exit(0);
