<?php

namespace Pterodactyl\Console\Commands\Nodexa;

use Illuminate\Console\Command;
use Pterodactyl\Services\Nodexa\NodexaAutomationService;

class RunAutomationCommand extends Command
{
    protected $signature = 'nodexa:automation';
    protected $description = 'Run Nodexa health, backup and billing automation.';

    public function handle(NodexaAutomationService $automation): int
    {
        $result = $automation->run();

        $this->info(sprintf(
            'Nodexa automation complete: %d health checks, %d backups, %d invoices.',
            $result['health'],
            $result['backups'],
            $result['billing'],
        ));

        return self::SUCCESS;
    }
}
