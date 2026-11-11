<?php

namespace Pterodactyl\Tests\Nodexa;

use PHPUnit\Framework\TestCase;
use Pterodactyl\Models\Egg;
use Pterodactyl\Services\Nodexa\MinecraftHostingPackageService;
use RuntimeException;

class MinecraftHostingPackageServiceTest extends TestCase
{
    private MinecraftHostingPackageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MinecraftHostingPackageService();
    }

    public function test_all_eight_minecraft_tiers_are_defined_in_ascending_order(): void
    {
        $profiles = $this->service->profiles();

        $this->assertSame(
            ['dirt', 'grass', 'stone', 'iron', 'gold', 'diamond', 'emerald', 'obsidian'],
            array_keys($profiles)
        );

        $lastMemory = 0;
        $lastCpu = 0;
        $lastDisk = 0;
        $lastPrice = 0;

        foreach ($profiles as $tier => $profile) {
            $this->assertSame(ucfirst($tier), $profile['name']);
            $this->assertGreaterThan($lastMemory, $profile['memory']);
            $this->assertGreaterThan($lastCpu, $profile['cpu']);
            $this->assertGreaterThan($lastDisk, $profile['disk']);
            $this->assertGreaterThan($lastPrice, $profile['price']);
            $this->assertGreaterThanOrEqual(1, $profile['allocations']);
            $this->assertGreaterThanOrEqual(1, $profile['backups']);
            $this->assertGreaterThanOrEqual(1, $profile['databases']);
            $this->assertNotEmpty($profile['description']);
            $lastMemory = $profile['memory'];
            $lastCpu = $profile['cpu'];
            $lastDisk = $profile['disk'];
            $lastPrice = $profile['price'];
        }
    }

    public function test_common_minecraft_eggs_are_supported(): void
    {
        foreach (['Minecraft Java', 'Paper', 'Spigot', 'Fabric', 'Forge', 'Folia'] as $name) {
            $egg = new Egg(['name' => $name]);
            $egg->setRelation('nest', null);
            $this->assertTrue($this->service->supportsEgg($egg), $name);
        }
    }

    public function test_fivem_eggs_cannot_generate_minecraft_packages(): void
    {
        $egg = new Egg(['name' => 'FiveM FXServer']);
        $egg->setRelation('nest', null);

        $this->expectException(RuntimeException::class);
        $this->service->generate($egg, ['dirt']);
    }

    public function test_unknown_tier_is_rejected_before_database_access(): void
    {
        $egg = new Egg(['name' => 'Paper']);
        $egg->setRelation('nest', null);

        $this->expectException(RuntimeException::class);
        $this->service->generate($egg, ['creative-admin']);
    }

    public function test_duplicate_tiers_are_rejected_before_database_access(): void
    {
        $egg = new Egg(['name' => 'Paper']);
        $egg->setRelation('nest', null);

        $this->expectException(RuntimeException::class);
        $this->service->generate($egg, ['dirt', 'dirt']);
    }

    public function test_non_numeric_price_is_rejected_before_database_access(): void
    {
        $egg = new Egg(['name' => 'Paper']);
        $egg->setRelation('nest', null);

        $this->expectException(RuntimeException::class);
        $this->service->generate($egg, ['stone'], ['stone' => 'gratis']);
    }
}
