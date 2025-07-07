<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;

class SendPaymentReminders extends Command
{
    protected $signature = 'payments:send-reminders';
    protected $description = 'Envia recordatorios de pago a los usuarios';

    public function handle(): int
    {
        $this->info('Enviando recordatorios de pago...');
        // Lógica de envío de recordatorios iría aquí
        return Command::SUCCESS;
    }
}
