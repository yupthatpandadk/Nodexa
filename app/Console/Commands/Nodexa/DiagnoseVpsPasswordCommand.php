<?php

namespace Pterodactyl\Console\Commands\Nodexa;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Services\Nodexa\FlaxResellerService;
use Pterodactyl\Services\Nodexa\VpsCredentialLookupException;

class DiagnoseVpsPasswordCommand extends Command
{
    protected $signature = 'nodexa:vps-password:diagnose {service : Nodexa internal VPS ID}';

    protected $description = 'Safely diagnose VPS password lookup without displaying an API key or password.';

    public function handle(FlaxResellerService $flax): int
    {
        $serviceId = (int) $this->argument('service');
        if ($serviceId < 1) {
            $this->error('Angiv et gyldigt internt VPS-ID.');
            return self::FAILURE;
        }

        $service = DB::table('nodexa_flax_services')
            ->where('id', $serviceId)
            ->first(['id', 'user_id', 'status', 'upstream_server_id']);

        if (!$service) {
            $this->error('VPS-ID findes ikke i Nodexa.');
            return self::FAILURE;
        }

        if (!$service->upstream_server_id) {
            $this->error('VPS er ikke provisioneret, og server-id mangler.');
            return self::FAILURE;
        }

        if (!$flax->isConfigured()) {
            $this->error('Reseller API-forbindelsen er ikke aktiveret i Nodexa Admin.');
            return self::FAILURE;
        }

        $this->line('Test af VPS #' . $serviceId . ' · status ' . $service->status);
        $this->line('Tester kun læsehandlingen get_password. Serverpassword bliver ikke ændret.');

        try {
            $password = $flax->getStoredOsPassword(
                $serviceId,
                (int) $service->user_id
            );

            if ($password === null) {
                $this->warn('API-kaldet lykkedes, men der er intet gemt password til denne VPS.');
                return self::SUCCESS;
            }

            $this->info('API-kaldet lykkedes: Et gemt VPS-password blev fundet.');
            $this->line('Passwordet vises ikke af sikkerhedshensyn.');
            return self::SUCCESS;
        } catch (VpsCredentialLookupException $exception) {
            $this->error('API-fejlkategori: ' . $exception->reason);
            $this->warn($exception->getMessage());
        } catch (\Throwable $exception) {
            $this->error('API-fejlkategori: unexpected_failure');
            $this->warn('Intern fejl. Kontrollér serverens Laravel-log uden at dele hemmeligheder.');
        }

        return self::FAILURE;
    }
}
