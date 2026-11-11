<?php

namespace Pterodactyl\Services\Nodexa;

use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

/**
 * Public storefront design presets, separate from Nodexa's site-wide
 * seasonal/color palette and from the Pterodactyl React/Admin interfaces.
 *
 * No user-supplied HTML/CSS and no third-party template assets are loaded.
 * These are original Nodexa layouts inspired by familiar hosting styles.
 */
class FrontendThemeService
{
    private const SETTING_KEY = 'nodexa::frontend.theme';
    private const DEFAULT_THEME = 'classic';

    private ?string $resolved = null;

    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    public static function presets(): array
    {
        return [
            'classic' => [
                'name' => 'Nodexa Original',
                'description' => 'Det nuværende Nodexa-design. Samme layout og udseende som før.',
                'subtitle' => 'Original · nuværende design',
                'accent' => '#3D8DFF',
                'secondary' => '#7657FF',
                'background' => '#050B14',
                'surface' => '#0B1828',
            ],
            'latch' => [
                'name' => 'Latch-stil',
                'description' => 'Teknisk og nedtonet hostingdesign med grønne detaljer, stærk typografi og tydelig navigation.',
                'subtitle' => 'Minimal · operationel',
                'accent' => '#8DE4A9',
                'secondary' => '#5AB4A4',
                'background' => '#080D0C',
                'surface' => '#101A17',
            ],
            'xlumia' => [
                'name' => 'Xlumia-stil',
                'description' => 'Et moderne hostinglayout med dyb violet, kølige highlights og diskret glaseffekt.',
                'subtitle' => 'Premium · aurora',
                'accent' => '#A990FF',
                'secondary' => '#5BD9EA',
                'background' => '#090817',
                'surface' => '#17132B',
            ],
            'horizon' => [
                'name' => 'Horizon-stil',
                'description' => 'Rent, struktureret design med grafitblå flader, præcise kanter og klare handlingsknapper.',
                'subtitle' => 'Klassisk hosting · moderne',
                'accent' => '#7EB7FF',
                'secondary' => '#5D8CF6',
                'background' => '#0A111B',
                'surface' => '#152334',
            ],
            'dinonode' => [
                'name' => 'DinoNode-stil',
                'description' => 'Spilorienteret, mørkegrønt hostingdesign med kraftigere kort og tydelige produktområder.',
                'subtitle' => 'Gaming · grøn',
                'accent' => '#7CDB7E',
                'secondary' => '#C0E782',
                'background' => '#08130E',
                'surface' => '#15261C',
            ],
        ];
    }

    public function current(): string
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        try {
            $value = (string) $this->settings->get(self::SETTING_KEY, self::DEFAULT_THEME);
        } catch (\Throwable $exception) {
            report($exception);
            $value = self::DEFAULT_THEME;
        }

        return $this->resolved = array_key_exists($value, self::presets()) ? $value : self::DEFAULT_THEME;
    }

    public function save(string $theme): void
    {
        if (!array_key_exists($theme, self::presets())) {
            throw new \InvalidArgumentException('Unknown frontend design preset.');
        }

        $this->settings->set(self::SETTING_KEY, $theme);
        $this->resolved = $theme;
    }

    /**
     * Only the root admin can preview a different layout by query string.
     * Visitors never see a draft preview; they always see the published theme.
     */
    public function activeForRequest(): string
    {
        $preview = request()->query('frontend_theme_preview');
        $user = auth()->user();

        if (is_string($preview)
            && $user !== null
            && (bool) $user->root_admin
            && array_key_exists($preview, self::presets())
        ) {
            return $preview;
        }

        return $this->current();
    }
}
