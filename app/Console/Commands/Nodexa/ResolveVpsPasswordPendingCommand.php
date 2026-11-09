<?php

namespace Pterodactyl\Console\Commands\Nodexa;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ResolveVpsPasswordPendingCommand extends Command
{
    protected $signature = 'nodexa:vps-password:resolve
        {service : Nodexa internal VPS service ID}
        {--confirm-provider-checked : Confirm that the actual current VPS password and reset state were verified independently}';

    protected $description = 'Clear an unconfirmed VPS password reset ONLY after an operator verifies the current OS login with the VPS provider.';

    public function handle(): int
    {
        $serviceId = (int) $this->argument('service');

        if ($serviceId < 1) {
            $this->error('VPS-ID skal være et positivt tal.');
            return self::FAILURE;
        }

        $service = DB::table('nodexa_flax_services')->where('id', $serviceId)->first();
        if (!$service) {
            $this->error('VPS findes ikke i Nodexa.');
            return self::FAILURE;
        }

        if (empty($service->credential_reset_pending_at)) {
            $this->info('VPS har ingen afventende password-reset.');
            return self::SUCCESS;
        }

        $this->warn('Resetstatus er ubekræftet. Denne kommando ændrer IKKE passwordet på serveren.');
        $this->warn('Kontrollér først VPS-login og evt. den seneste nulstilling i udbyderens kontrolpanel.');

        if (!$this->option('confirm-provider-checked')) {
            $this->error('Ingen ændringer foretaget. Kør med --confirm-provider-checked først EFTER manuel verifikation.');
            return self::FAILURE;
        }

        $changed = DB::table('nodexa_flax_services')
            ->where('id', $serviceId)
            ->whereNotNull('credential_reset_pending_at')
            ->update([
                'credential_reset_pending_at' => null,
                'credential_reset_previous_hash' => null,
                // Never keep or show a possibly stale OS password as current.
                'credential_password_encrypted' => null,
                'updated_at' => now(),
            ]);

        if (!$changed) {
            $this->warn('Afventende status blev allerede afsluttet af en anden handling.');
            return self::SUCCESS;
        }

        Log::notice('Nodexa VPS password reset pending state resolved by operator', [
            'service_id' => $serviceId,
            'reason' => 'manually_verified',
        ]);

        $this->info('Afventningsstatus er ryddet for VPS #' . $serviceId . '.');
        $this->line('Gamle, muligvis forældede adgangskoder er fjernet fra Nodexa. Ingen serverpasswords blev ændret.');
        return self::SUCCESS;
    }
}
