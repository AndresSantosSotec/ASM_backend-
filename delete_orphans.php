<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$ids = [622, 623, 624, 625, 626, 627, 628, 629, 630, 631];
$deleted = DB::table('userpermissions')->whereIn('id', $ids)->delete();
echo "✅ Eliminados: {$deleted} registros huérfanos\n";
