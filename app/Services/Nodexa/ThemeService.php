<?php

namespace Pterodactyl\Services\Nodexa;

use Carbon\Carbon;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

/**
 * Global Nodexa website theme settings.
 *
 * Only hard-coded palette values are rendered into CSS. The database stores
 * a preset ID, never arbitrary CSS or user-provided markup.
 */
class ThemeService
{
    private const PREFIX = 'nodexa::theme.';

    private ?array $resolved = null;

    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    public static function presets(): array
    {
        return [
            'classic' => ['name' => 'Nodexa Classic', 'description' => 'Det originale mørke blå/violette design.', 'accent' => '#3D8DFF', 'secondary' => '#7657FF', 'success' => '#47D99C', 'bg' => '#050B14', 'surface' => '#081321', 'card' => '#0B1828', 'border' => '#172B43', 'adminBg' => '#080D18', 'adminCard' => '#101827', 'adminBorder' => '#22304A'],
            'midnight' => ['name' => 'Midnight', 'description' => 'Dyb navy med kølig blå accent.', 'accent' => '#599BFF', 'secondary' => '#8692E9', 'success' => '#51CEA9', 'bg' => '#070D1B', 'surface' => '#0C1627', 'card' => '#101D31', 'border' => '#253651', 'adminBg' => '#090F1C', 'adminCard' => '#132035', 'adminBorder' => '#2C3B55'],
            'violet' => ['name' => 'Violet', 'description' => 'Lilla og indigo med rolige kontraster.', 'accent' => '#9A77FF', 'secondary' => '#6278FF', 'success' => '#55D9A9', 'bg' => '#0B0918', 'surface' => '#141028', 'card' => '#1A1532', 'border' => '#372C59', 'adminBg' => '#0E0B1C', 'adminCard' => '#1D1732', 'adminBorder' => '#382D54'],
            'emerald' => ['name' => 'Emerald', 'description' => 'Grønne og turkise detaljer.', 'accent' => '#31C79A', 'secondary' => '#40AFAE', 'success' => '#67D7AC', 'bg' => '#061310', 'surface' => '#0B211B', 'card' => '#102B22', 'border' => '#235041', 'adminBg' => '#071610', 'adminCard' => '#10271F', 'adminBorder' => '#285244'],
            'rose' => ['name' => 'Rose', 'description' => 'Dæmpet pink og rosatoner.', 'accent' => '#E86EA8', 'secondary' => '#AF78E5', 'success' => '#53C9A7', 'bg' => '#150B17', 'surface' => '#241327', 'card' => '#2B192E', 'border' => '#55324E', 'adminBg' => '#160D1B', 'adminCard' => '#29172E', 'adminBorder' => '#563351'],
            'sunset' => ['name' => 'Sunset', 'description' => 'Varm orange med koral og guld.', 'accent' => '#F2A354', 'secondary' => '#EF6D78', 'success' => '#7AD9A9', 'bg' => '#16100E', 'surface' => '#261914', 'card' => '#302019', 'border' => '#5C3B30', 'adminBg' => '#19110F', 'adminCard' => '#2F211A', 'adminBorder' => '#624237'],
            'red' => ['name' => 'Crimson', 'description' => 'Mørkerød og diskret rød accent.', 'accent' => '#E75E6A', 'secondary' => '#CF8499', 'success' => '#50CFA4', 'bg' => '#130B10', 'surface' => '#23131B', 'card' => '#2A1821', 'border' => '#523141', 'adminBg' => '#170D12', 'adminCard' => '#291A22', 'adminBorder' => '#583441'],
            'halloween' => ['name' => 'Halloween', 'description' => 'Græskarorange, dyb lilla og diskrete dekorationer.', 'accent' => '#F18C35', 'secondary' => '#A96BF5', 'success' => '#DBB26B', 'bg' => '#100B15', 'surface' => '#1C1124', 'card' => '#25182C', 'border' => '#483052', 'adminBg' => '#120D18', 'adminCard' => '#27182E', 'adminBorder' => '#52345A'],
            'christmas' => ['name' => 'Jul', 'description' => 'Julerød, gran-grøn og blid sne.', 'accent' => '#DE5D65', 'secondary' => '#35B98A', 'success' => '#65C99B', 'bg' => '#07140F', 'surface' => '#0E211B', 'card' => '#142C22', 'border' => '#305244', 'adminBg' => '#091710', 'adminCard' => '#142A20', 'adminBorder' => '#365445'],
        ];
    }

    public function current(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $presets = self::presets();
        $selected = 'classic';
        $automatic = false;

        try {
            $selected = (string) $this->settings->get(self::PREFIX . 'selected', 'classic');
            $automatic = filter_var($this->settings->get(self::PREFIX . 'automatic', '0'), FILTER_VALIDATE_BOOLEAN);
        } catch (\Throwable $exception) {
            // Do not break the public website during installation or DB recovery.
            report($exception);
        }

        if (!array_key_exists($selected, $presets)) {
            $selected = 'classic';
        }

        $active = $selected;
        if ($automatic) {
            $today = Carbon::now(config('app.timezone', 'Europe/Copenhagen'));
            if ($today->month === 10) {
                $active = 'halloween';
            } elseif ($today->month === 12) {
                $active = 'christmas';
            }
        }

        return $this->resolved = [
            'selected' => $selected,
            'active' => $active,
            'automatic' => $automatic,
            'colors' => $presets[$active],
        ];
    }

    public function save(string $selected, bool $automatic): void
    {
        if (!array_key_exists($selected, self::presets())) {
            throw new \InvalidArgumentException('Unknown theme preset.');
        }

        $this->settings->set(self::PREFIX . 'selected', $selected);
        $this->settings->set(self::PREFIX . 'automatic', $automatic ? '1' : '0');
        $this->resolved = null;
    }
}
