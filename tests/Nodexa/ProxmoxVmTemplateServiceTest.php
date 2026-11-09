<?php

namespace Pterodactyl\Tests\Nodexa;

use PHPUnit\Framework\TestCase;
use Pterodactyl\Services\Nodexa\ProxmoxVmTemplateService;

class ProxmoxVmTemplateServiceTest extends TestCase
{
    public function test_catalog_contains_expected_operating_systems(): void
    {
        $catalog = (new ProxmoxVmTemplateService())->catalog();

        $this->assertArrayHasKey('ubuntu-2404', $catalog);
        $this->assertArrayHasKey('ubuntu-2204', $catalog);
        $this->assertArrayHasKey('debian-12', $catalog);
        $this->assertArrayHasKey('debian-13', $catalog);
        $this->assertArrayHasKey('windows-2022', $catalog);
        $this->assertArrayHasKey('windows-2025', $catalog);

        foreach ($catalog as $key => $preset) {
            $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $key);
            $this->assertNotEmpty($preset['name']);
            $this->assertContains($preset['group'], ['Linux', 'Windows']);
        }
    }

    public function test_catalog_is_not_claiming_all_images_are_preinstalled(): void
    {
        $catalog = (new ProxmoxVmTemplateService())->catalog();

        $this->assertStringContainsString(
            'template',
            strtolower($catalog['windows-2022']['description'])
        );
    }
}
