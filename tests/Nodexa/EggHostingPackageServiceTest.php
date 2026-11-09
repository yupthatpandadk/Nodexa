<?php

namespace Pterodactyl\Tests\Nodexa;

use PHPUnit\Framework\TestCase;
use Pterodactyl\Models\Egg;
use Pterodactyl\Services\Nodexa\EggHostingPackageService;

class EggHostingPackageServiceTest extends TestCase
{
    private EggHostingPackageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EggHostingPackageService();
    }

    public function test_detects_minecraft_and_fivem_eggs(): void
    {
        $minecraft = new Egg(['name' => 'Paper Minecraft']);
        $minecraft->setRelation('nest', null);
        $fivem = new Egg(['name' => 'FiveM FXServer']);
        $fivem->setRelation('nest', null);

        $this->assertSame('minecraft', $this->service->detectCategory($minecraft));
        $this->assertSame('fivem', $this->service->detectCategory($fivem));
    }

    public function test_unknown_egg_uses_generic_profile(): void
    {
        $egg = new Egg(['name' => 'Custom Dedicated Server']);
        $egg->setRelation('nest', null);

        $this->assertSame('generic', $this->service->detectCategory($egg));
    }

    public function test_all_presets_have_four_increasing_resource_tiers(): void
    {
        foreach ($this->service->allProfiles() as $category => $packages) {
            $this->assertContains($category, array_keys(EggHostingPackageService::GAME_LABELS));
            $this->assertSame(array_keys(EggHostingPackageService::TIERS), array_keys($packages));
            $lastMemory = 0;
            $lastPrice = 0;

            foreach ($packages as $tier => $plan) {
                $this->assertGreaterThan($lastMemory, $plan['memory']);
                $this->assertGreaterThan($lastPrice, $plan['price']);
                $this->assertGreaterThanOrEqual(50, $plan['cpu']);
                $this->assertGreaterThanOrEqual(256, $plan['disk']);
                $this->assertGreaterThanOrEqual(1, $plan['allocations']);
                $this->assertGreaterThanOrEqual(0, $plan['backups']);
                $this->assertGreaterThanOrEqual(0, $plan['databases']);
                $lastMemory = $plan['memory'];
                $lastPrice = $plan['price'];
            }
        }
    }

    public function test_games_have_separate_pricing_and_ram_profiles(): void
    {
        $minecraft = $this->service->packages('minecraft');
        $rust = $this->service->packages('rust');

        $this->assertNotSame($minecraft['starter']['memory'], $rust['starter']['memory']);
        $this->assertNotSame($minecraft['starter']['price'], $rust['starter']['price']);
    }

    public function test_unknown_category_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service->packages('invalid-game');
    }
}
