<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🔍 Verificando restricción de scope\n";
echo "===================================\n\n";

// Verificar la restricción CHECK en la tabla userpermissions
$constraints = DB::select("
    SELECT
        conname AS constraint_name,
        pg_get_constraintdef(c.oid) AS constraint_definition
    FROM pg_constraint c
    JOIN pg_namespace n ON n.oid = c.connamespace
    JOIN pg_class cl ON cl.oid = c.conrelid
    WHERE cl.relname = 'userpermissions'
    AND c.contype = 'c'
");

echo "📋 Restricciones CHECK en userpermissions:\n";
foreach ($constraints as $constraint) {
    echo "\n🔒 {$constraint->constraint_name}\n";
    echo "   {$constraint->constraint_definition}\n";
}

echo "\n\n📊 Valores de scope actualmente en uso:\n";
$scopeValues = DB::table('userpermissions')
    ->select('scope', DB::raw('COUNT(*) as count'))
    ->groupBy('scope')
    ->get();

foreach ($scopeValues as $sv) {
    echo "   - '{$sv->scope}': {$sv->count} registros\n";
}

echo "\n\n💡 Valores permitidos para scope:\n";
echo "   Basado en los datos existentes, los valores válidos son:\n";
foreach ($scopeValues as $sv) {
    echo "   - '{$sv->scope}'\n";
}
