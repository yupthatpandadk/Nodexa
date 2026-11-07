<?php

namespace Pterodactyl\Console\Commands\Nodexa;

use Illuminate\Console\Command;
use Pterodactyl\Services\Nodexa\NodexaAutomationService;

class RunAutomationCommand extends Command
{
    protected $signature = 'nodexa:automation';
    protected $description = 'Run Nodexa health, backup, billing and reseller automation.';

    public function handle(NodexaAutomationService $automation): int
    {
        $result = $automation->run();

        $this->info(sprintf(
            'Nodexa automation complete: %d health checks, %d backups, %d invoices, %d Flax VPS syncs.',
            $result['health'],
            $result['backups'],
            $result['billing'],
            $result['flax'] ?? 0,
        ));

        return self::SUCCESS;
    }
}
