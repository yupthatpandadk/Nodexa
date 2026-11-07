<?php

namespace Pterodactyl\Console\Commands\Nodexa;

use Illuminate\Console\Command;
use Pterodactyl\Services\Support\TicketAutomation;

class SupportAutomationCommand extends Command
{
    protected $signature = 'nodexa:support:automation';
    protected $description = 'Escalate overdue support tickets and close inactive answered tickets';

    public function handle(TicketAutomation $automation): int
    {
        $result = $automation->run();
        $this->info("Closed: {$result['closed']}; escalated: {$result['escalated']}");
        return self::SUCCESS;
    }
}
