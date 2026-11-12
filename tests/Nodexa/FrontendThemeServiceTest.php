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

    public function test_storefront_falls_back_to_original_when_settings_read_fails(): void
    {
        $settings = $this->createMock(SettingsRepositoryInterface::class);
        $settings->method('get')->willThrowException(new \RuntimeException('Database temporarily unavailable'));

        // The frontend design cannot be allowed to take billing and checkout
        // pages offline; classic must always remain the fallback.
        $service = new FrontendThemeService($settings);
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

    public function test_all_non_classic_themes_have_real_home_hosting_and_vps_templates(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['latch', 'xlumia', 'horizon', 'dinonode'] as $theme) {
            foreach (['home', 'hosting', 'vps'] as $page) {
                $this->assertFileExists(
                    $root . '/resources/views/store/frontend/' . $theme . '/' . $page . '.blade.php',
                    sprintf('Missing %s page layout for %s', $page, $theme)
                );
            }
        }
    }

    public function test_original_pages_are_not_overwritten_by_new_layouts(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['index.blade.php', 'hosting.blade.php', 'vps/index.blade.php'] as $page) {
            $this->assertFileExists($root . '/resources/views/store/' . $page);
        }
    }

    public function test_each_frontend_has_separate_navigation_footer_and_product_presentation(): void
    {
        $root = dirname(__DIR__, 2);

        foreach ([
            'resources/views/store/frontend/parts/header.blade.php',
            'resources/views/store/frontend/parts/footer.blade.php',
            'resources/views/store/frontend/parts/product-hero.blade.php',
            'public/css/nodexa-frontend-shells.css',
            'public/css/nodexa-frontend-page-base.css',
            'resources/views/store/frontend/parts/latch-game-selector.blade.php',
        ] as $file) {
            $this->assertFileExists($root . '/' . $file);
        }

        $footer = file_get_contents($root . '/resources/views/store/frontend/parts/footer.blade.php');
        $headers = file_get_contents($root . '/resources/views/store/frontend/parts/header.blade.php');
        $product = file_get_contents($root . '/resources/views/store/frontend/parts/product-hero.blade.php');

        foreach (['latch', 'xlumia', 'horizon', 'dinonode'] as $theme) {
            $this->assertStringContainsString("nodexaFrontendLayout === '" . $theme . "'", $headers);
            $this->assertStringContainsString("nodexaFrontendLayout === '" . $theme . "'", $footer);
            $this->assertStringContainsString("nodexaProductLayout === '" . $theme . "'", $product);
        }
    }

    public function test_latch_uses_the_reference_inspired_gaming_hero_not_old_terminal(): void
    {
        $root = dirname(__DIR__, 2);
        $homepage = file_get_contents($root . '/resources/views/store/frontend/latch/home.blade.php');
        $header = file_get_contents($root . '/resources/views/store/frontend/parts/header.blade.php');
        $presets = FrontendThemeService::presets();

        $this->assertStringContainsString('nx-latch-landing-hero', $homepage);
        $this->assertStringContainsString('parts.latch-game-selector', $homepage);
        $this->assertStringNotContainsString('nx-latch-terminal', $homepage);
        $this->assertStringContainsString('nx-latch-onebar', $header);
        $this->assertSame('#A855F7', $presets['latch']['accent']);
    }

    public function test_dinonode_branding_uses_original_blue_style(): void
    {
        $presets = FrontendThemeService::presets();
        $this->assertSame('#289BEE', $presets['dinonode']['accent']);
        $this->assertSame('#131426', $presets['dinonode']['background']);
    }

    public function test_admin_thumbnails_render_actual_storefront_not_identical_mockups(): void
    {
        $root = dirname(__DIR__, 2);
        $admin = file_get_contents($root . '/resources/views/admin/settings/themes.blade.php');
        $this->assertStringContainsString('nx-front-real-preview', $admin);
        $this->assertStringContainsString('frontend_theme_preview', $admin);
        $this->assertStringNotContainsString('class="nx-front-demo" aria-hidden="true"', $admin);
    }

    public function test_invalid_theme_is_rejected_before_database_write(): void
    {
        $settings = $this->createMock(SettingsRepositoryInterface::class);
        $settings->expects($this->never())->method('set');

        $this->expectException(\InvalidArgumentException::class);
        (new FrontendThemeService($settings))->save('<script>alert(1)</script>');
    }
}
