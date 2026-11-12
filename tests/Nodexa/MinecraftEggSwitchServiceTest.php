<?php

namespace Pterodactyl\Tests\Nodexa;

use PHPUnit\Framework\TestCase;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Nest;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
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
            $this->createMock(ReinstallServerService::class),
            $this->createMock(DaemonFileRepository::class)
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


    public function test_keep_mode_is_default_and_does_not_require_delete_confirmation(): void
    {
        // Unauthorized request hits the owner guard, not the clean-mode guard.
        $this->expectException(AccessDeniedHttpException::class);
        $this->switcher->switch($this->server(10, 'FiveM'), $this->user(10), 5);
    }

    public function test_clean_mode_requires_extra_explicit_confirmation(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->switcher->switch($this->server(10, 'Minecraft'), $this->user(10), 5, 'clean', false);
    }

    public function test_unknown_installation_mode_is_rejected(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->switcher->switch($this->server(10, 'Minecraft'), $this->user(10), 5, 'delete-everything');
    }

    public function test_clean_delete_list_only_accepts_server_root_entries(): void
    {
        $this->assertSame(
            ['world', 'server.properties', '.pteroignore', 'mods'],
            MinecraftEggSwitchService::rootEntriesForDeletion([
                ['name' => 'world', 'file' => false],
                ['name' => 'server.properties', 'file' => true],
                ['name' => '.pteroignore', 'file' => true],
                ['name' => 'mods', 'file' => false],
            ])
        );
    }

    /**
     * @dataProvider unsafeCleanNames
     */
    public function test_clean_delete_list_rejects_unsafe_paths(string $name): void
    {
        $this->expectException(ConflictHttpException::class);
        MinecraftEggSwitchService::rootEntriesForDeletion([['name' => $name]]);
    }

    public static function unsafeCleanNames(): array
    {
        return [
            'empty' => [''],
            'dot' => ['.'],
            'parent' => ['..'],
            'absolute' => ['/etc/passwd'],
            'subdirectory' => ['world/playerdata'],
            'windows_traversal' => ['..\\other'],
            'line_break' => ["world\nadmin"],
        ];
    }

    public function test_clean_delete_list_rejects_symlinks(): void
    {
        $this->expectException(ConflictHttpException::class);
        MinecraftEggSwitchService::rootEntriesForDeletion([
            ['name' => 'world', 'symlink' => true],
        ]);
    }

    public function test_clean_delete_list_rejects_wings_is_symlink_entries(): void
    {
        // Wings uses "is_symlink", not just "symlink". Failing to check
        // this could follow a mounted or external directory during cleanup.
        $this->expectException(ConflictHttpException::class);
        MinecraftEggSwitchService::rootEntriesForDeletion([
            ['name' => 'external-folder', 'is_symlink' => true],
        ]);
    }

    public function test_clean_delete_list_accepts_regular_wings_entries(): void
    {
        $this->assertSame(
            ['world', 'server.properties'],
            MinecraftEggSwitchService::rootEntriesForDeletion([
                ['name' => 'world', 'is_file' => false, 'is_symlink' => false],
                ['name' => 'server.properties', 'is_file' => true, 'is_symlink' => false],
            ])
        );
    }

    public function test_clean_delete_list_rejects_duplicates(): void
    {
        $this->expectException(ConflictHttpException::class);
        MinecraftEggSwitchService::rootEntriesForDeletion([
            ['name' => 'world'], ['name' => 'world'],
        ]);
    }

    public function test_unauthorized_minecraft_switch_fails_before_querying_any_egg(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        $this->switcher->switch($this->server(10, 'FiveM'), $this->user(10), 5);
    }
}
