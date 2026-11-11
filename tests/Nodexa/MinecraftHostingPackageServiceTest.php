<?php

namespace Pterodactyl\Tests\Nodexa;

use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use Pterodactyl\Models\Egg;
use Pterodactyl\Services\Nodexa\MinecraftHostingPackageService;
use Pterodactyl\Services\Nodexa\MinecraftOrderOptionsService;
use RuntimeException;

class MinecraftHostingPackageServiceTest extends TestCase
{
    private MinecraftHostingPackageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MinecraftHostingPackageService(new MinecraftOrderOptionsService());
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

    public function test_technical_default_prefers_paper_and_not_a_proxy(): void
    {
        $eggs = new Collection([
            new Egg(['name' => 'Bungeecord']),
            new Egg(['name' => 'Fabric']),
            new Egg(['name' => 'Paper']),
            new Egg(['name' => 'Vanilla Minecraft']),
        ]);

        $this->assertSame(
            'Paper',
            MinecraftHostingPackageService::preferredEgg($eggs)->name
        );
    }

    public function test_technical_default_uses_vanilla_if_paper_missing(): void
    {
        $eggs = new Collection([
            new Egg(['name' => 'Velocity']),
            new Egg(['name' => 'Forge Minecraft']),
            new Egg(['name' => 'Vanilla Minecraft']),
        ]);

        $this->assertSame(
            'Vanilla Minecraft',
            MinecraftHostingPackageService::preferredEgg($eggs)->name
        );
    }

    public function test_proxy_only_nest_has_no_default_server_egg(): void
    {
        $eggs = new Collection([
            new Egg(['name' => 'Bungeecord']),
            new Egg(['name' => 'Velocity']),
        ]);

        $this->assertNull(MinecraftHostingPackageService::preferredEgg($eggs));
        $this->assertNull(MinecraftHostingPackageService::preferredEgg(new Collection()));
    }

    public function test_new_product_slug_does_not_depend_on_egg_id(): void
    {
        $this->assertSame('minecraft-stone', MinecraftHostingPackageService::slugFor('stone'));
        $this->assertSame('minecraft-obsidian', MinecraftHostingPackageService::slugFor('obsidian'));
    }

    public function test_empty_tiers_are_rejected_before_database_access(): void
    {
        $this->expectException(RuntimeException::class);
        $this->service->generate([]);
    }

    public function test_unknown_tier_is_rejected_before_database_access(): void
    {
        $this->expectException(RuntimeException::class);
        $this->service->generate(['creative-admin']);
    }

    public function test_duplicate_tiers_are_rejected_before_database_access(): void
    {
        $this->expectException(RuntimeException::class);
        $this->service->generate(['dirt', 'dirt']);
    }

    public function test_non_numeric_price_is_rejected_before_database_access(): void
    {
        $this->expectException(RuntimeException::class);
        $this->service->generate(['stone'], ['stone' => 'gratis']);
    }
}
