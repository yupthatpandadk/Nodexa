<?php

namespace Pterodactyl\Services\Nodexa;

use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\Egg;
use RuntimeException;

/**
 * Suggested storefront hosting tiers per installed Pterodactyl Egg.
 *
 * Presets NEVER publish products by default and NEVER modify existing
 * products, prices or paid orders. All prices are editable suggestions.
 */
class EggHostingPackageService
{
    public const TIERS = [
        'starter' => 'Starter',
        'plus' => 'Plus',
        'pro' => 'Pro',
        'ultimate' => 'Ultimate',
    ];

    public const GAME_LABELS = [
        'minecraft' => 'Minecraft',
        'fivem' => 'FiveM',
        'rust' => 'Rust',
        'ark' => 'ARK',
        'voice' => 'Voice',
        'generic' => 'Andre spil',
    ];

    /**
     * RAM/SSD in MB, CPU as Pterodactyl percentage (100% ~ one core).
     * Prices in DKK per month, intended as editable starting points.
     */
    private const PROFILES = [
        'minecraft' => [
            [2048, 100, 10240, 1, 1, 1, 29],
            [4096, 150, 20480, 2, 2, 2, 49],
            [8192, 250, 40960, 3, 4, 3, 89],
            [12288, 350, 61440, 5, 5, 4, 129],
        ],
        'fivem' => [
            [4096, 150, 20480, 1, 1, 1, 59],
            [6144, 200, 30720, 2, 2, 2, 89],
            [8192, 300, 51200, 3, 3, 3, 129],
            [12288, 400, 81920, 5, 5, 4, 189],
        ],
        'rust' => [
            [6144, 200, 30720, 1, 1, 1, 89],
            [8192, 250, 40960, 2, 2, 2, 129],
            [12288, 350, 61440, 3, 3, 3, 189],
            [16384, 450, 81920, 5, 4, 4, 259],
        ],
        'ark' => [
            [8192, 200, 40960, 1, 1, 1, 119],
            [12288, 300, 61440, 2, 2, 2, 169],
            [16384, 400, 81920, 3, 3, 3, 229],
            [24576, 500, 122880, 5, 4, 4, 329],
        ],
        'voice' => [
            [512, 50, 2048, 0, 1, 1, 15],
            [1024, 75, 4096, 0, 2, 1, 25],
            [2048, 100, 8192, 0, 3, 2, 39],
            [4096, 150, 16384, 0, 4, 2, 59],
        ],
        'generic' => [
            [2048, 100, 10240, 1, 1, 1, 29],
            [4096, 150, 20480, 2, 2, 2, 49],
            [8192, 250, 40960, 3, 3, 3, 89],
            [16384, 400, 81920, 4, 5, 4, 159],
        ],
    ];

    public function detectCategory(Egg $egg): string
    {
        $name = mb_strtolower(trim($egg->name . ' ' . ($egg->nest?->name ?? '')));
        if (preg_match('/minecraft|paper|purpur|spigot|folia|pufferfish|vanilla|fabric|forge|quilt|neoforge|sponge|bungee|velocity/', $name)) {
            return 'minecraft';
        }
        if (preg_match('/fivem|fxserver|cfx|redm/', $name)) {
            return 'fivem';
        }
        if (preg_match('/\brust\b/', $name)) {
            return 'rust';
        }
        if (preg_match('/\bark\b|survival evolved|survival ascended/', $name)) {
            return 'ark';
        }
        if (preg_match('/mumble|teamspeak|voice/', $name)) {
            return 'voice';
        }
        return 'generic';
    }

    public function packages(string $category): array
    {
        if (!isset(self::PROFILES[$category])) {
            throw new RuntimeException('Ukendt pakkekategori.');
        }

        $result = [];
        foreach (array_keys(self::TIERS) as $i => $tier) {
            [$memory, $cpu, $disk, $databases, $backups, $allocations, $price] = self::PROFILES[$category][$i];
            $result[$tier] = compact('memory', 'cpu', 'disk', 'databases', 'backups', 'allocations', 'price');
        }

        return $result;
    }

    public function allProfiles(): array
    {
        $result = [];
        foreach (array_keys(self::GAME_LABELS) as $category) {
            $result[$category] = $this->packages($category);
        }

        return $result;
    }

    /**
     * Returns created, skipped, and selected category.
     * Existing product records are never overwritten by the generator.
     */
    public function generate(
        Egg $egg,
        string $category,
        array $tiers,
        array $prices = []
    ): array {
        $category = $category === 'auto' ? $this->detectCategory($egg) : $category;
        $packages = $this->packages($category);
        $tiers = array_values(array_unique($tiers));
        if ($tiers === [] || array_diff($tiers, array_keys(self::TIERS))) {
            throw new RuntimeException('Vælg mindst ét gyldigt pakkeniveau.');
        }

        $created = 0;
        $skipped = 0;
        DB::transaction(function () use ($egg, $category, $tiers, $prices, $packages, &$created, &$skipped) {
            foreach ($tiers as $tier) {
                $plan = $packages[$tier];
                $price = array_key_exists($tier, $prices) && $prices[$tier] !== null && $prices[$tier] !== ''
                    ? (float) $prices[$tier]
                    : (float) $plan['price'];

                if (!is_finite($price) || $price < 0.01 || $price > 99999) {
                    throw new RuntimeException('Ugyldig månedlig pris for ' . self::TIERS[$tier] . '.');
                }

                // A stable slug ensures repeat imports do not duplicate packs.
                // Insert-or-ignore is atomic even for simultaneous requests.
                $slug = 'egg-' . $egg->id . '-' . $tier;
                $name = mb_substr($egg->name, 0, 70) . ' ' . self::TIERS[$tier];
                $rows = DB::table('nodexa_store_products')->insertOrIgnore([
                    'name' => $name,
                    'slug' => $slug,
                    'game' => self::GAME_LABELS[$category],
                    'description' => self::TIERS[$tier] . '-serverpakke til ' . $egg->name
                        . '. ' . ($plan['memory'] / 1024) . ' GB RAM, '
                        . ($plan['disk'] / 1024) . ' GB SSD, '
                        . $plan['cpu'] . '% CPU. Administreres i Nodexa Control Panel.',
                    'price_monthly' => number_format($price, 2, '.', ''),
                    'egg_id' => $egg->id,
                    'memory' => $plan['memory'],
                    'disk' => $plan['disk'],
                    'cpu' => $plan['cpu'],
                    'databases' => $plan['databases'],
                    'backups' => $plan['backups'],
                    'allocations' => $plan['allocations'],
                    'enabled' => false,
                    'featured' => $tier === 'plus',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($rows > 0) {
                    $created++;
                } else {
                    $skipped++;
                }
            }
        }, 3);

        return ['created' => $created, 'skipped' => $skipped, 'category' => $category];
    }
}
