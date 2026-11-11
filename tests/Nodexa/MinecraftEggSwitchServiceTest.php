<?php

namespace Pterodactyl\Tests\Nodexa;

use PHPUnit\Framework\TestCase;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Nest;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Pterodactyl\Services\Nodexa\MinecraftEggSwitchService;
use Pterodactyl\Services\Nodexa\MinecraftOrderOptionsService;
use Pterodactyl\Services\Servers\ReinstallServerService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class MinecraftEggSwitchServiceTest extends TestCase
{
    private MinecraftEggSwitchService $switcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->switcher = new MinecraftEggSwitchService(
            new MinecraftOrderOptionsService(),
            $this->createMock(DaemonServerRepository::class),
            $this->createMock(ReinstallServerService::class)
        );
    }

    private function user(int $id): User
    {
        return (new User())->forceFill(['id' => $id]);
    }

    private function server(int $ownerId, string $nestName): Server
    {
        $nest = new Nest(['name' => $nestName]);
        $egg = new Egg(['name' => $nestName === 'FiveM' ? 'FXServer' : 'Paper']);
        $egg->setRelation('nest', $nest);

        $server = (new Server())->forceFill(['owner_id' => $ownerId]);
        $server->setRelation('egg', $egg);

        return $server;
    }

    public function test_minecraft_owner_can_access_switcher(): void
    {
        $this->assertTrue($this->switcher->canManage(
            $this->server(10, 'Minecraft'),
            $this->user(10)
        ));
    }

    public function test_fivem_owner_cannot_access_switcher(): void
    {
        $server = $this->server(10, 'FiveM');
        $this->assertFalse($this->switcher->canManage($server, $this->user(10)));
        $this->expectException(AccessDeniedHttpException::class);
        $this->switcher->assertCanManage($server, $this->user(10));
    }

    public function test_other_game_owner_cannot_access_switcher(): void
    {
        $this->assertFalse($this->switcher->canManage(
            $this->server(10, 'Rust'),
            $this->user(10)
        ));
    }

    public function test_target_must_be_in_same_minecraft_nest(): void
    {
        $server = $this->server(10, 'Minecraft');
        $server->forceFill(['nest_id' => 3]);

        $sameNest = new Egg(['name' => 'Fabric']);
        $sameNest->forceFill(['nest_id' => 3]);
        $sameNest->setRelation('nest', new Nest(['name' => 'Minecraft']));
        $this->assertTrue($this->switcher->isAllowedTarget($server, $sameNest));

        $otherNest = new Egg(['name' => 'FXServer']);
        $otherNest->forceFill(['nest_id' => 4]);
        $otherNest->setRelation('nest', new Nest(['name' => 'FiveM']));
        $this->assertFalse($this->switcher->isAllowedTarget($server, $otherNest));

        $anotherMinecraftNest = new Egg(['name' => 'Paper']);
        $anotherMinecraftNest->forceFill(['nest_id' => 6]);
        $anotherMinecraftNest->setRelation('nest', new Nest(['name' => 'Minecraft']));
        $this->assertFalse($this->switcher->isAllowedTarget($server, $anotherMinecraftNest));
    }

    public function test_subuser_cannot_change_minecraft_egg(): void
    {
        $server = $this->server(10, 'Minecraft');
        $this->assertFalse($this->switcher->canManage($server, $this->user(22)));
        $this->expectException(AccessDeniedHttpException::class);
        $this->switcher->switch($server, $this->user(22), 5);
    }

    public function test_unauthorized_minecraft_switch_fails_before_querying_any_egg(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        $this->switcher->switch($this->server(10, 'FiveM'), $this->user(10), 5);
    }
}
