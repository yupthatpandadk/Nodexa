<?php

namespace Pterodactyl\Tests\Nodexa;

use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\EggVariable;
use Pterodactyl\Models\Nest;
use Pterodactyl\Services\Nodexa\MinecraftOrderOptionsService;

class MinecraftOrderOptionsServiceTest extends TestCase
{
    private MinecraftOrderOptionsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MinecraftOrderOptionsService();
    }

    public function test_minecraft_eggs_are_recognized_by_nest_not_egg_id(): void
    {
        $paper = new Egg(['name' => 'Paper']);
        $paper->setRelation('nest', new Nest(['name' => 'Minecraft']));
        $paper->setRelation('variables', new Collection());

        $this->assertTrue($this->service->belongsToMinecraftNest($paper));

        $notMinecraft = new Egg(['name' => 'Paper']);
        $notMinecraft->setRelation('nest', new Nest(['name' => 'Other Games']));
        $notMinecraft->setRelation('variables', new Collection());

        $this->assertFalse($this->service->belongsToMinecraftNest($notMinecraft));
    }

    public function test_common_version_variables_are_detected(): void
    {
        foreach (['MINECRAFT_VERSION', 'MC_VERSION', 'VANILLA_VERSION', 'SERVER_VERSION'] as $name) {
            $egg = new Egg(['name' => 'Custom Minecraft']);
            $egg->setRelation('variables', new Collection([
                new EggVariable(['env_variable' => $name, 'name' => 'Minecraft Version']),
            ]));
            $this->assertSame($name, $this->service->versionVariable($egg));
        }
    }

    public function test_proxy_with_no_version_variable_uses_egg_default(): void
    {
        $egg = new Egg(['name' => 'Bungeecord']);
        $egg->setRelation('variables', new Collection([
            new EggVariable(['env_variable' => 'SERVER_JARFILE', 'name' => 'Jar File']),
        ]));
        $this->assertNull($this->service->versionVariable($egg));
    }

    public function test_fabric_loader_version_is_not_confused_with_minecraft_version(): void
    {
        $egg = new Egg(['name' => 'Fabric']);
        $egg->setRelation('variables', new Collection([
            new EggVariable(['env_variable' => 'VERSION', 'name' => 'Fabric Loader Version']),
            new EggVariable(['env_variable' => 'MC_VERSION', 'name' => 'Minecraft Version']),
        ]));
        $this->assertSame('MC_VERSION', $this->service->versionVariable($egg));
    }

    public function test_only_valid_java_release_labels_are_allowed(): void
    {
        foreach (['latest', '1.8', '1.12.2', '1.19.4', '1.20.1', '1.21.11', '26.1', '26.1.2', '26.3'] as $version) {
            $this->assertTrue(MinecraftOrderOptionsService::validVersion($version), $version);
        }
        foreach (['snapshot', '1.20.1;rm -rf /', '../../etc', '', 'abc', '2.0', '26w14a', '26.3;echo'] as $version) {
            $this->assertFalse(MinecraftOrderOptionsService::validVersion($version), $version);
        }
    }

    public function test_java_image_matches_requested_minecraft_version(): void
    {
        $egg = new Egg(['name' => 'Paper']);
        $egg->docker_images = [
            'Java 25' => 'ghcr.io/pterodactyl/yolks:java_25',
            'Java 21' => 'ghcr.io/pterodactyl/yolks:java_21',
            'Java 17' => 'ghcr.io/pterodactyl/yolks:java_17',
            'Java 8' => 'ghcr.io/pterodactyl/yolks:java_8',
        ];

        $this->assertSame('ghcr.io/pterodactyl/yolks:java_21', $this->service->dockerImage($egg, '1.21.1'));
        $this->assertSame('ghcr.io/pterodactyl/yolks:java_25', $this->service->dockerImage($egg, '26.3'));
        $this->assertSame('ghcr.io/pterodactyl/yolks:java_25', $this->service->dockerImage($egg, '26.1.2'));
        $this->assertSame('ghcr.io/pterodactyl/yolks:java_21', $this->service->dockerImage($egg, '1.20.5'));
        $this->assertSame('ghcr.io/pterodactyl/yolks:java_17', $this->service->dockerImage($egg, '1.20.4'));
        $this->assertSame('ghcr.io/pterodactyl/yolks:java_8', $this->service->dockerImage($egg, '1.12.2'));
    }
}
