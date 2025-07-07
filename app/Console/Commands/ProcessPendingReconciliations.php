<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProcessPendingReconciliations extends Command
{
    protected $signature = 'reconciliations:process-pending';
    protected $description = 'Procesa las conciliaciones pendientes';

    public function handle(): int
    {
        $this->info('Procesando conciliaciones pendientes...');
        // Lógica de conciliación iría aquí
        return Command::SUCCESS;
    }
}
