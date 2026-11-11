<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\JsonResponse;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\GetServerRequest;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Nodexa\MinecraftEggSwitchService;

class MinecraftEggController extends ClientApiController
{
    public function __construct(private MinecraftEggSwitchService $switcher)
    {
        parent::__construct();
    }

    /**
     * Listing is deliberately protected like the write endpoint. FiveM users
     * cannot enumerate Eggs by calling this API directly.
     */
    public function index(GetServerRequest $request, Server $server): JsonResponse
    {
        $this->switcher->assertCanManage($server, $request->user());

        return new JsonResponse([
            'current_egg_id' => (int) $server->egg_id,
            'eggs' => $this->switcher->options($server, $request->user()),
            'has_recent_backup' => $this->switcher->hasRecentBackup($server),
            'backup_optional' => true,
        ]);
    }

    public function update(GetServerRequest $request, Server $server): JsonResponse
    {
        $values = $request->validate([
            'egg_id' => ['required', 'integer', 'exists:eggs,id'],
            'confirm_reinstall' => ['required', 'accepted'],
            'installation_mode' => ['required', 'in:keep,clean'],
            'confirm_delete' => ['required_if:installation_mode,clean', 'nullable', 'in:SLET'],
        ]);

        $mode = $values['installation_mode'];
        $previous = $server->egg;
        $deleted = $this->switcher->switch(
            $server,
            $request->user(),
            (int) $values['egg_id'],
            $mode,
            $mode === 'clean' && ($values['confirm_delete'] ?? null) === 'SLET'
        );

        Activity::event('server:settings.minecraft-egg')
            ->property([
                'old_egg_id' => $previous->id,
                'old_egg_name' => $previous->name,
                'new_egg_id' => (int) $values['egg_id'],
                'installation_mode' => $mode,
                'deleted_root_entries' => $deleted,
            ])->log();

        return new JsonResponse([
            'message' => $mode === 'clean'
                ? 'Minecraft Egg blev ændret. Serverens filer blev ryddet og geninstallationen er startet.'
                : 'Minecraft Egg blev ændret. Geninstallationen er startet. Nogle eksisterende filer kan blive overskrevet af det nye Egg.',
            'egg_id' => (int) $values['egg_id'],
            'installation_mode' => $mode,
            'deleted_root_entries' => $deleted,
        ], JsonResponse::HTTP_ACCEPTED);
    }
}
