<?php

namespace Pterodactyl\Console\Commands\Nodexa;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Pterodactyl\Services\Nodexa\ProxmoxVmTemplateService;

class ProcessProxmoxVmDeploymentsCommand extends Command
{
    protected $signature = 'nodexa:proxmox:process {--limit=20 : Maximum deployments per run}';

    protected $description = 'Read-only poll clone/start task status and start fully cloned Proxmox VM templates.';

    public function handle(ProxmoxVmTemplateService $service): int
    {
        if (!Schema::hasTable('nodexa_proxmox_deployments')) {
            return self::SUCCESS;
        }

        $processed = $service->processPending((int) $this->option('limit'));
        $this->info('Proxmox-deployments kontrolleret: ' . $processed);

        return self::SUCCESS;
    }
}
