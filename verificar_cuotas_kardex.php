<?php

use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n🔍 ANÁLISIS DE CUOTAS vs KARDEX\n";
echo "================================\n\n";

// Total de registros
$totalKardex = DB::table('kardex_pagos')->count();
$totalCuotas = DB::table('cuotas_programa_estudiante')->count();
$kardexSinCuota = DB::table('kardex_pagos')->whereNull('cuota_id')->count();
$cuotasDistintas = DB::table('kardex_pagos')->distinct()->whereNotNull('cuota_id')->count('cuota_id');

echo "📊 RESUMEN:\n";
echo "Total Kardex: {$totalKardex}\n";
echo "Total Cuotas: {$totalCuotas}\n";
echo "Kardex sin cuota: {$kardexSinCuota}\n";
echo "Cuotas distintas en kardex: {$cuotasDistintas}\n\n";

// Cuotas con múltiples kardex
echo "🔢 CUOTAS CON MÚLTIPLES KARDEX (TOP 20):\n";
$cuotasMultiples = DB::table('kardex_pagos')
    ->selectRaw('cuota_id, COUNT(*) as cantidad')
    ->whereNotNull('cuota_id')
    ->groupBy('cuota_id')
    ->havingRaw('COUNT(*) > 1')
    ->orderByDesc('cantidad')
    ->limit(20)
    ->get();

foreach ($cuotasMultiples as $item) {
    echo "  Cuota #{$item->cuota_id}: {$item->cantidad} kardex\n";
}

echo "\n📈 ESTADÍSTICAS:\n";
$promedio = DB::table('kardex_pagos')
    ->selectRaw('AVG(cnt) as promedio')
    ->fromSub(function ($query) {
        $query->selectRaw('cuota_id, COUNT(*) as cnt')
            ->from('kardex_pagos')
            ->whereNotNull('cuota_id')
            ->groupBy('cuota_id');
    }, 'subquery')
    ->value('promedio');

echo "Promedio de kardex por cuota: " . number_format($promedio, 2) . "\n";
echo "Relación: {$totalKardex} kardex / {$cuotasDistintas} cuotas = " . number_format($totalKardex / $cuotasDistintas, 2) . " kardex por cuota\n\n";

// Verificar si hay múltiples estudiantes en la misma cuota
echo "🔍 ANÁLISIS: ¿MÚLTIPLES ESTUDIANTES EN LA MISMA CUOTA?:\n";
$cuotasCompartidas = DB::select("
    SELECT
        c.id as cuota_id,
        c.estudiante_programa_id as cuota_estudiante,
        COUNT(DISTINCT k.estudiante_programa_id) as estudiantes_diferentes,
        COUNT(k.id) as total_kardex
    FROM cuotas_programa_estudiante c
    JOIN kardex_pagos k ON k.cuota_id = c.id
    GROUP BY c.id, c.estudiante_programa_id
    HAVING COUNT(DISTINCT k.estudiante_programa_id) > 1
    LIMIT 20
");

if (count($cuotasCompartidas) > 0) {
    echo "  ⚠️ ¡PROBLEMA ENCONTRADO! Cuotas compartidas por múltiples estudiantes:\n";
    foreach ($cuotasCompartidas as $cuota) {
        echo "    Cuota #{$cuota->cuota_id} (estudiante {$cuota->cuota_estudiante}): {$cuota->estudiantes_diferentes} estudiantes diferentes, {$cuota->total_kardex} kardex\n";
    }
} else {
    echo "  ✅ Todas las cuotas pertenecen a un solo estudiante\n";
    echo "  ℹ️  El problema es que un mismo estudiante tiene múltiples pagos asignados a una sola cuota\n";
}echo "\n================================\n";
