<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "📋 Estructura de la tabla permissions:\n";
echo "=====================================\n\n";

$columns = DB::select("SELECT column_name, data_type, is_nullable
                       FROM information_schema.columns
                       WHERE table_name = 'permissions'
                       ORDER BY ordinal_position");

foreach ($columns as $col) {
    $nullable = $col->is_nullable === 'YES' ? '(nullable)' : '(NOT NULL)';
    echo "  - {$col->column_name} ({$col->data_type}) {$nullable}\n";
}

echo "\n📊 Ejemplo de registro en permissions:\n";
$sample = DB::table('permissions')->first();
if ($sample) {
    foreach ($sample as $key => $value) {
        echo "  {$key}: {$value}\n";
    }
}
