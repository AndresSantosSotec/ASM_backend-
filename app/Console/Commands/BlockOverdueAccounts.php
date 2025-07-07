<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;

class BlockOverdueAccounts extends Command
{
    protected $signature = 'accounts:block-overdue';
    protected $description = 'Bloquea cuentas con pagos atrasados';

    public function handle(): int
    {
        $this->info('Bloqueando cuentas morosas...');
        // Lógica de bloqueo iría aquí
        return Command::SUCCESS;
    }
}
