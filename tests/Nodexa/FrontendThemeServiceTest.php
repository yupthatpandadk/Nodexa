<?php

namespace Pterodactyl\Tests\Nodexa;

use PHPUnit\Framework\TestCase;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Services\Nodexa\FrontendThemeService;

class FrontendThemeServiceTest extends TestCase
{
    public function test_all_five_layouts_exist_and_original_is_first(): void
    {
        $presets = FrontendThemeService::presets();

        $this->assertSame(
            ['classic', 'latch', 'xlumia', 'horizon', 'dinonode'],
            array_keys($presets)
        );

        foreach ($presets as $preset) {
            $this->assertNotEmpty($preset['name']);
            $this->assertNotEmpty($preset['description']);
            $this->assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $preset['accent']);
            $this->assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $preset['background']);
        }
    }

    public function test_unknown_saved_theme_falls_back_to_original(): void
    {
        $settings = $this->createMock(SettingsRepositoryInterface::class);
        $settings->expects($this->once())
            ->method('get')
            ->with('nodexa::frontend.theme', 'classic')
            ->willReturn('nonexistent-design');

        $service = new FrontendThemeService($settings);
        $this->assertSame('classic', $service->current());
        $this->assertSame('classic', $service->current());
    }

    public function test_first_installation_defaults_to_original(): void
    {
        $settings = $this->createMock(SettingsRepositoryInterface::class);
        $settings->method('get')->willReturn('classic');

        $this->assertSame('classic', (new FrontendThemeService($settings))->current());
    }

    public function test_save_changes_only_the_frontend_theme_setting(): void
    {
        $settings = $this->createMock(SettingsRepositoryInterface::class);
        $settings->expects($this->once())
            ->method('set')
            ->with('nodexa::frontend.theme', 'xlumia');

        $service = new FrontendThemeService($settings);
        $service->save('xlumia');

        $this->assertSame('xlumia', $service->current());
    }

    public function test_invalid_theme_is_rejected_before_database_write(): void
    {
        $settings = $this->createMock(SettingsRepositoryInterface::class);
        $settings->expects($this->never())->method('set');

        $this->expectException(\InvalidArgumentException::class);
        (new FrontendThemeService($settings))->save('<script>alert(1)</script>');
    }
}
