<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;

class ApplyLateFees extends Command
{
    protected $signature = 'late-fees:apply';
    protected $description = 'Aplica cargos por mora a las cuentas vencidas';

    public function handle(): int
    {
        $this->info('Aplicando cargos por mora...');
        // Lógica de aplicación de recargos iría aquí
        return Command::SUCCESS;
    }
}
