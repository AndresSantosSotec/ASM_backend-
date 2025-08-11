<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolePermissionsSeeder;

class PermissionsSync extends Command
{
    protected $signature = 'permissions:sync {--seed-roles}';

    protected $description = 'Synchronize permissions with module views';

    public function handle(): int
    {
        (new PermissionsSeeder())->run();

        if ($this->option('seed-roles')) {
            (new RolePermissionsSeeder())->run();
        }

        $this->info('Permissions synchronized.');
        return Command::SUCCESS;
    }
}
