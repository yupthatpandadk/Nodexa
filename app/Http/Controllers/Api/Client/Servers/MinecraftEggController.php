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
        ]);

        $previous = $server->egg;
        $this->switcher->switch($server, $request->user(), (int) $values['egg_id']);

        Activity::event('server:settings.minecraft-egg')
            ->property([
                'old_egg_id' => $previous->id,
                'old_egg_name' => $previous->name,
                'new_egg_id' => (int) $values['egg_id'],
            ])->log();

        return new JsonResponse([
            'message' => 'Minecraft Egg blev ændret. Geninstallationen er startet. Serverfiler kan blive ændret, især hvis du fortsatte uden backup.',
            'egg_id' => (int) $values['egg_id'],
        ], JsonResponse::HTTP_ACCEPTED);
    }
}
