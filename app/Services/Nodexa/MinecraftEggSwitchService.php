<?php

namespace Pterodactyl\Services\Nodexa;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Pterodactyl\Services\Servers\ReinstallServerService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Self-service Egg changes for Minecraft server owners ONLY.
 *
 * Changing installation software can overwrite or invalidate old server
 * files. Therefore this endpoint always requires a fresh completed backup,
 * stopped Wings container, explicit confirmation, and triggers a reinstall.
 *
 * It cannot be used by FiveM or other Nests, or by server subusers.
 */
class MinecraftEggSwitchService
{
    public function __construct(
        private MinecraftOrderOptionsService $minecraft,
        private DaemonServerRepository $daemon,
        private ReinstallServerService $reinstaller,
    ) {
    }

    public function canManage(Server $server, User $user): bool
    {
        return (int) $server->owner_id === (int) $user->id
            && $this->minecraft->belongsToMinecraftNest($server->egg);
    }

    public function assertCanManage(Server $server, User $user): void
    {
        if (!$this->canManage($server, $user)) {
            throw new AccessDeniedHttpException('Egg-skift er kun tilgængeligt for ejeren af en Minecraft-server.');
        }
    }

    public function isAllowedTarget(Server $server, Egg $egg): bool
    {
        return (int) $egg->nest_id === (int) $server->nest_id
            && $this->minecraft->belongsToMinecraftNest($egg);
    }

    /**
     * Only Eggs in the server's CURRENT Minecraft Nest are valid. Other
     * Nests, including FiveM, are never presented or accepted by the API.
     *
     * @return array<int, array{id:int,name:string,current:bool}>
     */
    public function options(Server $server, User $user): array
    {
        $this->assertCanManage($server, $user);

        return $this->minecraft->eggs()
            ->filter(fn (Egg $egg) => (int) $egg->nest_id === (int) $server->nest_id)
            ->map(fn (Egg $egg) => [
                'id' => (int) $egg->id,
                'name' => (string) $egg->name,
                'current' => (int) $egg->id === (int) $server->egg_id,
            ])
            ->values()
            ->all();
    }

    public function hasRecentBackup(Server $server): bool
    {
        return DB::table('backups')
            ->where('server_id', $server->id)
            ->where('is_successful', true)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', now()->subDay())
            ->whereNull('deleted_at')
            ->exists();
    }

    public function switch(Server $server, User $user, int $eggId): void
    {
        $this->assertCanManage($server, $user);

        $lock = Cache::lock('nodexa:minecraft:egg-switch:' . $server->id, 180);
        if (!$lock->get()) {
            throw new ConflictHttpException('Der er allerede et Egg-skift i gang. Prøv igen senere.');
        }

        try {
            $server->refresh();
            $this->assertCanManage($server, $user);

            if ((int) $server->egg_id === $eggId) {
                throw new BadRequestHttpException('Dette Egg bruges allerede på serveren.');
            }

            $egg = Egg::query()
                ->with(['nest', 'variables'])
                ->whereKey($eggId)
                ->where('nest_id', $server->nest_id)
                ->first();

            if (!$egg || !$this->isAllowedTarget($server, $egg)) {
                throw new BadRequestHttpException('Du kan kun vælge et Egg fra serverens eget Minecraft Nest.');
            }

            if ($server->status !== null || !$server->isInstalled() || !$server->canBeReinstalled()
                || $server->isSuspended() || $server->transfer !== null || $server->node->isUnderMaintenance()) {
                throw new ConflictHttpException('Serveren skal være færdiginstalleret og må ikke være suspenderet, under overførsel eller vedligeholdelse.');
            }

            if (!$this->hasRecentBackup($server)) {
                throw new ConflictHttpException('Opret en fuldført backup inden for de seneste 24 timer, før du skifter Egg.');
            }

            // Verify the actual Wings state; the database's install status
            // does not tell us if Minecraft is still running.
            $details = $this->daemon->setServer($server)->getDetails();
            if (!in_array((string) ($details['state'] ?? ''), ['offline', 'stopped'], true)) {
                throw new ConflictHttpException('Stop Minecraft-serveren helt, før du skifter Egg.');
            }

            if (!$egg->startup || empty($egg->docker_images)) {
                throw new BadRequestHttpException('Dette Egg mangler startup-kommando eller Docker-image. Kontakt administrator.');
            }

            // Ensure required variables have working defaults before changing
            // anything. Do not carry Fabric or Forge variables into Paper.
            foreach ($egg->variables as $variable) {
                if (str_contains((string) $variable->rules, 'required')
                    && $variable->default_value === null) {
                    throw new BadRequestHttpException('Dette Egg kræver manuel konfiguration af ' . $variable->name . '. Kontakt administrator.');
                }
            }

            $original = [
                'egg_id' => $server->egg_id,
                'nest_id' => $server->nest_id,
                'startup' => $server->startup,
                'image' => $server->image,
            ];
            $oldVariables = DB::table('server_variables')->where('server_id', $server->id)->get();

            DB::transaction(function () use ($server, $egg): void {
                $server->forceFill([
                    'egg_id' => $egg->id,
                    'nest_id' => $egg->nest_id,
                    'startup' => $egg->startup,
                    'image' => $this->minecraft->dockerImage($egg, null),
                ])->saveOrFail();

                DB::table('server_variables')->where('server_id', $server->id)->delete();
                foreach ($egg->variables as $variable) {
                    DB::table('server_variables')->insert([
                        'server_id' => $server->id,
                        'variable_id' => $variable->id,
                        'variable_value' => (string) ($variable->default_value ?? ''),
                    ]);
                }
            });

            // sync triggers Wings to read the new Egg environment.
            // Reinstallation is needed to replace Paper/Forge/Fabric artifacts.
            try {
                $this->daemon->setServer($server->fresh())->sync();
            } catch (\Throwable $exception) {
                // Sync did not succeed: restore the previous Egg and variables.
                DB::transaction(function () use ($server, $original, $oldVariables): void {
                    $server->forceFill($original)->saveOrFail();
                    DB::table('server_variables')->where('server_id', $server->id)->delete();
                    foreach ($oldVariables as $row) {
                        DB::table('server_variables')->insert((array) $row);
                    }
                });

                try {
                    $this->daemon->setServer($server->fresh())->sync();
                } catch (\Throwable $rollbackError) {
                    report($rollbackError);
                }

                throw $exception;
            }

            // If Wings fails during installation, Pterodactyl retains the
            // install-failed state; the user can contact support for recovery.
            $this->reinstaller->handle($server->fresh());
        } finally {
            $lock->release();
        }
    }
}
